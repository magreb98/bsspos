<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table): void {
            $table->string('niu')->nullable()->change();
            $table->string('rccm')->nullable()->change();
            $table->string('company_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table): void {
            $table->string('niu')->nullable(false)->change();
            $table->string('rccm')->nullable(false)->change();
            $table->string('company_name')->nullable(false)->change();
        });
    }
};
