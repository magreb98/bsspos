<?php

declare(strict_types=1);

namespace App\Platform\Mcp\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class McpToken extends Model
{
    use HasUuids;

    protected $table = 'mcp_tokens';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
    ];
}
