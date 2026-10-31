<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_seeder_membuat_role_dan_permission_default(): void
    {
        $this->assertSame(count(PermissionCatalog::names()), \Spatie\Permission\Models\Permission::count());
        $this->assertSame(6, Role::count());

        $pm = Role::findByName('pm');
        $this->assertTrue($pm->hasPermissionTo('sprint.manage'));
        $this->assertFalse($pm->hasPermissionTo('user.manage'));

        $qa = Role::findByName('qa');
        $this->assertTrue($qa->hasPermissionTo('backlog.update-status'));
        $this->assertFalse($qa->hasPermissionTo('documentation.create-update'));
    }

    public function test_super_admin_punya_semua_permission(): void
    {
        $super = User::factory()->superAdmin()->create();

        foreach (PermissionCatalog::names() as $perm) {
            $this->assertTrue($super->can($perm), "Super Admin harus punya {$perm}");
        }
    }

    public function test_admin_bisa_mengubah_matriks(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/roles')->assertOk()->assertSee('Matriks Permission');

        $matrix = collect(PermissionCatalog::defaultsByRole())->except('super_admin')->all();
        $matrix['qa'][] = 'documentation.create-update';

        $this->actingAs($admin)->put('/roles/matrix', ['matrix' => $matrix, 'selected' => 'qa'])
            ->assertRedirect('/roles?role=qa');

        $this->assertTrue(Role::findByName('qa')->hasPermissionTo('documentation.create-update'));
        $this->assertTrue($admin->fresh()->can('user.manage'));
    }

    public function test_permission_tidak_dikenal_ditolak(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/roles/matrix', ['matrix' => ['qa' => ['hack.everything']]])
            ->assertSessionHasErrors();
    }

    public function test_role_kustom_bisa_dibuat_dan_dihapus_tapi_role_bawaan_tidak(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/roles', ['label' => 'Tech Lead'])->assertRedirect('/roles?role=tech_lead');
        $role = Role::findByName('tech_lead');
        $this->assertFalse((bool) $role->is_system);

        $this->actingAs($admin)->delete("/roles/{$role->id}")->assertRedirect('/roles');
        $this->assertNull(Role::where('name', 'tech_lead')->first());

        $pm = Role::findByName('pm');
        $this->actingAs($admin)->delete("/roles/{$pm->id}")->assertForbidden();
    }

    public function test_user_biasa_tidak_bisa_mengakses_halaman_role(): void
    {
        $this->actingAs(User::factory()->create())->get('/roles')->assertForbidden();
    }
}
