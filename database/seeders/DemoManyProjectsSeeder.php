<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\SprintStatus;
use App\Enums\UserLevel;
use App\Models\Backlog;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Services\BacklogService;
use App\Services\StatusService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

/**
 * Data contoh berskala: beberapa project dengan kondisi berbeda (aktif, ditunda,
 * selesai), sprint yang mengikuti tanggal hari ini, dan backlog berstatus beragam.
 *
 * Aman dijalankan ulang di database yang sudah berisi data:
 *   php artisan db:seed --class=DemoManyProjectsSeeder
 * Project yang kodenya sudah ada dilewati; user dibuat bila belum ada.
 */
class DemoManyProjectsSeeder extends Seeder
{
    private const USERS = [
        // nama, email, level
        ['Eko Saputra', 'eko.saputra@simpro.local', UserLevel::Admin],
        ['Lukman Hakim', 'lukman.hakim@simpro.local', UserLevel::User],
        ['Gita Lestari', 'gita.lestari@simpro.local', UserLevel::User],
        ['Hendra Wijaya', 'hendra.wijaya@simpro.local', UserLevel::User],
        ['Joko Susilo', 'joko.susilo@simpro.local', UserLevel::User],
        ['Maya Anggraini', 'maya.anggraini@simpro.local', UserLevel::User],
        ['Indah Permata', 'indah.permata@simpro.local', UserLevel::User],
        ['Kartika Sari', 'kartika.sari@simpro.local', UserLevel::User],
    ];

    /**
     * Tiap project: kode, nama, deskripsi, status, mulai (minggu relatif hari ini),
     * durasi (minggu), PM, programmer, QA, modul dokumentasi, daftar judul backlog.
     */
    private function projects(): array
    {
        return [
            [
                'code' => 'SIA', 'name' => 'Sistem Informasi Akademik', 'status' => ProjectStatus::Active,
                'desc' => 'Portal akademik untuk mahasiswa dan dosen: KRS online, jadwal kuliah, input nilai, dan transkrip.',
                'start' => -10, 'weeks' => 24, 'pm' => 'eko.saputra', 'prog' => ['gita.lestari', 'hendra.wijaya', 'budi.santoso'], 'qa' => ['indah.permata'],
                'modules' => ['KRS Online', 'Penilaian'],
                'titles' => ['Login mahasiswa dan dosen', 'Data master mata kuliah', 'Jadwal kuliah per semester', 'Pengisian KRS online', 'Validasi batas SKS', 'Persetujuan KRS oleh dosen wali', 'Input nilai oleh dosen', 'Rekap nilai per kelas', 'Cetak KHS (PDF)', 'Transkrip nilai sementara', 'Kalender akademik', 'Notifikasi batas pengisian KRS', 'Import data mahasiswa (Excel)', 'Dashboard dosen wali', 'Riwayat perubahan nilai', 'Pengumuman akademik', 'Presensi kuliah via QR', 'Laporan IPK per angkatan'],
            ],
            [
                'code' => 'EPR', 'name' => 'E-Procurement', 'status' => ProjectStatus::Active,
                'desc' => 'Pengadaan barang dan jasa secara elektronik: pendaftaran vendor, tender, penawaran, hingga purchase order.',
                'start' => -6, 'weeks' => 18, 'pm' => 'lukman.hakim', 'prog' => ['joko.susilo', 'maya.anggraini'], 'qa' => ['kartika.sari'],
                'modules' => ['Vendor', 'Tender'],
                'titles' => ['Registrasi vendor', 'Verifikasi dokumen vendor', 'Katalog barang dan jasa', 'Pembuatan permintaan pembelian', 'Approval berjenjang', 'Pengumuman tender', 'Upload penawaran vendor', 'Evaluasi penawaran', 'Penetapan pemenang', 'Generate purchase order', 'Penerimaan barang', 'Rating kinerja vendor', 'Laporan pengadaan bulanan', 'Integrasi anggaran'],
            ],
            [
                'code' => 'CRM', 'name' => 'CRM Penjualan', 'status' => ProjectStatus::Active,
                'desc' => 'Pengelolaan prospek dan pelanggan untuk tim sales, termasuk pipeline dan target bulanan.',
                'start' => -14, 'weeks' => 16, 'pm' => 'andi.pratama', 'prog' => ['citra.ayu', 'gita.lestari'], 'qa' => ['dewi.kartika'],
                'modules' => ['Pipeline'],
                'titles' => ['Data pelanggan', 'Input prospek', 'Pipeline penjualan (kanban)', 'Riwayat interaksi pelanggan', 'Pengingat follow up', 'Target sales bulanan', 'Laporan konversi prospek', 'Import kontak dari CSV', 'Penawaran harga (quotation)', 'Dashboard manajer sales', 'Integrasi WhatsApp', 'Hak akses per wilayah'],
            ],
            [
                'code' => 'KSR', 'name' => 'Aplikasi Kasir Mobile', 'status' => ProjectStatus::Active,
                'desc' => 'Aplikasi kasir untuk tablet: transaksi cepat, stok sederhana, dan laporan harian untuk UMKM.',
                'start' => -1, 'weeks' => 14, 'pm' => 'eko.saputra', 'prog' => ['joko.susilo', 'hendra.wijaya'], 'qa' => ['kartika.sari'],
                'modules' => ['Transaksi'],
                'titles' => ['Setup project mobile', 'Login kasir dengan PIN', 'Daftar produk dan kategori', 'Keranjang dan pembayaran', 'Cetak struk Bluetooth', 'Diskon dan promo', 'Stok sederhana', 'Laporan penjualan harian', 'Sinkronisasi offline', 'Multi outlet'],
            ],
            [
                'code' => 'LMS', 'name' => 'Learning Management System', 'status' => ProjectStatus::Active,
                'desc' => 'Platform pelatihan internal: kelas, materi video, kuis, dan sertifikat.',
                'start' => -8, 'weeks' => 20, 'pm' => 'lukman.hakim', 'prog' => ['maya.anggraini', 'budi.santoso', 'citra.ayu'], 'qa' => ['indah.permata', 'dewi.kartika'],
                'modules' => ['Kelas', 'Kuis'],
                'titles' => ['Manajemen kelas', 'Upload materi video', 'Progres belajar peserta', 'Bank soal', 'Kuis pilihan ganda', 'Penilaian otomatis', 'Forum diskusi kelas', 'Sertifikat PDF', 'Leaderboard peserta', 'Laporan kelulusan', 'Enrol massal via Excel', 'Reminder deadline tugas', 'Rating kelas'],
            ],
            [
                'code' => 'HLP', 'name' => 'Helpdesk Ticketing', 'status' => ProjectStatus::Active,
                'desc' => 'Sistem tiket dukungan IT dengan SLA, eskalasi, dan basis pengetahuan.',
                'start' => -3, 'weeks' => 12, 'pm' => 'andi.pratama', 'prog' => ['hendra.wijaya', 'joko.susilo'], 'qa' => ['kartika.sari'],
                'modules' => ['Tiket'],
                'titles' => ['Buat tiket via form', 'Tiket via email masuk', 'Penugasan teknisi', 'SLA dan eskalasi otomatis', 'Status tiket realtime', 'Basis pengetahuan', 'Survei kepuasan', 'Laporan SLA bulanan', 'Lampiran tangkapan layar'],
            ],
            [
                'code' => 'INV', 'name' => 'Inventaris Gudang', 'status' => ProjectStatus::OnHold,
                'desc' => 'Pencatatan barang masuk/keluar dan stok opname. Ditunda menunggu keputusan anggaran.',
                'start' => -12, 'weeks' => 16, 'pm' => 'eko.saputra', 'prog' => ['gita.lestari'], 'qa' => ['indah.permata'],
                'modules' => ['Stok'],
                'titles' => ['Data master barang', 'Barang masuk', 'Barang keluar', 'Kartu stok', 'Stok opname', 'Label barcode', 'Laporan mutasi stok', 'Peringatan stok minimum'],
            ],
            [
                'code' => 'WEB', 'name' => 'Website Company Profile', 'status' => ProjectStatus::Done,
                'desc' => 'Website profil perusahaan dengan CMS sederhana untuk berita dan lowongan kerja.',
                'start' => -16, 'weeks' => 8, 'pm' => 'lukman.hakim', 'prog' => ['maya.anggraini'], 'qa' => ['dewi.kartika'],
                'modules' => ['CMS'],
                'titles' => ['Desain halaman utama', 'Halaman tentang kami', 'CMS berita', 'Halaman lowongan kerja', 'Form kontak', 'SEO dasar', 'Optimasi kecepatan', 'Deploy ke hosting'],
            ],
        ];
    }

    public function run(BacklogService $backlogs, StatusService $statuses): void
    {
        // Hindari banjir notifikasi "di-assign" selama seeding.
        Notification::fake();

        foreach (self::USERS as [$name, $email, $level]) {
            User::query()->firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => 'password', 'user_level' => $level,
                'is_active' => true, 'email_verified_at' => now(),
            ]);
        }

        $user = fn (string $handle) => User::where('email', $handle.'@simpro.local')->first();
        $usedColors = Project::pluck('color')->all();

        foreach ($this->projects() as $i => $def) {
            if (Project::where('code', $def['code'])->exists()) {
                continue;
            }

            mt_srand(crc32($def['code'])); // hasil acak yang sama setiap kali dijalankan

            $pm = $user($def['pm']);
            $programmers = collect($def['prog'])->map($user)->filter()->values();
            $qas = collect($def['qa'])->map($user)->filter()->values();
            if (! $pm || $programmers->isEmpty() || $qas->isEmpty()) {
                $this->command?->warn("Lewati {$def['code']}: user demo belum ada (jalankan DemoUserSeeder dulu).");

                continue;
            }

            $start = today()->addWeeks($def['start'])->startOfWeek();
            $end = $start->copy()->addWeeks($def['weeks'])->subDay();
            $color = collect(Project::COLORS)->first(fn ($c) => ! in_array($c, $usedColors, true)) ?? Project::COLORS[$i % count(Project::COLORS)];
            $usedColors[] = $color;

            $project = Project::create([
                'code' => $def['code'], 'name' => $def['name'], 'description' => '<div>'.e($def['desc']).'</div>',
                'start_date' => $start, 'end_date' => $end, 'status' => $def['status'],
                'color' => $color, 'created_by' => $pm->id,
            ]);

            $project->members()->attach(
                [$pm->id => ['project_role' => 'pm']]
                + $programmers->mapWithKeys(fn ($u) => [$u->id => ['project_role' => 'programmer']])->all()
                + $qas->mapWithKeys(fn ($u) => [$u->id => ['project_role' => 'qa']])->all()
            );

            $sprints = $this->makeSprints($project, $def['status']);
            $created = $this->makeBacklogs($project, $def, $sprints, $programmers, $qas, $pm, $backlogs, $statuses);
            $this->makeDocs($project, $def['modules'], $created, $pm);
        }
    }

    /** Sprint 2 mingguan sepanjang project; status mengikuti tanggal hari ini. */
    private function makeSprints(Project $project, ProjectStatus $status): array
    {
        $sprints = [];
        $cursor = $project->start_date->copy();
        $n = 1;

        while ($cursor->lte($project->end_date)) {
            $sEnd = $cursor->copy()->addDays(13)->min($project->end_date);

            $sprintStatus = match (true) {
                $status === ProjectStatus::Done || $sEnd->lt(today()) => SprintStatus::Done,
                $status === ProjectStatus::OnHold => SprintStatus::Planned,
                $cursor->lte(today()) => SprintStatus::Active,
                default => SprintStatus::Planned,
            };

            $sprints[] = $project->sprints()->create([
                'name' => 'Sprint '.$n,
                'goal' => null,
                'start_date' => $cursor, 'end_date' => $sEnd,
                'status' => $sprintStatus,
                'done_at' => $sprintStatus === SprintStatus::Done ? $sEnd : null,
            ]);

            $cursor = $sEnd->copy()->addDay();
            $n++;
        }

        // Project berjalan biasanya hanya merencanakan 2 sprint ke depan.
        $planned = collect($sprints)->filter(fn (Sprint $s) => $s->status === SprintStatus::Planned)->values();
        $planned->slice(2)->each->delete();

        return collect($sprints)->reject(fn (Sprint $s) => ! $s->exists)->values()->all();
    }

    private function makeBacklogs(Project $project, array $def, array $sprints, $programmers, $qas, User $pm, BacklogService $backlogs, StatusService $statuses): array
    {
        $titles = $def['titles'];
        $priorities = ['low', 'medium', 'medium', 'medium', 'high', 'high', 'critical'];
        $created = [];

        // Project selesai: semua backlog masuk sprint. Lainnya: sisakan 2 di product backlog.
        $reserve = $def['status'] === ProjectStatus::Done ? 0 : min(2, count($titles) - count($sprints));
        $inSprints = count($titles) - $reserve;

        // Bagi ke semua sprint agar progres sebanding dengan waktu yang berjalan;
        // sprint aktif diberi bobot dua kali karena biasanya paling ramai.
        $weights = array_map(fn (Sprint $s) => $s->status === SprintStatus::Active ? 2 : 1, $sprints);
        $counts = $this->distribute($inSprints, $weights);
        $t = 0;

        foreach ($sprints as $i => $sprint) {
            $count = $counts[$i];
            // Sisa pekerjaan (carry-over) hanya wajar dari 2 sprint terakhir yang selesai.
            $recentDone = $sprint->status === SprintStatus::Done && $sprint->end_date->gte(today()->subWeeks(4));
            for ($k = 0; $k < $count; $k++, $t++) {
                [$from, $to] = $this->dates($sprint);
                $status = $this->statusFor($sprint, $to, $def['status'], $recentDone);
                $created[] = $this->createBacklog($project, $titles[$t], $status, $priorities[mt_rand(0, 6)], $sprint, $from, $to, $programmers, $qas, $pm, $backlogs, $statuses);
            }
        }

        for (; $t < count($titles); $t++) {
            $created[] = $this->createBacklog($project, $titles[$t], 'To Do', $priorities[mt_rand(0, 6)], null, null, null, $programmers, $qas, $pm, $backlogs, $statuses, assign: mt_rand(0, 1) === 1);
        }

        return $created;
    }

    /** Bagi $total ke beberapa wadah sesuai bobot (metode sisa terbesar). */
    private function distribute(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        $raw = array_map(fn ($w) => $total * $w / $sum, $weights);
        $counts = array_map('intval', array_map('floor', $raw));
        $left = $total - array_sum($counts);
        $order = array_keys($raw);
        usort($order, fn ($a, $b) => ($raw[$b] - floor($raw[$b])) <=> ($raw[$a] - floor($raw[$a])));
        foreach (array_slice($order, 0, $left) as $i) {
            $counts[$i]++;
        }

        return $counts;
    }

    private function createBacklog(Project $project, string $title, string $status, string $priority, ?Sprint $sprint, $from, $to, $programmers, $qas, User $pm, BacklogService $backlogs, StatusService $statuses, bool $assign = true): Backlog
    {
        $backlog = $backlogs->create($project, [
            'sprint_id' => $sprint?->id,
            'title' => $title,
            'status_id' => $statuses->resolve($project, $status)->id,
            'priority' => $priority,
            'programmer_id' => $assign ? $programmers[mt_rand(0, $programmers->count() - 1)]->id : null,
            'qa_id' => $sprint ? $qas[mt_rand(0, $qas->count() - 1)]->id : null,
            'start_date' => $from,
            'end_date' => $to,
        ], $pm);

        // Waktu dibuat dimundurkan ke sekitar awal sprint (atau awal project),
        // agar "Aktivitas terbaru" tidak semuanya "beberapa detik yang lalu".
        $createdAt = ($from ?? $project->start_date)->copy()->subDays(mt_rand(1, 4))->setTime(mt_rand(8, 16), mt_rand(0, 59));
        if ($createdAt->gt(now())) {
            $createdAt = now()->subHours(mt_rand(1, 48));
        }
        $backlog->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        $backlog->activities()->getQuery()->toBase()->update(['created_at' => $createdAt]);

        // completed_at diisi "sekarang" oleh service; untuk data lampau pakai tanggal selesainya.
        if ($backlog->completed_at && $to && $to->lt(today())) {
            $backlog->forceFill(['completed_at' => $to->copy()->setTime(16, 0)])->saveQuietly();
        }

        return $backlog;
    }

    /** Rentang tanggal backlog di dalam sprint. */
    private function dates(Sprint $sprint): array
    {
        $span = $sprint->start_date->diffInDays($sprint->end_date);
        $from = $sprint->start_date->copy()->addDays(mt_rand(0, (int) max(0, $span - 3)));
        $to = $from->copy()->addDays(mt_rand(2, 6))->min($sprint->end_date);

        return [$from, $to];
    }

    /**
     * Status realistis: sprint selesai -> umumnya Done (sesekali ada sisa pekerjaan
     * yang terbawa dan jadi terlambat); sprint aktif -> campuran; rencana -> To Do.
     */
    private function statusFor(Sprint $sprint, CarbonInterface $due, ProjectStatus $projectStatus, bool $allowCarryOver = false): string
    {
        if ($sprint->status === SprintStatus::Done) {
            if (! $allowCarryOver || $projectStatus !== ProjectStatus::Active) {
                return 'Done';
            }

            return mt_rand(1, 100) <= 75 ? 'Done' : ['In Progress', 'Reopen', 'Testing'][mt_rand(0, 2)];
        }
        if ($sprint->status === SprintStatus::Planned) {
            return 'To Do';
        }

        // Sprint aktif: yang tenggatnya sudah lewat lebih mungkin selesai, sebagian terlambat.
        $pool = $due->lt(today())
            ? ['Done', 'Done', 'Testing', 'Ready for QA', 'In Progress', 'Reopen']
            : ['To Do', 'In Progress', 'In Progress', 'Ready for QA', 'Testing', 'Done'];

        return $pool[mt_rand(0, count($pool) - 1)];
    }

    private function makeDocs(Project $project, array $modules, array $backlogs, User $pm): void
    {
        foreach ($modules as $m => $name) {
            $module = $project->docModules()->create(['name' => $name, 'sort_order' => $m + 1]);
            $feature = $module->features()->create([
                'title' => 'Alur '.mb_strtolower($name),
                'content' => '<h2>Ringkasan</h2><div>Dokumentasi alur '.e(mb_strtolower($name)).' pada '.e($project->name).'.</div><h2>Catatan</h2><ul><li>Diperbarui setiap akhir sprint.</li></ul>',
                'sort_order' => 1,
                'updated_by' => $pm->id,
            ]);
            $linked = collect($backlogs)->slice($m * 3, 3)->pluck('id')->all();
            $feature->backlogs()->attach($linked);
        }
    }
}
