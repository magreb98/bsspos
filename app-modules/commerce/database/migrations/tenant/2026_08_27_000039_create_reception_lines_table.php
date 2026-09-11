<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('reception_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reception_id')->constrained('receptions')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->integer('quantity_expected');
            $table->integer('quantity_received');
            $table->bigInteger('unit_cost');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reception_lines');
    }
};
