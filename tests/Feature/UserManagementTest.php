<?php

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_admin_bisa_melihat_daftar_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/users')->assertOk()->assertSee('Manajemen User');
    }

    public function test_user_biasa_tidak_bisa_mengakses_manajemen_user(): void
    {
        $this->actingAs(User::factory()->create())->get('/users')->assertForbidden();
    }

    public function test_admin_bisa_membuat_user_dan_role_level_terpasang(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Gita Permata',
            'email' => 'gita@example.test',
            'password' => 'Rahasia2026',
            'user_level' => 'user',
            'is_active' => '1',
        ])->assertRedirect('/users');

        $user = User::where('email', 'gita@example.test')->firstOrFail();
        $this->assertSame(UserLevel::User, $user->user_level);
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_email_duplikat_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Duplikat',
            'email' => $existing->email,
            'password' => 'Rahasia2026',
            'user_level' => 'user',
        ])->assertSessionHasErrors(['email' => 'Email sudah digunakan user lain.']);
    }

    public function test_super_admin_tidak_bisa_dipilih_lewat_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Coba',
            'email' => 'coba@example.test',
            'password' => 'Rahasia2026',
            'user_level' => 'super_admin',
        ])->assertSessionHasErrors('user_level');
    }

    public function test_ubah_level_menyinkronkan_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->put("/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'user_level' => 'admin',
            'is_active' => '1',
        ])->assertRedirect('/users');

        $user->refresh();
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('user'));
    }

    public function test_akun_super_admin_terkunci(): void
    {
        $admin = User::factory()->admin()->create();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get("/users/{$super->id}/edit")->assertForbidden();
        $this->actingAs($admin)->patch("/users/{$super->id}/toggle-active")->assertForbidden();
        $this->actingAs($admin)->delete("/users/{$super->id}")->assertForbidden();

        // Super Admin lain pun tidak bisa menghapus Super Admin.
        $this->actingAs(User::factory()->superAdmin()->create())->delete("/users/{$super->id}")->assertForbidden();
    }

    public function test_admin_tidak_bisa_menonaktifkan_diri_sendiri(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch("/users/{$admin->id}/toggle-active")->assertForbidden();
    }

    public function test_nonaktifkan_dan_aktifkan_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->patch("/users/{$user->id}/toggle-active")->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)->patch("/users/{$user->id}/toggle-active")->assertRedirect();
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_hapus_user_memakai_soft_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->delete("/users/{$user->id}")->assertRedirect('/users');

        $this->assertSoftDeleted($user);
    }

    public function test_filter_dan_pencarian(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Andi Pratama']);
        User::factory()->create(['name' => 'Budi Santoso']);
        User::factory()->inactive()->create(['name' => 'Fajar Nugroho']);

        $this->actingAs($admin)->get('/users?q=budi')->assertSee('Budi Santoso')->assertDontSee('Fajar Nugroho');
        $this->actingAs($admin)->get('/users?status=nonaktif')->assertSee('Fajar Nugroho')->assertDontSee('Budi Santoso');
    }
}
