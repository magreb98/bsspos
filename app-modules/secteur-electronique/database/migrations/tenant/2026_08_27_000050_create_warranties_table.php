<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('warranties', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('serial_unit_id')->unique();
            $table->uuid('sale_line_id');
            $table->string('starts_on');
            $table->string('expires_on');
            $table->smallInteger('duration_months');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranties');
    }
};
