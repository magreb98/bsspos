<?php

declare(strict_types=1);

namespace App\Control;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

final class AdminUser extends Model
{
    use HasUuids;

    protected $table = 'admin_users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = ['password'];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
        'last_connected_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active'             => 'boolean',
        'last_connected_at'  => 'datetime',
        'password'           => 'hashed',
    ];

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    public function checkPassword(string $plaintext): bool
    {
        return Hash::check($plaintext, $this->password);
    }

    /** @return HasMany<AdminToken, $this> */
    public function tokens(): HasMany
    {
        return $this->hasMany(AdminToken::class, 'admin_user_id');
    }
}
