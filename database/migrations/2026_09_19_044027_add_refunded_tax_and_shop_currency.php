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
        Schema::table('orders', function (Blueprint $table) {
            // How much of the refunded money was tax. WooCommerce reports it
            // per refund, and without it a refunded order keeps contributing
            // its full tax to a report long after the tax went back.
            $table->decimal('refunded_tax', 15, 4)->default(0)->after('refunded_total');
        });

        Schema::table('shops', function (Blueprint $table) {
            // The currency the store sells in, read from its own settings.
            // Totals from two currencies cannot be added together, so this is
            // what lets the shops page say so before a report tries.
            $table->string('currency', 3)->nullable()->after('platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('refunded_tax');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
