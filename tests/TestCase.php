<?php

namespace Tests;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Role, permission, dan status bawaan dibutuhkan hampir di semua test. */
    protected function seedRoles(): void
    {
        $this->seed([RolePermissionSeeder::class, StatusSeeder::class]);
    }

    /**
     * Buat project beserta anggotanya.
     *
     * @param  array<string, User>  $members  role => user, mis. ['pm' => $andi]
     */
    protected function makeProject(array $members = [], array $attrs = []): Project
    {
        static $n = 0;
        $n++;

        $project = Project::create($attrs + [
            'code' => 'TP'.$n,
            'name' => 'Project Test '.$n,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => ProjectStatus::Active,
        ]);

        foreach ($members as $role => $users) {
            // Jangan pakai (array) $users: cast objek model menghasilkan array propertinya.
            foreach ($users instanceof User ? [$users] : $users as $user) {
                $project->members()->attach($user->id, ['project_role' => $role]);
            }
        }

        return $project;
    }
}
