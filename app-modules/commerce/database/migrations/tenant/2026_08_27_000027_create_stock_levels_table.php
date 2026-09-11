<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('point_of_sale_id');
            $table->uuid('product_id')->nullable();
            $table->uuid('product_variant_id')->nullable();
            $table->bigInteger('quantity')->default(0);
            $table->timestamps();

            $table->foreign('point_of_sale_id')
                ->references('id')
                ->on('points_of_sale')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->cascadeOnDelete();

            $table->unique(['point_of_sale_id', 'product_id']);
            $table->unique(['point_of_sale_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
