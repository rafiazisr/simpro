<?php

namespace App\Http\Controllers;

use App\Enums\UserLevel;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\ProjectMemberService;
use App\Support\ProjectRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private ProjectMemberService $members)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        return view('users.index', $this->listData($request));
    }

    /** Form tambah tampil sebagai modal di atas daftar user (sesuai desain). */
    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('users.index', ['modal' => 'form', 'editing' => null] + $this->formData($request, null) + $this->listData($request));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $plan = $request->syncsProjects() ? $this->members->plan(new User, $request->assignments(), $actor) : null;

        $user = DB::transaction(function () use ($request, $plan, $actor) {
            $user = User::create($request->payload());
            if ($plan) {
                $this->members->apply($plan, $user, $actor);
            }

            return $user;
        });

        return redirect()->route('users.index')->with('toast', "User {$user->name} berhasil ditambahkan.");
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.index', ['modal' => 'form', 'editing' => $user] + $this->formData($request, $user) + $this->listData($request));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $wasActive = $user->is_active;
        $data = $request->payload();

        // User tidak boleh menonaktifkan akunnya sendiri.
        if ($request->user()->is($user)) {
            $data['is_active'] = true;
        }

        $actor = $request->user();
        $plan = $request->syncsProjects() ? $this->members->plan($user, $request->assignments(), $actor) : null;

        DB::transaction(function () use ($user, $data, $plan, $actor) {
            $user->update($data);
            if ($plan) {
                $this->members->apply($plan, $user, $actor);
            }
        });

        if ($wasActive && ! $user->is_active) {
            $user->terminateSessions();
        }

        return redirect()->route('users.index')->with('toast', "Data {$user->name} berhasil disimpan.");
    }

    public function toggleActive(User $user): RedirectResponse
    {
        Gate::authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);

        if (! $user->is_active) {
            $user->terminateSessions();

            return back()->with('toast', "{$user->name} dinonaktifkan. Sesinya sudah diputus.");
        }

        return back()->with('toast', "{$user->name} diaktifkan kembali.");
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->terminateSessions();
        $user->delete(); // soft delete

        return redirect()->route('users.index')->with('toast', "User {$user->name} dihapus.");
    }

    /**
     * Data bagian "Assign ke Project" di form user.
     *
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?User $user): array
    {
        $manageable = $this->members->manageableProjects($request->user());
        $memberships = $user ? $user->projects()->orderBy('name')->get() : collect();

        return [
            'assignableProjects' => $manageable->map(fn ($p) => ['id' => $p->id, 'label' => "{$p->name} ({$p->code})"])->values(),
            'memberRows' => $memberships->whereIn('id', $manageable->pluck('id'))
                ->map(fn ($p) => ['project_id' => (string) $p->id, 'role' => $p->pivot->project_role])->values(),
            'lockedMemberships' => $memberships->whereNotIn('id', $manageable->pluck('id'))->values(),
            'roleOptions' => ProjectRoles::options(),
        ];
    }

    /** @return array<string, mixed> */
    private function listData(Request $request): array
    {
        $sort = in_array($request->query('sort'), ['name', 'last_login_at'], true) ? $request->query('sort') : 'name';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $users = User::query()
            ->withCount('projects')
            ->search($request->query('q'))
            ->when($request->query('level'), fn ($q, $level) => $q->where('user_level', $level))
            ->when($request->query('status'), fn ($q, $status) => $q->where('is_active', $status === 'aktif'))
            ->orderByRaw("user_level = 'super_admin' desc")
            ->orderBy($sort, $dir)
            ->paginate(config('settings.per_page'))
            ->withQueryString();

        return [
            'users' => $users,
            'levels' => UserLevel::cases(),
            'assignableLevels' => UserLevel::assignable(),
            'totalUsers' => User::count(),
            'activeUsers' => User::where('is_active', true)->count(),
            'sort' => $sort,
            'dir' => $dir,
            'modal' => null,
            'editing' => null,
        ];
    }
}
