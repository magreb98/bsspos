<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape returned by AdminUserController::store() — mirrors the hand-built
 * array previously constructed inline in the controller.
 */
final class AdminUserStoreResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'email'          => $this->email,
            'is_super_admin' => $this->is_super_admin,
        ];
    }
}
