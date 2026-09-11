<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->bigInteger('discount_amount')->default(0)->after('allocations');
            $table->uuid('coupon_id')->nullable()->after('discount_amount');

            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn(['discount_amount', 'coupon_id']);
        });
    }
};
