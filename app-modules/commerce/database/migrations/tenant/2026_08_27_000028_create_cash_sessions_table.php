<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cash_register_id')->constrained('cash_registers');
            $table->string('state');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->bigInteger('opening_balance');
            $table->bigInteger('closing_balance')->nullable();
            $table->uuid('opened_by');
            $table->uuid('closed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
