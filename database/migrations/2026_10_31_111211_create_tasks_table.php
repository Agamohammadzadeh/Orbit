<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->longText('description')->nullable();

            // مقادیر Enum به‌صورت رشته (قابل حمل بین MySQL و PostgreSQL)
            $table->string('status', 20)->default('todo');
            $table->string('priority', 20)->default('medium');
            $table->string('type', 20)->default('feature');

            $table->date('due_date')->nullable();
            $table->unsignedInteger('estimate_minutes')->nullable();

            // ترتیب در ستون کانبان؛ با فاصله‌ی ثابت (config('orbit.kanban.position_gap'))
            $table->unsignedBigInteger('position')->default(0);

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // کانبان یک پروژه
            $table->index(['project_id', 'status', 'position']);
            // «تسک‌های من»
            $table->index(['assignee_id', 'status']);
            // تقویم و تسک‌های عقب‌افتاده
            $table->index('due_date');
            $table->index('sprint_id');
            $table->index('phase_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
