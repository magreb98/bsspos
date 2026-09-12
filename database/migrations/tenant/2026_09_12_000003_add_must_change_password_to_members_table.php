<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            // Defaults to true so existing accounts (created with phone-number
            // passwords before this column existed) are also forced through a
            // change on next login, not just newly created ones.
            $table->boolean('must_change_password')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn('must_change_password');
        });
    }
};
