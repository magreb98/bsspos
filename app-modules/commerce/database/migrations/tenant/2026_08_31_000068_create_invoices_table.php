<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->bigInteger('number_sequence');
            $table->string('number')->unique();
            $table->string('status')->default('draft');
            $table->foreignUuid('client_id')->constrained('customers');
            $table->foreignUuid('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->bigInteger('total_ht')->default(0);
            $table->bigInteger('total_vat')->default(0);
            $table->bigInteger('total_ttc')->default(0);
            $table->bigInteger('paid_amount')->default(0);
            $table->bigInteger('outstanding_amount')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
