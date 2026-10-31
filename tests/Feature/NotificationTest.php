<?php

namespace Tests\Feature;

use App\Models\Backlog;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Notifications\BacklogAssigned;
use App\Notifications\BacklogReadyForQa;
use App\Notifications\ProjectAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private User $prog;

    private User $qa;

    private Project $project;

    private Sprint $sprint;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->pm = User::factory()->create();
        $this->prog = User::factory()->create(['name' => 'Budi Santoso']);
        $this->qa = User::factory()->create(['name' => 'Dewi Kartika']);
        $this->project = $this->makeProject(['pm' => $this->pm, 'programmer' => $this->prog, 'qa' => $this->qa], ['code' => 'PHR', 'name' => 'Portal HR']);
        $this->sprint = $this->project->sprints()->create(['name' => 'Sprint 1', 'start_date' => '2026-03-01', 'end_date' => '2026-03-21', 'status' => 'active']);
    }

    private function createBacklog(string $status = 'To Do'): Backlog
    {
        $this->actingAs($this->pm)->post('/projects/PHR/backlogs', [
            'title' => 'Integrasi login SSO', 'sprint_id' => $this->sprint->id, 'status_name' => $status,
            'priority' => 'high', 'programmer_id' => $this->prog->id, 'qa_id' => $this->qa->id,
        ])->assertSessionHasNoErrors();

        return Backlog::latest('id')->firstOrFail();
    }

    public function test_tambah_anggota_mengirim_notifikasi_project(): void
    {
        $citra = User::factory()->create();

        $this->actingAs($this->pm)->post('/projects/PHR/members', ['user_id' => $citra->id, 'role' => 'programmer']);

        $n = $citra->notifications()->first();
        $this->assertSame(ProjectAssigned::class, $n->type);
        $this->assertSame('Anda ditambahkan ke project Portal HR sebagai Programmer', $n->data['text']);
        $this->assertSame(0, $this->pm->notifications()->count(), 'pelaku tidak menerima notifikasi');
    }

    public function test_buat_project_memberi_tahu_anggota_kecuali_pembuat(): void
    {
        $admin = User::factory()->admin()->create();
        $budi = User::factory()->create();

        $this->actingAs($admin)->post('/projects', [
            'code' => 'KSR', 'name' => 'Aplikasi Kasir', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'status' => 'active',
            'members' => [['user_id' => $admin->id, 'role' => 'pm'], ['user_id' => $budi->id, 'role' => 'qa']],
        ])->assertRedirect();

        $this->assertSame(1, $budi->notifications()->count());
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_assign_backlog_memberi_tahu_programmer_dan_qa_sekali(): void
    {
        $b = $this->createBacklog();

        $this->assertSame(1, $this->prog->notifications()->where('type', BacklogAssigned::class)->count());
        $this->assertSame(1, $this->qa->notifications()->where('type', BacklogAssigned::class)->count());
        $this->assertStringContainsString('PHR-1 Integrasi login SSO', $this->prog->notifications()->first()->data['text']);

        // Simpan ulang tanpa mengganti assignee: tidak ada notifikasi baru.
        $this->actingAs($this->pm)->put("/backlogs/{$b->code}", [
            'title' => 'Judul baru', 'sprint_id' => (string) $this->sprint->id, 'status_name' => 'To Do', 'priority' => 'high',
            'programmer_id' => (string) $this->prog->id, 'qa_id' => (string) $this->qa->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->prog->notifications()->count());
    }

    public function test_ready_for_qa_hanya_ke_qa_backlog(): void
    {
        $b = $this->createBacklog('In Progress');
        $this->qa->notifications()->delete();

        $this->actingAs($this->prog)->patch("/backlogs/{$b->code}/status", ['action' => 'send_to_qa'])->assertRedirect();

        $n = $this->qa->notifications()->first();
        $this->assertSame(BacklogReadyForQa::class, $n->type);
        $this->assertSame('PHR-1 siap dites oleh QA (diselesaikan oleh Budi Santoso)', $n->data['text']);
        $this->assertSame(0, $this->pm->notifications()->count());
        $this->assertSame(1, $this->prog->notifications()->count(), 'hanya notifikasi assign sebelumnya');
    }

    public function test_lonceng_halaman_dan_tandai_dibaca(): void
    {
        $this->createBacklog();

        $this->actingAs($this->qa)->get('/dashboard')->assertOk()->assertSee('notificationBell', false);
        $this->actingAs($this->qa)->get('/notifications')->assertOk()->assertSee('<b>PHR-1 Integrasi login SSO</b>', false);
        $this->actingAs($this->qa)->get('/notifications?filter=unread')->assertOk()->assertSee('PHR-1');

        $id = $this->qa->notifications()->first()->id;
        $this->actingAs($this->qa)->get("/notifications/{$id}/open")->assertRedirect('/backlogs/PHR-1');
        $this->assertNotNull($this->qa->notifications()->first()->read_at);

        // Notifikasi milik orang lain tidak bisa dibuka.
        $otherId = $this->prog->notifications()->first()->id;
        $this->actingAs($this->qa)->get("/notifications/{$otherId}/open")->assertNotFound();

        $this->actingAs($this->prog)->postJson('/notifications/read-all')->assertOk()->assertJson(['unread' => 0]);
        $this->assertSame(0, $this->prog->unreadNotifications()->count());
    }

    public function test_teks_notifikasi_di_escape(): void
    {
        $this->actingAs($this->pm)->post('/projects/PHR/backlogs', [
            'title' => '<img src=x onerror=alert(1)>', 'sprint_id' => $this->sprint->id, 'status_name' => 'To Do',
            'priority' => 'low', 'programmer_id' => $this->prog->id, 'qa_id' => $this->qa->id,
        ]);

        $this->actingAs($this->prog)->get('/notifications')->assertOk()
            ->assertDontSee('<img src=x', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
    }

    public function test_stream_sse_mengirim_notifikasi_baru(): void
    {
        $since = now()->subMinute()->toIso8601String();
        $this->createBacklog();

        $res = $this->actingAs($this->qa)->get('/notifications/stream?since='.urlencode($since));
        $res->assertOk();
        $this->assertStringStartsWith('text/event-stream', $res->headers->get('Content-Type'));

        $body = $res->streamedContent();
        $this->assertStringContainsString('retry: 3000', $body);
        $this->assertStringContainsString('event: notification', $body);
        $this->assertStringContainsString('PHR-1 Integrasi login SSO', $body);
        $this->assertStringContainsString("event: unread\ndata: 1", $body);

        // Tanpa notifikasi baru sejak "since": hanya jumlah belum dibaca.
        $later = $this->actingAs($this->qa)->get('/notifications/stream?since='.urlencode(now()->addMinute()->toIso8601String()))->streamedContent();
        $this->assertStringNotContainsString('event: notification', $later);
    }
}
