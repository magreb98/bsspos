<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('points_of_sale', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organizational_unit_id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('organizational_unit_id')
                ->references('id')
                ->on('organizational_units')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_of_sale');
    }
};
