<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\StatusCategory;
use App\Models\Backlog;
use App\Models\BacklogActivity;
use App\Models\Project;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const TAB_CATEGORIES = [
        'programmer' => [StatusCategory::Todo, StatusCategory::InProgress],
        'qa' => [StatusCategory::ReadyForQa, StatusCategory::Testing],
    ];

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->can('dashboard.view-all');
        $projects = $this->projects($user);
        $project = $projects->firstWhere('id', (int) $request->query('project')) ?? $projects->first();

        return view('dashboard', [
            'user' => $user,
            'isAdmin' => $isAdmin,
            'projects' => $projects,
            'project' => $project,
            'stats' => $isAdmin ? $this->adminStats($projects) : $this->userStats($user),
            'initial' => $project ? $this->payload($user, $project, null, 'programmer') : null,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $request->validate([
            'project' => ['required', 'integer'],
            'status' => ['nullable', 'integer'],
            'tab' => ['nullable', 'in:programmer,qa'],
        ]);

        $user = $request->user();
        $project = $this->projects($user)->firstWhere('id', (int) $request->query('project'));
        abort_unless($project, 403);

        return response()->json($this->payload($user, $project, $request->integer('status') ?: null, $request->query('tab', 'programmer')));
    }

    private function payload(User $user, Project $project, ?int $statusId, string $tab): array
    {
        $isAdmin = $user->can('dashboard.view-all');
        $scope = fn (Builder $q) => $q->where('project_id', $project->id)
            ->when(! $isAdmin, fn ($q) => $q->assignedTo($user));

        $counts = Backlog::query()->tap($scope)
            ->selectRaw('status_id, count(*) as total')->groupBy('status_id')->pluck('total', 'status_id');
        $statuses = $project->availableStatuses()->filter(fn (Status $s) => ($counts[$s->id] ?? 0) > 0)->values();
        $total = (int) $counts->sum();

        $slices = $statuses->map(fn (Status $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'count' => (int) $counts[$s->id],
            'pct' => $total ? round($counts[$s->id] / $total * 100) : 0,
            'color' => $s->color(),
        ])->values();

        $selected = $statuses->firstWhere('id', $statusId);

        $list = Backlog::query()->tap($scope)
            ->with(['status', 'programmer', 'qa', 'project'])
            ->when($selected, fn ($q) => $q->where('status_id', $selected->id))
            ->when(! $selected, fn ($q) => $q->whereHas('status', fn ($s) => $s->whereIn('category', self::TAB_CATEGORIES[$tab])))
            ->orderByRaw("case priority when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")
            ->orderByRaw('end_date is null')->orderBy('end_date')
            ->limit(50)->get();

        $tabCount = fn (string $t) => Backlog::query()->tap($scope)
            ->whereHas('status', fn ($s) => $s->whereIn('category', self::TAB_CATEGORIES[$t]))->count();

        $done = $statuses->filter(fn ($s) => $s->category === StatusCategory::Done)->sum(fn ($s) => $counts[$s->id]);

        return [
            'project' => ['id' => $project->id, 'name' => $project->name, 'code' => $project->code],
            'slices' => $slices,
            'total' => $total,
            'done_pct' => $total ? (int) round($done / $total * 100) : 0,
            'selected' => $selected ? ['id' => $selected->id, 'name' => $selected->name] : null,
            'tab' => $tab,
            'n_programmer' => $tabCount('programmer'),
            'n_qa' => $tabCount('qa'),
            'rows_html' => view('dashboard.partials.rows', ['backlogs' => $list, 'isAdmin' => $isAdmin, 'user' => $user])->render(),
            'list_info' => $list->count() === 50 ? 'Menampilkan 50 teratas' : $list->count().' backlog',
            'activities_html' => view('dashboard.partials.activities', [
                'activities' => BacklogActivity::query()
                    ->whereHas('backlog', $scope)
                    ->with(['user', 'backlog'])
                    ->latest('created_at')->latest('id')->limit(6)->get(),
            ])->render(),
        ];
    }

    private function projects(User $user): Collection
    {
        return Project::query()->visibleTo($user)
            ->orderByRaw("case status when 'active' then 0 when 'on_hold' then 1 else 2 end")
            ->orderBy('name')->get(['id', 'code', 'name', 'status', 'color']);
    }

    private function adminStats(Collection $projects): array
    {
        $ids = $projects->pluck('id');
        $base = fn () => Backlog::query()->whereIn('project_id', $ids);

        return [
            ['label' => 'Project aktif', 'value' => $projects->where('status', ProjectStatus::Active)->count(), 'icon' => 'folder'],
            ['label' => 'Total backlog', 'value' => $base()->count(), 'icon' => 'list'],
            ['label' => 'Ready for QA', 'value' => $base()->whereHas('status', fn ($q) => $q->where('category', StatusCategory::ReadyForQa))->count(), 'icon' => 'check'],
            ['label' => 'Terlambat', 'value' => $late = $base()->notDone()->whereDate('end_date', '<', today())->count(), 'icon' => 'alert-triangle', 'danger' => $late > 0],
        ];
    }

    private function userStats(User $user): array
    {
        $mine = fn () => Backlog::query()->assignedTo($user);

        return [
            ['label' => 'Tugas aktif', 'value' => $mine()->notDone()->count(), 'icon' => 'list'],
            ['label' => 'Menunggu QA', 'value' => Backlog::where('programmer_id', $user->id)->whereHas('status', fn ($q) => $q->whereIn('category', self::TAB_CATEGORIES['qa']))->count(), 'icon' => 'send'],
            ['label' => 'Perlu Anda tes', 'value' => Backlog::where('qa_id', $user->id)->whereHas('status', fn ($q) => $q->where('category', StatusCategory::ReadyForQa))->count(), 'icon' => 'check'],
            ['label' => 'Selesai minggu ini', 'value' => $mine()->where('completed_at', '>=', now()->startOfWeek())->count(), 'icon' => 'zap'],
        ];
    }
}
