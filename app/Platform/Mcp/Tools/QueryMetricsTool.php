<?php

declare(strict_types=1);

namespace App\Platform\Mcp\Tools;

use App\Platform\Identity\Models\User;
use App\Platform\Mcp\McpToolContract;
use App\Platform\Mcp\MetricAuthorizerContract;
use Illuminate\Support\Facades\DB;

final class QueryMetricsTool implements McpToolContract
{
    public function __construct(private readonly MetricAuthorizerContract $authorizer)
    {
    }

    public function name(): string
    {
        return 'query_metrics';
    }

    public function description(): string
    {
        return 'Query aggregated commerce metrics between two dates.';
    }

    /** @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function call(User $user, array $params): array
    {
        $metric = (string) ($params['metric'] ?? '');
        $from = (string) ($params['from'] ?? '');
        $to = (string) ($params['to'] ?? '');

        $this->authorizer->authorize($metric, $user);

        $totalIncludingTax = (int) DB::table('daily_aggregates')
            ->whereBetween('date', [$from, $to])
            ->sum('total_including_tax');

        $saleCount = (int) DB::table('daily_aggregates')
            ->whereBetween('date', [$from, $to])
            ->sum('sale_count');

        return [
            'metric' => $metric,
            'from' => $from,
            'to' => $to,
            'total_including_tax' => $totalIncludingTax,
            'sale_count' => $saleCount,
        ];
    }
}
