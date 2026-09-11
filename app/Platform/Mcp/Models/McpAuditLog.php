<?php

declare(strict_types=1);

namespace App\Platform\Mcp\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class McpAuditLog extends Model
{
    use HasUuids;

    protected $table = 'mcp_audit_logs';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'agent_name',
        'tool',
        'params',
        'called_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'params' => 'array',
        'called_at' => 'datetime',
    ];
}
