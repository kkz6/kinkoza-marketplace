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
        Schema::create('listings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('sequence')->unique();
            $table->foreignUlid('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('category');
            $table->string('status');
            $table->char('currency', 3);
            $table->unsignedBigInteger('price_minor');
            $table->char('country', 2);
            $table->string('city');
            $table->timestamp('online_at');
            $table->timestamp('offline_at')->nullable();
            $table->unsignedInteger('inventory_quantity')->default(1);
            $table->string('image_url')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(
                ['status', 'online_at', 'offline_at'],
                'listings_public_window_index',
            );
            $table->index(
                ['status', 'category', 'price_minor'],
                'listings_category_price_index',
            );
            $table->index(
                ['status', 'country', 'price_minor'],
                'listings_country_price_index',
            );
            $table->index(
                ['seller_id', 'status', 'updated_at'],
                'listings_seller_status_index',
            );
        });
    }
};
