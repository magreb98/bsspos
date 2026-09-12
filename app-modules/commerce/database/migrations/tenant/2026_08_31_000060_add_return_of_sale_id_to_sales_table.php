<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->uuid('return_of_sale_id')->nullable();
            $table->foreign('return_of_sale_id')->references('id')->on('sales');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['return_of_sale_id']);
            $table->dropColumn('return_of_sale_id');
        });
    }
};
