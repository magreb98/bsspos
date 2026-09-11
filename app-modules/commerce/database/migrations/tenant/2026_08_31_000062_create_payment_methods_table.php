<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('label');
            $table->boolean('auto_confirm')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Seed default methods
        DB::table('payment_methods')->insert([
            ['id' => (string) Str::uuid(), 'key' => 'cash', 'label' => 'Espèces', 'auto_confirm' => true, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'key' => 'mobile_money', 'label' => 'Mobile Money', 'auto_confirm' => false, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
