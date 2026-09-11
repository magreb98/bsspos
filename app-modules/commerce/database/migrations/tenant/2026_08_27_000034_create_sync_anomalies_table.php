<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('sync_anomalies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('sales');
            $table->foreignUuid('product_id')->constrained('products');
            $table->string('type');
            $table->string('detail');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_anomalies');
    }
};
