<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->longText('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('active')->index(); // active | on_hold | done
            $table->string('color', 7)->default('#3B82F6');
            $table->timestamp('done_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Nama role spatie dengan scope "project": pm, programmer, qa, atau role kustom.
            $table->string('project_role', 50);
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('goal')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('planned'); // planned | active | done
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'start_date']);
        });

        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            // null = status bawaan yang berlaku untuk semua project.
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('category', 20); // todo | in_progress | ready_for_qa | testing | done
            $table->string('style', 20)->nullable(); // kunci warna desain (todo, progress, readyqa, testing, done, reopen)
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });

        Schema::create('backlogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('code', 20)->unique();
            $table->string('title');
            $table->longText('detail')->nullable();
            $table->foreignId('status_id')->constrained('statuses');
            $table->string('priority', 10)->default('medium'); // low | medium | high | critical
            $table->foreignId('programmer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('qa_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'number']);
            $table->index(['project_id', 'sprint_id']);
            $table->index('status_id');
            $table->index('programmer_id');
            $table->index('qa_id');
        });

        Schema::create('backlog_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backlog_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field', 30); // created | status | priority | programmer | qa | sprint | start_date | end_date | title
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['backlog_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backlog_activities');
        Schema::dropIfExists('backlogs');
        Schema::dropIfExists('statuses');
        Schema::dropIfExists('sprints');
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
    }
};
