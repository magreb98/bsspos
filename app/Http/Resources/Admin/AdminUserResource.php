<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full AdminUser representation used by AdminUserController::index() —
 * mirrors the explicit column select previously passed to ->get([...]).
 */
final class AdminUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'active'            => $this->active,
            'is_super_admin'    => $this->is_super_admin,
            'last_connected_at' => $this->last_connected_at,
            'created_at'        => $this->created_at,
        ];
    }
}
