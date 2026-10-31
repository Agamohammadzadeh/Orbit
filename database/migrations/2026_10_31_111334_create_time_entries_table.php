<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('minutes');
            $table->date('worked_on');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index('task_id');
            $table->index(['user_id', 'worked_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
