<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('catalog_projections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->unique();
            $table->string('reference');
            $table->string('label');
            $table->bigInteger('selling_price');
            $table->string('availability')->default('available');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_projections');
    }
};
