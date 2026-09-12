<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape of a tenant "member" row returned by TenantController::users() —
 * mirrors the explicit column select previously passed to the query builder.
 */
final class TenantMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'first_name'        => $this->first_name,
            'last_name'         => $this->last_name,
            'phone'             => $this->phone,
            'email'             => $this->email,
            'active'            => $this->active,
            'last_connected_at' => $this->last_connected_at,
            'created_at'        => $this->created_at,
        ];
    }
}
