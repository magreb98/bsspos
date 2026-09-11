<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Creates the landlord `domains` table.
     *
     * Stancl requires the name `domains` for its DomainTenantResolver.
     * The `Domaine` model points to this table via `protected $table = 'domains'`.
     *
     * Columns: id (auto-increment PK), tenant_id (FK), domain, created_at.
     */
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('tenant_id');
            // Column named `domain` for compatibility with Stancl DomainTenantResolver.
            $table->string('domain', 255)->unique();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('tenant_id');
        });
    }

    /**
     * Drops the `domains` table.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
