<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->string('label');
            $table->uuid('family_id');
            $table->bigInteger('selling_price');
            $table->decimal('vat_rate', 5, 2)->default(19.25);
            $table->string('granularity');
            $table->json('attributes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('family_id')
                ->references('id')
                ->on('families')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
