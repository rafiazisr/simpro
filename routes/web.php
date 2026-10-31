<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BacklogController;
use App\Http\Controllers\BacklogStatusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocController;
use App\Http\Controllers\GanttController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/*
| Autentikasi (tamu)
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('lupa-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('lupa-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

/*
| Aplikasi (wajib login dan akun aktif)
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('cari', SearchController::class)->name('search');

    // Notifikasi
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/stream', [NotificationController::class, 'stream'])->name('notifications.stream');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{id}/open', [NotificationController::class, 'open'])->whereUuid('id')->name('notifications.open');

    // Project
    Route::resource('projects', ProjectController::class);
    Route::patch('projects/{project}/done', [ProjectController::class, 'markDone'])->name('projects.done');

    // Anggota project
    Route::get('projects/{project}/members', [ProjectMemberController::class, 'index'])->name('projects.members.index');
    Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
    Route::patch('projects/{project}/members/{user}', [ProjectMemberController::class, 'update'])->withTrashed()->name('projects.members.update');
    Route::delete('projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy'])->withTrashed()->name('projects.members.destroy');

    // Sprint
    Route::resource('projects.sprints', SprintController::class)->except('show');
    Route::patch('projects/{project}/sprints/{sprint}/done', [SprintController::class, 'markDone'])->name('projects.sprints.done');

    // Backlog (tab project) + detail backlog berdasarkan kode, mis. /backlogs/PHR-12
    Route::get('projects/{project}/backlogs', [BacklogController::class, 'index'])->name('projects.backlogs.index');
    Route::get('projects/{project}/backlogs/create', [BacklogController::class, 'create'])->name('projects.backlogs.create');
    Route::post('projects/{project}/backlogs', [BacklogController::class, 'store'])->name('projects.backlogs.store');
    Route::get('backlogs/{backlog}', [BacklogController::class, 'show'])->name('backlogs.show');
    Route::get('backlogs/{backlog}/edit', [BacklogController::class, 'edit'])->name('backlogs.edit');
    Route::put('backlogs/{backlog}', [BacklogController::class, 'update'])->name('backlogs.update');
    Route::delete('backlogs/{backlog}', [BacklogController::class, 'destroy'])->name('backlogs.destroy');
    Route::patch('backlogs/{backlog}/status', BacklogStatusController::class)->name('backlogs.status');

    // Swimlane Gantt Chart
    Route::get('timeline', [GanttController::class, 'index'])->name('gantt.index');
    Route::redirect('gantt', 'timeline', 301);
    Route::get('projects/{project}/timeline', [GanttController::class, 'project'])->name('projects.timeline');
    Route::get('gantt/data', [GanttController::class, 'data'])->name('gantt.data');
    Route::get('gantt/sprints/{sprint}/backlogs', [GanttController::class, 'sprintBacklogs'])->name('gantt.sprint-backlogs');
    Route::put('gantt/sprints/{sprint}', [GanttController::class, 'updateSprint'])->name('gantt.sprints.update');

    // Dokumentasi
    Route::get('dokumentasi', [DocController::class, 'index'])->name('docs.index');
    Route::post('dokumentasi/upload-image', [DocController::class, 'uploadImage'])->middleware('throttle:30,1')->name('docs.upload-image');
    Route::prefix('projects/{project}/docs')->name('projects.docs.')->group(function () {
        Route::get('/', [DocController::class, 'project'])->name('index');
        Route::post('modules', [DocController::class, 'storeModule'])->name('modules.store');
        Route::patch('modules/{module}', [DocController::class, 'updateModule'])->name('modules.update');
        Route::delete('modules/{module}', [DocController::class, 'destroyModule'])->name('modules.destroy');
        Route::patch('modules/{module}/move', [DocController::class, 'moveModule'])->name('modules.move');
        Route::get('modules/{module}/features/create', [DocController::class, 'create'])->name('features.create');
        Route::post('modules/{module}/features', [DocController::class, 'store'])->name('features.store');
        Route::get('{feature}', [DocController::class, 'show'])->whereNumber('feature')->name('show');
        Route::get('{feature}/edit', [DocController::class, 'edit'])->whereNumber('feature')->name('edit');
        Route::put('{feature}', [DocController::class, 'update'])->whereNumber('feature')->name('update');
        Route::delete('{feature}', [DocController::class, 'destroy'])->whereNumber('feature')->name('destroy');
        Route::patch('{feature}/move', [DocController::class, 'moveFeature'])->whereNumber('feature')->name('move');
        Route::post('{feature}/backlogs', [DocController::class, 'linkBacklogs'])->whereNumber('feature')->name('backlogs.link');
        Route::delete('{feature}/backlogs/{backlog}', [DocController::class, 'unlinkBacklog'])->whereNumber('feature')->name('backlogs.unlink');
    });

    // Administrasi
    Route::middleware('permission:user.manage')->group(function () {
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::resource('users', UserController::class)->except('show');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('roles/matrix', [RoleController::class, 'updateMatrix'])->name('roles.matrix');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
});
