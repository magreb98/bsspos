<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Creates the landlord `tenants` table.
     *
     * Columns: id (uuid PK), name, status, data (json), created_at, updated_at.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('name', 255);
            $table->string('status', 50)->default('pending');

            // data is the JSON field used by Stancl (VirtualColumn) to store its internal keys.
            // MySQL 8 rejects JSON literal defaults — nullable is handled by Stancl as empty array.
            $table->json('data')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('status');
        });
    }

    /**
     * Drops the `tenants` table.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
