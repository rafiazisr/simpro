<?php

namespace Tests\Feature;

use App\Models\Backlog;
use App\Models\User;
use App\Notifications\ProjectAssigned;
use App\Services\StatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Bagian "Assign ke Project" di form Manajemen User. */
class UserProjectAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function payload(User $user, array $projects): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'user_level' => $user->user_level->value,
            'is_active' => '1',
            'projects_sync' => '1',
            'projects' => $projects,
        ];
    }

    public function test_form_menampilkan_bagian_assign_project_tanpa_teks_fase(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->makeProject(['pm' => $admin]);
        $user = User::factory()->create();

        $this->actingAs($admin)->get("/users/{$user->id}/edit")->assertOk()
            ->assertSee('Assign ke Project')
            ->assertSee($project->name)
            ->assertDontSee('Fase 2');
    }

    public function test_user_baru_langsung_di_assign_ke_project_dan_dapat_notifikasi(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $project = $this->makeProject(['pm' => $admin]);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Gita Permata',
            'email' => 'gita@example.test',
            'password' => 'Rahasia2026',
            'user_level' => 'user',
            'is_active' => '1',
            'projects_sync' => '1',
            'projects' => [['project_id' => $project->id, 'role' => 'qa']],
        ])->assertRedirect('/users')->assertSessionHasNoErrors();

        $gita = User::where('email', 'gita@example.test')->firstOrFail();
        $this->assertSame('qa', $project->roleOf($gita));
        Notification::assertSentTo($gita, ProjectAssigned::class);
    }

    public function test_ubah_role_tambah_dan_keluarkan_project_sekaligus(): void
    {
        $admin = User::factory()->admin()->create();
        $budi = User::factory()->create();
        $a = $this->makeProject(['pm' => $admin, 'programmer' => $budi]);
        $b = $this->makeProject(['pm' => $admin, 'qa' => $budi]);
        $c = $this->makeProject(['pm' => $admin]);

        $this->actingAs($admin)->put("/users/{$budi->id}", $this->payload($budi, [
            ['project_id' => $a->id, 'role' => 'qa'],          // ubah role
            ['project_id' => $c->id, 'role' => 'programmer'],  // tambah
        ]))->assertRedirect('/users')->assertSessionHasNoErrors();               // $b dikeluarkan

        $this->assertSame('qa', $a->roleOf($budi));
        $this->assertNull($b->roleOf($budi));
        $this->assertSame('programmer', $c->roleOf($budi));
    }

    public function test_tanpa_projects_sync_keanggotaan_tidak_berubah(): void
    {
        $admin = User::factory()->admin()->create();
        $budi = User::factory()->create();
        $a = $this->makeProject(['pm' => $admin, 'programmer' => $budi]);

        $data = $this->payload($budi, []);
        unset($data['projects_sync'], $data['projects']);
        $this->actingAs($admin)->put("/users/{$budi->id}", $data)->assertRedirect('/users');

        $this->assertSame('programmer', $a->roleOf($budi));
    }

    public function test_pm_terakhir_tidak_bisa_dikeluarkan_dan_tidak_ada_yang_berubah(): void
    {
        $admin = User::factory()->admin()->create();
        $andi = User::factory()->create();
        $a = $this->makeProject(['pm' => $andi]);
        $c = $this->makeProject(['pm' => $admin]);

        $this->actingAs($admin)->from("/users/{$andi->id}/edit")->put("/users/{$andi->id}", $this->payload($andi, [
            ['project_id' => $c->id, 'role' => 'programmer'],
        ]))->assertRedirect("/users/{$andi->id}/edit")->assertSessionHasErrors('projects');

        $this->assertSame('pm', $a->roleOf($andi));
        $this->assertNull($c->roleOf($andi));
    }

    public function test_user_dengan_backlog_aktif_tidak_bisa_dikeluarkan(): void
    {
        $admin = User::factory()->admin()->create();
        $budi = User::factory()->create();
        $a = $this->makeProject(['pm' => $admin, 'programmer' => $budi]);
        Backlog::create([
            'project_id' => $a->id, 'number' => 1, 'code' => $a->code.'-1', 'title' => 'Tugas',
            'status_id' => app(StatusService::class)->default('In Progress')->id, 'priority' => 'medium',
            'programmer_id' => $budi->id, 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put("/users/{$budi->id}", $this->payload($budi, []))
            ->assertSessionHasErrors('projects');

        $this->assertSame('programmer', $a->roleOf($budi));
    }

    public function test_project_dipilih_dua_kali_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $budi = User::factory()->create();
        $a = $this->makeProject(['pm' => $admin]);

        $this->actingAs($admin)->put("/users/{$budi->id}", $this->payload($budi, [
            ['project_id' => $a->id, 'role' => 'qa'],
            ['project_id' => $a->id, 'role' => 'programmer'],
        ]))->assertSessionHasErrors('projects.1.project_id');

        $this->assertNull($a->roleOf($budi));
    }

    public function test_kolom_project_di_daftar_user_menampilkan_jumlah(): void
    {
        $admin = User::factory()->admin()->create();
        $budi = User::factory()->create(['name' => 'Budi Unik']);
        $this->makeProject(['pm' => $admin, 'programmer' => $budi]);
        $this->makeProject(['pm' => $admin, 'qa' => $budi]);

        $this->actingAs($admin)->get('/users?q=Budi+Unik')->assertOk()
            ->assertSee('data-label="Project">2</td>', false);
    }
}
