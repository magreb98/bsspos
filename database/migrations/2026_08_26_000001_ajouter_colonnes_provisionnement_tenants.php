<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->smallInteger('provisioning_step')->default(0);
            $table->text('provisioning_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['provisioning_step', 'provisioning_error']);
        });
    }
};
