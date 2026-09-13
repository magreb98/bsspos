<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RequiresSuperAdmin;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SettingsController
{
    use RequiresSuperAdmin;

    private const DEFAULTS = [
        'platform_name'        => 'BSS POS',
        'contact_email'        => '',
        'max_tenants'          => 0,
        'maintenance_mode'     => false,
        'provisioning_auto'    => true,
        'support_url'          => '',
    ];

    public function index(): JsonResponse
    {
        $rows     = DB::table('platform_settings')->get()->keyBy('key');
        $settings = [];

        foreach (self::DEFAULTS as $key => $default) {
            $row = $rows->get($key);
            $settings[$key] = $row !== null ? json_decode((string) $row->value, true) ?? $default : $default;
        }

        return response()->json(['data' => $settings]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->assertActingAdminIsSuperAdmin($request);

        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            DB::table('platform_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'updated_at' => now()],
            );
        }

        return $this->index();
    }
}
