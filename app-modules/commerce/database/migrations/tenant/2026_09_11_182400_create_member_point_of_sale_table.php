<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('member_point_of_sale', function (Blueprint $table): void {
            $table->uuid('member_id');
            $table->uuid('point_of_sale_id');
            $table->timestamps();

            $table->primary(['member_id', 'point_of_sale_id']);

            $table->foreign('point_of_sale_id')
                ->references('id')
                ->on('points_of_sale')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_point_of_sale');
    }
};
