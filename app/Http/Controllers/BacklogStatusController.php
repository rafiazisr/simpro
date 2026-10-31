<?php

namespace App\Http\Controllers;

use App\Enums\StatusCategory;
use App\Models\Backlog;
use App\Services\BacklogService;
use App\Services\StatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Ubah status backlog dari halaman detail.
 * - Programmer: "Selesai, kirim ke QA" (action=send_to_qa)
 * - QA: "Lulus (Done)" (action=pass) atau "Reopen" (action=reopen)
 * - PM/Admin: pilih status mana pun (status_name, boleh status baru)
 */
class BacklogStatusController extends Controller
{
    public function __invoke(Request $request, Backlog $backlog, BacklogService $backlogs, StatusService $statuses): RedirectResponse
    {
        Gate::authorize('updateStatus', $backlog);

        $user = $request->user();
        $canManage = $user->can('update', $backlog);
        $backlog->load('status');

        $request->validate([
            'action' => ['nullable', 'in:send_to_qa,start_testing,pass,reopen'],
            'status_name' => ['required_without:action', 'nullable', 'string', 'max:50'],
        ]);

        if ($action = $request->input('action')) {
            $isProgrammer = $backlog->programmer_id === $user->id;
            $isQa = $backlog->qa_id === $user->id;
            $category = $backlog->status->category;

            [$allowed, $target] = match ($action) {
                'send_to_qa' => [($isProgrammer || $canManage) && $category->isProgrammerStage(), 'Ready for QA'],
                'start_testing' => [($isQa || $canManage) && $category === StatusCategory::ReadyForQa, 'Testing'],
                'pass' => [($isQa || $canManage) && $category->isQaStage(), 'Done'],
                'reopen' => [($isQa || $canManage) && ($category->isQaStage() || $category === StatusCategory::Done), 'Reopen'],
            };

            abort_unless($allowed, 403, 'Aksi ini tidak tersedia untuk status backlog saat ini.');
            $status = $statuses->default($target);
        } else {
            // Status bebas hanya untuk yang berhak mengubah backlog; assignee memakai tombol aksi.
            abort_unless($canManage, 403);
            $status = $statuses->resolve($backlog->project, $request->input('status_name'));
        }

        if ($status->id === $backlog->status_id) {
            return back();
        }

        $backlogs->changeStatus($backlog, $status, $user);

        return back()->with('toast', "Status {$backlog->code} diubah menjadi {$status->name}.");
    }
}
