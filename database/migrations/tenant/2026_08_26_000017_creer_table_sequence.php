<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('sequence', function (Blueprint $table): void {
            $table->uuid('unit_id');
            $table->string('key', 100);
            $table->integer('fiscal_year');
            $table->bigInteger('last_number')->default(0);

            $table->primary(['unit_id', 'key', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence');
    }
};
