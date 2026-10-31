<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function payload(array $override = []): array
    {
        return $override + [
            'code' => 'phr',
            'name' => 'Portal HR',
            'description' => '<p>Halo <script>alert(1)</script><b>dunia</b></p>',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'members' => [],
        ];
    }

    public function test_admin_membuat_project_dengan_anggota(): void
    {
        $admin = User::factory()->admin()->create();
        $budi = User::factory()->create();

        $this->actingAs($admin)->post('/projects', $this->payload(['members' => [
            ['user_id' => $admin->id, 'role' => 'pm'],
            ['user_id' => $budi->id, 'role' => 'programmer'],
            ['user_id' => '', 'role' => 'qa'], // baris kosong diabaikan
        ]]))->assertRedirect('/projects/PHR');

        $project = Project::where('code', 'PHR')->firstOrFail();
        $this->assertSame('pm', $project->roleOf($admin));
        $this->assertSame('programmer', $project->roleOf($budi));
        $this->assertStringNotContainsString('<script>', $project->description);
        $this->assertStringContainsString('<b>dunia</b>', $project->description);
    }

    public function test_project_wajib_punya_pm_dan_kode_unik(): void
    {
        $admin = User::factory()->admin()->create();
        $budi = User::factory()->create();
        $this->makeProject(attrs: ['code' => 'PHR']);

        $this->actingAs($admin)->post('/projects', $this->payload(['members' => [
            ['user_id' => $budi->id, 'role' => 'programmer'],
        ]]))->assertSessionHasErrors(['members', 'code']);
    }

    public function test_user_biasa_tidak_bisa_membuat_project(): void
    {
        $this->actingAs(User::factory()->create())->get('/projects/create')->assertForbidden();
    }

    public function test_daftar_project_hanya_menampilkan_project_milik_user(): void
    {
        $budi = User::factory()->create();
        $mine = $this->makeProject(['programmer' => $budi], ['name' => 'Project Budi']);
        $other = $this->makeProject([], ['name' => 'Project Rahasia']);

        $this->actingAs($budi)->get('/projects')->assertOk()->assertSee('Project Budi')->assertDontSee('Project Rahasia');
        $this->actingAs($budi)->get("/projects/{$other->code}")->assertForbidden();
        $this->actingAs($budi)->get("/projects/{$mine->code}")->assertOk();
    }

    public function test_pm_bisa_mengubah_project_tetapi_programmer_tidak(): void
    {
        $pm = User::factory()->create();
        $prog = User::factory()->create();
        $project = $this->makeProject(['pm' => $pm, 'programmer' => $prog]);

        $data = ['name' => 'Nama Baru', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active'];

        $this->actingAs($prog)->put("/projects/{$project->code}", $data)->assertForbidden();
        $this->actingAs($pm)->put("/projects/{$project->code}", $data)->assertRedirect();
        $this->assertSame('Nama Baru', $project->fresh()->name);
    }

    public function test_rentang_project_harus_mencakup_sprint(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->makeProject();
        $project->sprints()->create(['name' => 'Sprint 1', 'start_date' => '2026-03-01', 'end_date' => '2026-03-14', 'status' => 'planned']);

        $this->actingAs($admin)->put("/projects/{$project->code}", [
            'name' => $project->name, 'start_date' => '2026-03-05', 'end_date' => '2026-12-31', 'status' => 'active',
        ])->assertSessionHasErrors('start_date');
    }

    public function test_tandai_project_selesai_mengunci_sprint(): void
    {
        $pm = User::factory()->create();
        $project = $this->makeProject(['pm' => $pm]);
        $sprint = $project->sprints()->create(['name' => 'Sprint 1', 'start_date' => '2026-03-01', 'end_date' => '2026-03-14', 'status' => 'active']);

        $this->actingAs($pm)->patch("/projects/{$project->code}/done")->assertRedirect();

        $this->assertSame(ProjectStatus::Done, $project->fresh()->status);
        $this->assertSame(SprintStatus::Done, $sprint->fresh()->status);
        // Project selesai: tidak bisa menambah backlog/sprint baru.
        $this->actingAs($pm)->get("/projects/{$project->code}/backlogs/create")->assertForbidden();
    }

    public function test_halaman_tab_project_tampil(): void
    {
        $pm = User::factory()->create();
        $project = $this->makeProject(['pm' => $pm]);

        foreach (['', '/sprints', '/backlogs', '/backlogs?view=board', '/members', '/sprints/create', '/backlogs/create'] as $path) {
            $this->actingAs($pm)->get("/projects/{$project->code}{$path}")->assertOk();
        }

        $this->actingAs($pm)->get('/projects?view=table')->assertOk()->assertSee($project->name);
    }
}
