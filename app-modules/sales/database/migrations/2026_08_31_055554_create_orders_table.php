<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('sequence')->unique();
            $table->string('number', 12)->unique();
            $table->foreignUlid('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('cart_id')->constrained('carts')->restrictOnDelete();
            $table->string('idempotency_key', 64);
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('total_minor');
            $table->timestamp('placed_at')->index();
            $table->timestamps();

            $table->unique('cart_id');
            $table->unique(['buyer_id', 'idempotency_key']);
        });
    }
};
