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
        Schema::table('users', function (Blueprint $table) {
            // The `sub` claim from 3AG Accounts. Opaque, stable, and the only
            // thing that reliably identifies the same person across a rename
            // or a change of email address.
            $table->string('oidc_sub')->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['oidc_sub']);
            $table->dropColumn('oidc_sub');
        });
    }
};
