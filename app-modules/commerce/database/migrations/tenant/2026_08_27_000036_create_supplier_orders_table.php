<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('supplier_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers');
            $table->string('status');
            $table->timestamp('ordered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_orders');
    }
};
