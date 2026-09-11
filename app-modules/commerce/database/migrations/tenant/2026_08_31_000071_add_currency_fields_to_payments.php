<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            // currency_code of the original payment (default XAF = base currency)
            $table->string('currency_code', 3)->default('XAF')->after('amount');
            // exchange_rate × 1000 used at time of payment, e.g. 655000 = 655 XAF/USD
            $table->bigInteger('exchange_rate')->default(1000)->after('currency_code');
            // original amount in foreign currency (null when currency_code = 'XAF')
            $table->bigInteger('amount_original')->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['currency_code', 'exchange_rate', 'amount_original']);
        });
    }
};
