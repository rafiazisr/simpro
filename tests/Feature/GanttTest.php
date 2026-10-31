<?php

namespace Tests\Feature;

use App\Models\Backlog;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GanttTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private User $prog;

    private Project $project;

    private Sprint $active;

    private Sprint $done;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->pm = User::factory()->create();
        $this->prog = User::factory()->create();
        $this->project = $this->makeProject(['pm' => $this->pm, 'programmer' => $this->prog], ['code' => 'PHR', 'name' => 'Portal HR']);
        $this->done = $this->project->sprints()->create(['name' => 'Sprint 1', 'start_date' => '2026-02-01', 'end_date' => '2026-02-14', 'status' => 'done']);
        $this->active = $this->project->sprints()->create(['name' => 'Sprint 2', 'start_date' => '2026-03-01', 'end_date' => '2026-03-14', 'status' => 'active']);
    }

    public function test_halaman_gantt_tampil(): void
    {
        $this->actingAs($this->prog)->get('/gantt')->assertOk()->assertSee('Gantt Chart')->assertSee('Portal HR');
    }

    public function test_data_gantt_swimlane_project_dan_bar_sprint(): void
    {
        $done = Status::where('name', 'Done')->first();
        Backlog::create(['project_id' => $this->project->id, 'sprint_id' => $this->active->id, 'number' => 1, 'code' => 'PHR-1', 'title' => 'A', 'status_id' => $done->id, 'priority' => 'low']);
        Backlog::create(['project_id' => $this->project->id, 'sprint_id' => $this->active->id, 'number' => 2, 'code' => 'PHR-2', 'title' => 'B', 'status_id' => Status::first()->id, 'priority' => 'low']);

        $res = $this->actingAs($this->pm)->getJson('/gantt/data')->assertOk()->json('data');

        $this->assertCount(3, $res);
        $this->assertSame('project', $res[0]['kind']);
        $this->assertSame('2027-01-01', $res[0]['end_date'], 'tanggal selesai eksklusif (+1 hari)');

        $sprint2 = collect($res)->firstWhere('text', 'Sprint 2');
        $this->assertSame('p'.$this->project->id, $sprint2['parent']);
        $this->assertSame(0.5, $sprint2['progress']);
        $this->assertFalse($sprint2['readonly'], 'PM boleh menggeser sprint aktif');
        $this->assertTrue(collect($res)->firstWhere('text', 'Sprint 1')['readonly'], 'sprint Done tidak bisa digeser');

        // Programmer: semua bar read-only.
        $asProg = $this->actingAs($this->prog)->getJson('/gantt/data')->json('data');
        $this->assertTrue(collect($asProg)->firstWhere('text', 'Sprint 2')['readonly']);
    }

    public function test_filter_gantt(): void
    {
        $other = $this->makeProject(['pm' => $this->pm], ['name' => 'Lain']);

        $only = $this->actingAs($this->pm)->getJson('/gantt/data?projects[]='.$this->project->id.'&sprint_status=active')->json('data');
        $this->assertSame(['Portal HR', 'Sprint 2'], array_column($only, 'text'));

        $byDate = $this->actingAs($this->pm)->getJson('/gantt/data?projects[]='.$this->project->id.'&from=2026-02-01&to=2026-02-20')->json('data');
        $this->assertSame(['Portal HR', 'Sprint 1'], array_column($byDate, 'text'));

        // Project yang bukan miliknya tidak ikut walau diminta.
        $outsider = User::factory()->create();
        $this->assertSame([], $this->actingAs($outsider)->getJson('/gantt/data?projects[]='.$other->id)->json('data'));
    }

    public function test_klik_bar_menampilkan_backlog_sprint(): void
    {
        Backlog::create(['project_id' => $this->project->id, 'sprint_id' => $this->active->id, 'number' => 1, 'code' => 'PHR-1', 'title' => 'Integrasi SSO', 'status_id' => Status::first()->id, 'priority' => 'low', 'programmer_id' => $this->prog->id, 'start_date' => '2026-03-02', 'end_date' => '2026-03-05']);

        $this->actingAs($this->prog)->getJson("/gantt/sprints/{$this->active->id}/backlogs")
            ->assertOk()
            ->assertJsonPath('sprint.name', 'Sprint 2')
            ->assertJsonPath('backlogs.0.code', 'PHR-1')
            ->assertJsonPath('backlogs.0.programmer.name', $this->prog->name);

        $this->actingAs(User::factory()->create())->getJson("/gantt/sprints/{$this->active->id}/backlogs")->assertForbidden();
    }

    public function test_drag_sprint_hanya_untuk_yang_berhak(): void
    {
        $this->actingAs($this->prog)->putJson("/gantt/sprints/{$this->active->id}", ['start_date' => '2026-03-03', 'end_date' => '2026-03-16'])->assertForbidden();
        $this->actingAs($this->pm)->putJson("/gantt/sprints/{$this->done->id}", ['start_date' => '2026-02-03', 'end_date' => '2026-02-16'])->assertForbidden();

        $this->actingAs($this->pm)->putJson("/gantt/sprints/{$this->active->id}", ['start_date' => '2026-03-03', 'end_date' => '2026-03-16'])
            ->assertOk()->assertJsonPath('range_label', '3 – 16 Mar 2026');
        $this->assertSame('2026-03-16', $this->active->fresh()->end_date->format('Y-m-d'));

        // Di luar rentang project ditolak.
        $this->actingAs($this->pm)->putJson("/gantt/sprints/{$this->active->id}", ['start_date' => '2026-12-25', 'end_date' => '2027-01-05'])->assertUnprocessable();
    }
}
