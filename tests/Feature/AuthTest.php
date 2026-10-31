<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_halaman_login_tampil(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk')->assertSee(config('app.name'));
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_super_admin_dari_seeder_bisa_login(): void
    {
        config(['settings.super_admin.email' => 'sa@test.local', 'settings.super_admin.password' => 'Rahasia123']);
        $this->seed(SuperAdminSeeder::class);

        $this->post('/login', ['email' => 'sa@test.local', 'password' => 'Rahasia123'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isSuperAdmin());
        $this->assertTrue(auth()->user()->hasRole('super_admin'));
        $this->assertNotNull(auth()->user()->last_login_at);
    }

    public function test_password_salah_ditolak(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
    }

    public function test_user_nonaktif_tidak_bisa_login(): void
    {
        $user = User::factory()->inactive()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('account_disabled');

        $this->assertGuest();
    }

    public function test_sesi_user_yang_dinonaktifkan_langsung_diputus(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_dibatasi_lima_kali_per_menit(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_fitur_lupa_password_dihapus(): void
    {
        $this->get('/login')->assertOk()->assertSee('Lupa password? Hubungi Admin.')->assertDontSee('lupa-password');
        $this->get('/lupa-password')->assertNotFound();
    }

    public function test_perintah_reset_password_dari_server(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.test']);

        $this->artisan('user:reset-password', ['email' => 'budi@example.test', '--password' => 'PasswordBaru123'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('PasswordBaru123', $user->fresh()->password));
    }

    public function test_perintah_reset_password_menolak_email_tidak_dikenal_dan_password_pendek(): void
    {
        User::factory()->create(['email' => 'budi@example.test']);

        $this->artisan('user:reset-password', ['email' => 'tidak@ada.test', '--password' => 'PasswordBaru123'])->assertFailed();
        $this->artisan('user:reset-password', ['email' => 'budi@example.test', '--password' => 'pendek'])->assertFailed();
    }
}
