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
        Schema::create('currencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 10);
            // exchange_rate = actual_rate × 1000, e.g. 655000 means 1 foreign unit = 655 XAF
            $table->bigInteger('exchange_rate')->default(1000);
            $table->boolean('is_base')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('currencies')->insert([
            [
                'id'            => (string) Str::uuid(),
                'code'          => 'XAF',
                'name'          => 'Franc CFA (CEMAC)',
                'symbol'        => 'FCFA',
                'exchange_rate' => 1000,
                'is_base'       => true,
                'active'        => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'id'            => (string) Str::uuid(),
                'code'          => 'USD',
                'name'          => 'Dollar américain',
                'symbol'        => '$',
                'exchange_rate' => 655000,
                'is_base'       => false,
                'active'        => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'id'            => (string) Str::uuid(),
                'code'          => 'EUR',
                'name'          => 'Euro',
                'symbol'        => '€',
                'exchange_rate' => 655957,
                'is_base'       => false,
                'active'        => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
