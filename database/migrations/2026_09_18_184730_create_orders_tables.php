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
        Schema::create('shop_sync_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            // Where the historical import has got to, and when it finished.
            $table->timestamp('backfill_cursor')->nullable();
            $table->timestamp('backfill_completed_at')->nullable();
            // The high-water mark for the incremental pass.
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamp('last_finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('woo_id');
            $table->string('number')->nullable();
            $table->string('status')->index();
            $table->string('currency', 3);

            // Money is stored as decimals rather than floats: these numbers end
            // up in a bookkeeper's report and must add up exactly.
            $table->decimal('total', 15, 4)->default(0);
            $table->decimal('total_tax', 15, 4)->default(0);
            $table->decimal('shipping_total', 15, 4)->default(0);
            $table->decimal('shipping_tax', 15, 4)->default(0);
            $table->decimal('cart_tax', 15, 4)->default(0);
            $table->decimal('discount_total', 15, 4)->default(0);
            $table->decimal('discount_tax', 15, 4)->default(0);
            $table->decimal('refunded_total', 15, 4)->default(0);

            $table->unsignedBigInteger('customer_woo_id')->nullable();
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('billing_country', 2)->nullable();
            $table->string('payment_method_title')->nullable();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('woo_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'woo_id']);
            // Every report starts as "this shop, between these two dates".
            $table->index(['shop_id', 'placed_at']);
            $table->index(['shop_id', 'status', 'placed_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('woo_id');
            $table->string('name');
            $table->string('sku')->nullable()->index();
            $table->unsignedBigInteger('product_woo_id')->nullable();
            $table->unsignedBigInteger('variation_woo_id')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('subtotal_tax', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->decimal('total_tax', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['order_id', 'woo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('shop_sync_states');
    }
};
