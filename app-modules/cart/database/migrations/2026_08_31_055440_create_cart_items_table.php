<?php

declare(strict_types=1);

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
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('sequence')->unique();
            $table->foreignUlid('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignUlid('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->string('sku');
            $table->string('title');
            $table->char('currency', 3);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['cart_id', 'listing_id']);
        });
    }
};
