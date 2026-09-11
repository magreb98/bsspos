<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sale_id')->unique();
            $table->bigInteger('deposit')->default(0);
            $table->bigInteger('total');
            $table->timestamps();
        });

        Schema::create('installments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('payment_schedule_id');
            $table->bigInteger('amount');
            $table->string('due_on');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
        Schema::dropIfExists('payment_schedules');
    }
};
