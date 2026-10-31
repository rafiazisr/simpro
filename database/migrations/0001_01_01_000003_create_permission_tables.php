<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = (array) config('permission.table_names') + [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ];
        $morphKey = config('permission.column_names.model_morph_key') ?: 'model_id';
        $pivotRole = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $pivotPermission = config('permission.column_names.permission_pivot_key') ?: 'permission_id';

        Schema::create($tables['permissions'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tables['roles'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->string('label')->nullable();
            $table->string('scope', 20)->default('project'); // level | project
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tables['model_has_permissions'], function (Blueprint $table) use ($tables, $morphKey, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($pivotPermission)->references('id')->on($tables['permissions'])->onDelete('cascade');
            $table->primary([$pivotPermission, $morphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tables['model_has_roles'], function (Blueprint $table) use ($tables, $morphKey, $pivotRole) {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($pivotRole)->references('id')->on($tables['roles'])->onDelete('cascade');
            $table->primary([$pivotRole, $morphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create($tables['role_has_permissions'], function (Blueprint $table) use ($tables, $pivotRole, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);
            $table->foreign($pivotPermission)->references('id')->on($tables['permissions'])->onDelete('cascade');
            $table->foreign($pivotRole)->references('id')->on($tables['roles'])->onDelete('cascade');
            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key', 'spatie.permission.cache'));
    }

    public function down(): void
    {
        $tables = (array) config('permission.table_names') + [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ];

        Schema::dropIfExists($tables['role_has_permissions']);
        Schema::dropIfExists($tables['model_has_roles']);
        Schema::dropIfExists($tables['model_has_permissions']);
        Schema::dropIfExists($tables['roles']);
        Schema::dropIfExists($tables['permissions']);
    }
};
