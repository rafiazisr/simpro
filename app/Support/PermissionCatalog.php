<?php

namespace App\Support;

/**
 * Daftar permission aplikasi beserta default per role.
 * Urutan dan pengelompokan mengikuti layar "Role & Hak Akses" di desain.
 */
class PermissionCatalog
{
    /** Role bawaan: name => [label, scope]. Urutan = urutan kolom di matriks. */
    public const SYSTEM_ROLES = [
        'super_admin' => ['Super Admin', 'level'],
        'admin' => ['Admin', 'level'],
        'user' => ['User', 'level'],
        'pm' => ['Project Manager', 'project'],
        'programmer' => ['Programmer', 'project'],
        'qa' => ['QA', 'project'],
    ];

    /**
     * Grup => [permission => [label, role default yang memilikinya]].
     * Super Admin selalu punya semua permission lewat Gate::before.
     */
    public const GROUPS = [
        'User' => [
            'user.manage' => ['Kelola user', ['admin']],
        ],
        'Project' => [
            'project.create' => ['Buat project', ['admin']],
            'project.update' => ['Ubah project', ['admin', 'pm']],
            'project.delete' => ['Hapus project', ['admin']],
            'project.assign-member' => ['Assign anggota', ['admin', 'pm']],
            'project.mark-done' => ['Tandai project selesai', ['admin', 'pm']],
        ],
        'Sprint' => [
            'sprint.manage' => ['Buat / ubah / hapus sprint', ['admin', 'pm']],
            'sprint.view' => ['Lihat sprint', ['admin', 'user', 'pm', 'programmer', 'qa']],
            'sprint.mark-done' => ['Tandai sprint selesai', ['admin', 'pm']],
        ],
        'Backlog' => [
            'backlog.create' => ['Buat backlog', ['admin', 'pm']],
            'backlog.update-delete' => ['Ubah / hapus backlog', ['admin', 'pm']],
            'backlog.update-status' => ['Ubah status backlog', ['admin', 'pm', 'programmer', 'qa']],
        ],
        'Timeline' => [
            'timeline.manage' => ['Atur tanggal (drag di Gantt)', ['admin', 'pm']],
        ],
        'Prioritas' => [
            'priority.manage' => ['Atur prioritas', ['admin', 'pm']],
        ],
        'Dokumentasi' => [
            'documentation.create-update' => ['Buat / ubah dokumentasi', ['admin', 'pm', 'programmer']],
            'documentation.delete' => ['Hapus dokumentasi', ['admin', 'pm']],
        ],
        'Dashboard' => [
            'dashboard.view-all' => ['Lihat dashboard semua user', ['admin']],
        ],
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return collect(self::GROUPS)->flatMap(fn (array $perms) => array_keys($perms))->values()->all();
    }

    /** @return array<string, list<string>> role => permission default */
    public static function defaultsByRole(): array
    {
        $map = array_fill_keys(array_keys(self::SYSTEM_ROLES), []);

        foreach (self::GROUPS as $perms) {
            foreach ($perms as $name => [, $roles]) {
                foreach ($roles as $role) {
                    $map[$role][] = $name;
                }
            }
        }

        return $map;
    }
}
