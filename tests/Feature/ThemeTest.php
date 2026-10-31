<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_halaman_login_punya_tombol_tema_dan_skrip_tema(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('theme-toggle', false)
            ->assertSee("localStorage.getItem('theme')", false)
            ->assertSee('rel="icon"', false);
    }

    public function test_halaman_aplikasi_punya_tombol_tema_di_topbar(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()
            ->assertSee('x-data="themeToggle"', false)
            ->assertSee('Ganti ke mode gelap', false);
    }
}
