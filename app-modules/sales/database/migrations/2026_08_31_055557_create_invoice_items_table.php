<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('sequence')->unique();
            $table->foreignUlid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->ulid('listing_id')->nullable();
            $table->string('title');
            $table->char('currency', 3);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
        });
    }
};
