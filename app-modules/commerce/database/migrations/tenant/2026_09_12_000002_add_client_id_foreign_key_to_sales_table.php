<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->index('client_id');
            $table->foreign('client_id')->references('id')->on('customers');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);
            $table->dropIndex(['client_id']);
        });
    }
};
