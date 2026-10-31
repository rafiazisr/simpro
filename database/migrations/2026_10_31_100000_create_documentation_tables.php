<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'sort_order']);
        });

        Schema::create('doc_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_module_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->longText('content')->nullable(); // HTML yang sudah disaring
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['doc_module_id', 'sort_order']);
        });

        Schema::create('backlog_doc_feature', function (Blueprint $table) {
            $table->foreignId('doc_feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('backlog_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['doc_feature_id', 'backlog_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backlog_doc_feature');
        Schema::dropIfExists('doc_features');
        Schema::dropIfExists('doc_modules');
    }
};
