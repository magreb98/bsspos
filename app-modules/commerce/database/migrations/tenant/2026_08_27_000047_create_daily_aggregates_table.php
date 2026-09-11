<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('daily_aggregates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('point_of_sale_id');
            $table->date('date');
            $table->unsignedInteger('sale_count')->default(0);
            $table->bigInteger('total_excluding_tax')->default(0);
            $table->bigInteger('total_tax')->default(0);
            $table->bigInteger('total_including_tax')->default(0);
            $table->boolean('is_provisional')->default(true);
            $table->timestampTz('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['point_of_sale_id', 'date']);

            $table->foreign('point_of_sale_id')
                ->references('id')
                ->on('points_of_sale')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_aggregates');
    }
};
