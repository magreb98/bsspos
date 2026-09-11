<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('inventory_counts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('point_of_sale_id')->constrained('points_of_sale');
            $table->timestamp('counted_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_counts');
    }
};
