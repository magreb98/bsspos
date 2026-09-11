<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('type');
            $table->json('payload');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();
            $table->smallInteger('attempts')->default(0);
        });

        // Partial index on processed_at for MySQL — filter in application code or use covering index.
        Schema::table('outbox_messages', function (Blueprint $table): void {
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
