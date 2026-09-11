<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('customer_credits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers');
            $table->foreignUuid('return_sale_id')->nullable()->constrained('sales');
            $table->bigInteger('original_amount');
            $table->bigInteger('remaining_amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credits');
    }
};
