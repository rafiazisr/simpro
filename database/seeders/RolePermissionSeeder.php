<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Membuat semua permission dan 6 role bawaan beserta hak akses default.
     * Aman dijalankan ulang: permission role yang sudah diubah Admin tidak ditimpa.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::names() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $defaults = PermissionCatalog::defaultsByRole();

        foreach (PermissionCatalog::SYSTEM_ROLES as $name => [$label, $scope]) {
            $role = Role::query()->firstOrNew(['name' => $name, 'guard_name' => 'web']);
            $isNew = ! $role->exists;
            $role->fill(['label' => $label, 'scope' => $scope, 'is_system' => true])->save();

            if ($isNew) {
                $role->syncPermissions($defaults[$name] ?? []);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
