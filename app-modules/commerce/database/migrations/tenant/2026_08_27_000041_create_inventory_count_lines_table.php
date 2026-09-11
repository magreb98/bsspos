<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('inventory_count_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_count_id')->constrained('inventory_counts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->integer('theoretical_quantity');
            $table->integer('counted_quantity');
            $table->integer('adjustment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_lines');
    }
};
