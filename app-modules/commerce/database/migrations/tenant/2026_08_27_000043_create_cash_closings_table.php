<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('cash_closings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cash_session_id')->constrained('cash_sessions');
            $table->unique('cash_session_id');
            $table->bigInteger('opening_balance');
            $table->bigInteger('cash_sales');
            $table->bigInteger('mobile_money_sales');
            $table->bigInteger('expenses');
            $table->bigInteger('expected_cash');
            $table->bigInteger('declared_cash');
            $table->bigInteger('discrepancy');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
    }
};
