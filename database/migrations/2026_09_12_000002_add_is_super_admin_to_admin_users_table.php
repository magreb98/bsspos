<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table): void {
            // Defaults to true so every admin that already exists today keeps
            // their current, unrestricted access — nobody gets locked out by
            // this migration. Going forward, AdminUserController::store()
            // creates new admins with is_super_admin = false unless an
            // existing super-admin explicitly grants it, so account creation
            // and tenant destruction stop being reachable by any compromised
            // admin token.
            $table->boolean('is_super_admin')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table): void {
            $table->dropColumn('is_super_admin');
        });
    }
};
