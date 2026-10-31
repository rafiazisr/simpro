<?php

namespace App\Http\Controllers;

use App\Enums\SprintStatus;
use App\Enums\StatusCategory;
use App\Models\Project;
use App\Models\Sprint;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Swimlane Gantt: satu swimlane per project, satu bar per sprint (FR-20 s.d. FR-23).
 * Data mengikuti format DHTMLX Gantt: {data: [...], links: []}.
 */
class GanttController extends Controller
{
    public function index(Request $request): View
    {
        return view('gantt.index', [
            'projects' => Project::query()->visibleTo($request->user())->orderBy('name')->get(['id', 'code', 'name', 'color']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $request->validate([
            'projects' => ['nullable', 'array'],
            'projects.*' => ['integer'],
            'sprint_status' => ['nullable', 'in:active,planned_active'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $from = $request->date('from');
        $to = $request->date('to');

        $projects = Project::query()
            ->visibleTo($user)
            ->when($request->input('projects'), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->with(['members', 'sprints' => function ($q) use ($request, $from, $to) {
                $q->when($request->input('sprint_status') === 'active', fn ($q) => $q->where('status', SprintStatus::Active))
                    ->when($request->input('sprint_status') === 'planned_active', fn ($q) => $q->whereIn('status', [SprintStatus::Planned, SprintStatus::Active]))
                    ->when($from, fn ($q) => $q->whereDate('end_date', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('start_date', '<=', $to))
                    ->withCount([
                        'backlogs',
                        'backlogs as done_count' => fn ($b) => $b->whereHas('status', fn ($s) => $s->where('category', StatusCategory::Done)),
                    ]);
            }])
            ->withCount([
                'backlogs',
                'backlogs as done_backlogs_count' => fn ($b) => $b->whereHas('status', fn ($s) => $s->where('category', StatusCategory::Done)),
            ])
            ->when($from, fn ($q) => $q->whereDate('end_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('start_date', '<=', $to))
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($projects as $p) {
            $canDrag = $user->canInProject('timeline.manage', $p);

            $rows[] = [
                'id' => 'p'.$p->id,
                'text' => $p->name,
                'code' => $p->code,
                'kind' => 'project',
                'start_date' => $p->start_date->format('Y-m-d'),
                // DHTMLX memakai tanggal selesai eksklusif, jadi +1 hari.
                'end_date' => $p->end_date->copy()->addDay()->format('Y-m-d'),
                'end_label' => Format::date($p->end_date, false),
                'start_label' => Format::date($p->start_date, false),
                'progress' => $p->backlogs_count ? round($p->done_backlogs_count / $p->backlogs_count, 2) : 0,
                'swatch' => $p->color, // bukan 'color': DHTMLX memakainya sebagai warna bar
                'open' => true,
                'readonly' => true,
                'url' => route('projects.show', $p),
            ];

            foreach ($p->sprints->sortBy('start_date') as $s) {
                $rows[] = [
                    'id' => 's'.$s->id,
                    'sprint_id' => $s->id,
                    'parent' => 'p'.$p->id,
                    'kind' => 'sprint',
                    'text' => $s->name,
                    'project' => $p->name,
                    'status' => $s->status->value,
                    'start_date' => $s->start_date->format('Y-m-d'),
                    'end_date' => $s->end_date->copy()->addDay()->format('Y-m-d'),
                    'start_label' => Format::date($s->start_date, false),
                    'end_label' => Format::date($s->end_date, false),
                    'range_label' => $s->rangeLabel(),
                    'progress' => $s->backlogs_count ? round($s->done_count / $s->backlogs_count, 2) : 0,
                    'backlogs' => $s->backlogs_count,
                    // Sprint yang sudah Done atau project Done tidak bisa digeser.
                    'readonly' => ! ($canDrag && $s->status !== SprintStatus::Done && $p->status->value !== 'done'),
                ];
            }
        }

        return response()->json(['data' => $rows, 'links' => []]);
    }

    /** Daftar backlog sprint untuk modal saat bar diklik (FR-22). */
    public function sprintBacklogs(Sprint $sprint): JsonResponse
    {
        Gate::authorize('view', $sprint->project);

        $backlogs = $sprint->backlogs()->with(['status', 'programmer', 'qa'])->orderBy('number')->get();
        $total = $backlogs->count();
        $done = $backlogs->filter->isDone()->count();

        return response()->json([
            'sprint' => [
                'name' => $sprint->name,
                'project' => $sprint->project->name,
                'status' => $sprint->status->label(),
                'status_class' => $sprint->status->badgeClass(),
                'range' => $sprint->rangeLabel(),
                'start' => $sprint->start_date->format('Y-m-d'),
                'end' => $sprint->end_date->format('Y-m-d'),
                'progress' => $total ? (int) round($done / $total * 100) : 0,
                'done' => $done,
                'total' => $total,
                'url' => route('projects.backlogs.index', [$sprint->project, 'sprint' => $sprint->id]),
            ],
            'backlogs' => $backlogs->map(fn ($b) => [
                'code' => $b->code,
                'title' => $b->title,
                'status' => $b->status->name,
                'status_class' => $b->status->badgeClass(),
                'color' => $b->status->color(),
                'programmer' => $b->programmer ? ['name' => $b->programmer->name, 'initials' => $b->programmer->initials(), 'color' => $b->programmer->avatarColor()] : null,
                'qa' => $b->qa ? ['name' => $b->qa->name, 'initials' => $b->qa->initials(), 'color' => $b->qa->avatarColor()] : null,
                'start' => $b->start_date?->format('Y-m-d'),
                'end' => $b->end_date?->format('Y-m-d'),
                'range' => $b->start_date || $b->end_date ? Format::dateRange($b->start_date, $b->end_date, false) : 'Belum dijadwalkan',
                'late' => $b->isLate(),
                'url' => route('backlogs.show', $b),
            ])->values(),
        ]);
    }

    /** Geser/ubah ukuran bar sprint (FR-23). Hanya untuk user dengan izin timeline. */
    public function updateSprint(Request $request, Sprint $sprint): JsonResponse
    {
        $project = $sprint->project;
        abort_unless(
            $request->user()->canInProject('timeline.manage', $project)
            && $sprint->status !== SprintStatus::Done
            && $project->status->value !== 'done',
            403,
            'Anda tidak punya izin mengatur timeline sprint ini.'
        );

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();

        if ($start->lt($project->start_date) || $end->gt($project->end_date)) {
            throw ValidationException::withMessages([
                'start_date' => 'Sprint harus di dalam rentang project ('.Format::dateRange($project->start_date, $project->end_date).').',
            ]);
        }

        $sprint->update(['start_date' => $start, 'end_date' => $end]);

        return response()->json([
            'message' => "{$sprint->name} dijadwalkan ulang: ".$sprint->rangeLabel().'.',
            'range_label' => $sprint->rangeLabel(),
        ]);
    }
}
