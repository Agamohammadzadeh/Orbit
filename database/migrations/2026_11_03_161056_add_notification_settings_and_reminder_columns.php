<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // {"email_enabled": true, "app": {"mention": true, ...}, "email": {...}}؛ خالی = پیش‌فرض‌ها
            $table->json('notification_settings')->nullable()->after('last_seen_at');
        });

        Schema::table('tasks', function (Blueprint $table) {
            // ددلاینی که برایش یادآوری فرستاده شده؛ با عوض شدن ددلاین، یادآوری دوباره مجاز می‌شود
            $table->date('deadline_reminded_for')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notification_settings'));
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('deadline_reminded_for'));
    }
};
