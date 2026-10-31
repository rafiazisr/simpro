<?php

namespace App\Http\Controllers;

use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $roles = $this->orderedRoles();
        $selected = $roles->firstWhere('name', $request->query('role')) ?? $roles->firstWhere('name', 'pm') ?? $roles->first();

        // Matriks: role => daftar nama permission yang dimiliki.
        $granted = $roles->mapWithKeys(fn (Role $r) => [$r->name => $r->permissions->pluck('name')->all()]);

        $userCounts = DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->selectRaw('role_id, count(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        return view('roles.index', [
            'roles' => $roles,
            'levelRoles' => $roles->where('scope', 'level'),
            'projectRoles' => $roles->where('scope', 'project'),
            'selected' => $selected,
            'groups' => PermissionCatalog::GROUPS,
            'granted' => $granted,
            'userCounts' => $userCounts,
        ]);
    }

    /** Simpan seluruh matriks sekaligus (tombol "Simpan Perubahan"). */
    public function updateMatrix(Request $request): RedirectResponse
    {
        $request->validate([
            'matrix' => ['array'],
            'matrix.*' => ['array'],
            'matrix.*.*' => ['string', Rule::in(PermissionCatalog::names())],
        ]);

        $matrix = $request->input('matrix', []);

        foreach ($this->orderedRoles() as $role) {
            if ($role->name === 'super_admin') {
                continue; // selalu punya semua akses
            }

            $role->syncPermissions($matrix[$role->name] ?? []);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index', ['role' => $request->input('selected')])
            ->with('toast', 'Hak akses berhasil disimpan.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
        ], [], ['label' => 'nama role']);

        $name = Str::slug($data['label'], '_');

        if ($name === '' || Role::where('name', $name)->exists()) {
            return back()->withErrors(['label' => 'Nama role sudah dipakai.'])->withInput();
        }

        Role::create([
            'name' => $name,
            'guard_name' => 'web',
            'label' => $data['label'],
            'scope' => 'project',
            'is_system' => false,
        ]);

        return redirect()->route('roles.index', ['role' => $name])->with('toast', "Role {$data['label']} dibuat.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'Role bawaan tidak bisa dihapus.');

        $label = $role->label ?? $role->name;
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('toast', "Role {$label} dihapus.");
    }

    /** @return Collection<int, Role> role bawaan sesuai urutan desain, lalu role kustom. */
    private function orderedRoles(): Collection
    {
        $order = array_flip(array_keys(PermissionCatalog::SYSTEM_ROLES));

        return Role::with('permissions')->get()
            ->sortBy(fn (Role $r) => [$order[$r->name] ?? 100, $r->id])
            ->values();
    }
}
