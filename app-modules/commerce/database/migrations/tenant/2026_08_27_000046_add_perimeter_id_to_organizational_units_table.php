<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organizational_units', function (Blueprint $table): void {
            $table->uuid('perimeter_id')->nullable()->after('parent_id');

            $table->foreign('perimeter_id')
                ->references('id')
                ->on('perimeters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizational_units', function (Blueprint $table): void {
            $table->dropForeign(['perimeter_id']);
            $table->dropColumn('perimeter_id');
        });
    }
};
