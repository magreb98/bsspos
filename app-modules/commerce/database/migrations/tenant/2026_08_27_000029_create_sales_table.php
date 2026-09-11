<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cash_session_id')->constrained('cash_sessions');
            $table->uuid('client_id')->nullable();
            $table->string('state');
            $table->string('number')->nullable();
            $table->bigInteger('total_excluding_tax')->nullable();
            $table->bigInteger('total_tax')->nullable();
            $table->bigInteger('total_including_tax')->nullable();
            $table->string('idempotency_key')->unique();
            $table->string('anomaly')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
