<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('serial_units', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('serial_number');
            $table->string('status')->default('available');
            $table->uuid('sale_line_id')->nullable()->unique();
            $table->timestamps();

            $table->unique(['product_id', 'serial_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_units');
    }
};
