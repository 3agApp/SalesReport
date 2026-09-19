<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('connection_status')->default('unknown')->index();
            $table->text('connection_message')->nullable();
            $table->timestamp('connection_checked_at')->nullable()->index();
            $table->unsignedInteger('connection_response_time_ms')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'connection_status',
                'connection_message',
                'connection_checked_at',
                'connection_response_time_ms',
            ]);
        });
    }
};
