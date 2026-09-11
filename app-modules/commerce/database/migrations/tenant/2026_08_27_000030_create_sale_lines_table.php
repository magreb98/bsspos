<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('sale_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->string('designation');
            $table->bigInteger('unit_price');
            $table->decimal('vat_rate', 5, 2);
            $table->integer('quantity');
            $table->bigInteger('line_total_excluding_tax');
            $table->bigInteger('line_total_tax');
            $table->bigInteger('line_total_including_tax');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_lines');
    }
};
