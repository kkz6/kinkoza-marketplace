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
        Schema::create('carts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('sequence')->unique();
            $table->foreignUlid('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->ulid('guest_token')->nullable()->index();
            $table->string('active_key', 32)->nullable()->unique();
            $table->char('currency', 3);
            $table->string('status')->default('active');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });
    }
};
