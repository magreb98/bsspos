<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('member_perimeter', function (Blueprint $table): void {
            $table->uuid('member_id');
            $table->uuid('perimeter_id');

            $table->primary(['member_id', 'perimeter_id']);

            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->cascadeOnDelete();

            $table->foreign('perimeter_id')
                ->references('id')
                ->on('perimeters')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_perimeter');
    }
};
