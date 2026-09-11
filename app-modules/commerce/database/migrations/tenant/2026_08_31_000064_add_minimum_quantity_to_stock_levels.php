<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->integer('minimum_quantity')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->dropColumn('minimum_quantity');
        });
    }
};
