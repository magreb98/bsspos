<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->string('key', 255)->primary();
            $table->string('request_fingerprint', 255);
            $table->json('response')->nullable();
            $table->smallInteger('status')->nullable();
            $table->timestampTz('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
