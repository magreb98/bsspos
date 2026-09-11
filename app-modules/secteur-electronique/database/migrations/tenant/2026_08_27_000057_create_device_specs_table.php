<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('device_specs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products');
            $table->unique('product_id');

            $table->string('category', 30);
            $table->string('brand', 100);
            $table->string('model', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->smallInteger('model_year')->nullable();
            $table->boolean('imei_required')->default(false);
            $table->smallInteger('warranty_months')->default(12);

            // Screen
            $table->decimal('screen_size_inches', 4, 1)->nullable();
            $table->string('screen_resolution', 20)->nullable();
            $table->string('panel_type', 30)->nullable();

            // Processor / Memory
            $table->string('processor', 100)->nullable();
            $table->smallInteger('ram_gb')->nullable();
            $table->integer('storage_gb')->nullable();

            // Connectivity
            $table->boolean('bluetooth')->nullable();
            $table->boolean('wifi')->nullable();
            $table->boolean('nfc')->nullable();
            $table->string('cellular_network', 10)->nullable();

            // Battery
            $table->integer('battery_mah')->nullable();

            // Camera
            $table->smallInteger('main_camera_mp')->nullable();

            // Audio output
            $table->smallInteger('power_watts')->nullable();

            // Operating system
            $table->string('operating_system', 60)->nullable();

            // Physical
            $table->smallInteger('weight_grams')->nullable();

            // Catch-all for category-specific attributes
            $table->jsonb('additional_specs')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_specs');
    }
};
