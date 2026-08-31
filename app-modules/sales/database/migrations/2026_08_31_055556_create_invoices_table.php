<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('sequence')->unique();
            $table->string('number', 12)->unique();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('status', ['issued', 'paid', 'void'])->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('total_minor');
            $table->timestamp('issued_at')->index();
            $table->timestamps();

            $table->unique('order_id');
        });
    }
};
