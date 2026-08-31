<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('sequence')->unique();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->foreignUlid('seller_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->char('currency', 3);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();

            $table->index('order_id');
            $table->index('listing_id');
            $table->index('seller_id');
        });
    }
};
