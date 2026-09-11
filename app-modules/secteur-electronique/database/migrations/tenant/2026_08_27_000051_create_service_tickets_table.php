<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('service_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('serial_unit_id');
            $table->string('status')->default('open');
            $table->text('description');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_tickets');
    }
};
