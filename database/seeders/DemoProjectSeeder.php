<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\BacklogService;
use App\Services\StatusService;
use Illuminate\Database\Seeder;

/** Project contoh "Portal HR" agar halaman Project langsung berisi data (hanya lokal). */
class DemoProjectSeeder extends Seeder
{
    public function run(BacklogService $backlogs, StatusService $statuses): void
    {
        if (Project::where('code', 'PHR')->exists()) {
            return;
        }

        $u = fn (string $email) => User::where('email', $email)->firstOrFail();
        $andi = $u('andi.pratama@simpro.local');
        $budi = $u('budi.santoso@simpro.local');
        $citra = $u('citra.ayu@simpro.local');
        $dewi = $u('dewi.kartika@simpro.local');

        $start = today()->subWeeks(6)->startOfWeek();

        $project = Project::create([
            'code' => 'PHR',
            'name' => 'Portal HR',
            'description' => '<div>Aplikasi internal untuk mengelola data karyawan, pengajuan cuti, kehadiran, dan slip gaji.</div><ul><li>Fokus tahap ini: modul cuti dan integrasi login SSO</li></ul>',
            'start_date' => $start,
            'end_date' => $start->copy()->addWeeks(20),
            'status' => ProjectStatus::Active,
            'color' => Project::COLORS[0],
            'created_by' => $andi->id,
        ]);

        $project->members()->attach([
            $andi->id => ['project_role' => 'pm'],
            $budi->id => ['project_role' => 'programmer'],
            $citra->id => ['project_role' => 'programmer'],
            $dewi->id => ['project_role' => 'qa'],
        ]);

        $s1 = $project->sprints()->create(['name' => 'Sprint 1', 'goal' => 'Fondasi aplikasi dan autentikasi', 'start_date' => $start, 'end_date' => $start->copy()->addDays(13), 'status' => SprintStatus::Done, 'done_at' => $start->copy()->addDays(13)]);
        $s2 = $project->sprints()->create(['name' => 'Sprint 2', 'goal' => 'Modul cuti dan integrasi login SSO', 'start_date' => today()->subDays(6), 'end_date' => today()->addDays(8), 'status' => SprintStatus::Active]);
        $s3 = $project->sprints()->create(['name' => 'Sprint 3', 'goal' => 'Laporan kehadiran', 'start_date' => today()->addDays(9), 'end_date' => today()->addDays(23), 'status' => SprintStatus::Planned]);

        $rows = [
            [$s1, 'Halaman login dan manajemen sesi', 'Done', 'medium', $budi, $s1->start_date, $s1->start_date->copy()->addDays(5)],
            [$s1, 'Data master karyawan', 'Done', 'high', $citra, $s1->start_date->copy()->addDays(3), $s1->end_date],
            [$s2, 'Integrasi login SSO', 'Ready for QA', 'high', $budi, $s2->start_date, today()],
            [$s2, 'Form pengajuan cuti', 'In Progress', 'medium', $citra, $s2->start_date->copy()->addDay(), today()->addDays(5)],
            [$s2, 'Saldo cuti otomatis per tahun', 'To Do', 'high', $budi, today(), today()->addDays(7)],
            [$s2, 'Export laporan kehadiran (Excel)', 'Reopen', 'critical', $citra, $s2->start_date, today()->subDay()],
            [$s2, 'Notifikasi email slip gaji', 'Testing', 'medium', $budi, $s2->start_date->copy()->addDays(2), today()->addDays(2)],
            [$s3, 'Laporan kehadiran bulanan', 'To Do', 'medium', $citra, $s3->start_date, $s3->end_date],
            [null, 'Upload dokumen kontrak karyawan', 'To Do', 'low', null, null, null],
            [null, 'Integrasi mesin absensi fingerprint', 'To Do', 'high', null, null, null],
        ];

        $created = [];

        foreach ($rows as [$sprint, $title, $status, $priority, $programmer, $from, $to]) {
            $created[$title] = $backlogs->create($project, [
                'sprint_id' => $sprint?->id,
                'title' => $title,
                'status_id' => $statuses->resolve($project, $status)->id,
                'priority' => $priority,
                'programmer_id' => $programmer?->id,
                'qa_id' => $sprint ? $dewi->id : null,
                'start_date' => $from,
                'end_date' => $to,
            ], $andi);
        }

        // Dokumentasi contoh: Modul → Fitur, terhubung ke backlog.
        $auth = $project->docModules()->create(['name' => 'Autentikasi', 'sort_order' => 1]);
        $sso = $auth->features()->create([
            'title' => 'Login SSO (OAuth 2.0)',
            'content' => '<h2>Ringkasan</h2><div>Pengguna masuk memakai akun SSO kantor. Email dari SSO dicocokkan dengan user aplikasi yang aktif.</div><h2>Alur</h2><ol><li>Klik tombol "Masuk dengan SSO"</li><li>Login di halaman SSO kantor</li><li>Callback memetakan email ke user aplikasi</li></ol>',
            'sort_order' => 1,
            'updated_by' => $andi->id,
        ]);
        $sso->backlogs()->attach([$created['Integrasi login SSO']->id, $created['Halaman login dan manajemen sesi']->id]);

        $cuti = $project->docModules()->create(['name' => 'Cuti', 'sort_order' => 2]);
        $form = $cuti->features()->create([
            'title' => 'Pengajuan cuti',
            'content' => '<div>Karyawan mengajukan cuti; atasan menyetujui atau menolak. Saldo cuti berkurang otomatis setelah disetujui.</div>',
            'sort_order' => 1,
            'updated_by' => $budi->id,
        ]);
        $form->backlogs()->attach([$created['Form pengajuan cuti']->id, $created['Saldo cuti otomatis per tahun']->id]);
    }
}
