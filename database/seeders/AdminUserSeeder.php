<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Control\AdminUser;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (AdminUser::where('email', 'admin@bsspos.cm')->exists()) {
            return;
        }

        AdminUser::create([
            'name'     => 'BSS Admin',
            'email'    => 'admin@bsspos.cm',
            'password' => 'bsspos2026!',
            'active'   => true,
        ]);
    }
}
