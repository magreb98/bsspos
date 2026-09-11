<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('perimeters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('name', 255);
            $table->string('type', 50)->default('store');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('parent_id');

            $table->foreign('parent_id')
                ->references('id')
                ->on('perimeters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perimeters');
    }
};
