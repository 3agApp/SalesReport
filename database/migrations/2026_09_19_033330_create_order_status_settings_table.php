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
        Schema::create('order_status_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // The WooCommerce status slug, exactly as the orders carry it. A
            // store can register whatever it likes, so this is never an enum.
            $table->string('status');

            // What the organization calls it. A slug like "planzer-transmit"
            // means something to the shop that invented it and nothing to a
            // bookkeeper, so they get to say what it is.
            $table->string('label')->nullable();

            // Whether an order in this status is money the organization has
            // taken. Nothing is assumed: an unanswered status is not counted.
            $table->boolean('counts_as_revenue')->default(false);

            $table->timestamps();

            $table->unique(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_settings');
    }
};
