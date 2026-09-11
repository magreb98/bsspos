<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Control\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class AuditController
{
    public function index(Request $request): JsonResponse
    {
        $perPage  = min((int) $request->input('per_page', 25), 100);
        $page     = max((int) $request->input('page', 1), 1);
        $tenantId = $request->input('tenant_id');
        $tool     = $request->input('tool');
        $from     = $request->input('from');
        $to       = $request->input('to');

        $tenantsQuery = Tenant::where('status', 'actif');
        if ($tenantId !== null) {
            $tenantsQuery->where('id', $tenantId);
        }

        // Date range is required — default to the last 7 days to bound memory usage.
        // Without a date filter, cross-tenant aggregation would load unbounded rows.
        $from = $from ?? now()->subDays(7)->toDateString();
        $to   = $to   ?? now()->toDateString();

        // Per-tenant cap: enough to cover several pages without unbounded RAM.
        // Cross-tenant sort accuracy degrades only when a single tenant exceeds this
        // threshold within the selected date range (rare in practice).
        $perTenantLimit = min($perPage * 10, 200);

        $allLogs = [];

        foreach ($tenantsQuery->cursor() as $tenant) {
            try {
                $tenant->run(function () use ($tenant, $tool, $from, $to, $perTenantLimit, &$allLogs): void {
                    if (! DB::getSchemaBuilder()->hasTable('mcp_audit_logs')) {
                        return;
                    }

                    $query = DB::table('mcp_audit_logs')
                        ->whereDate('called_at', '>=', $from)
                        ->whereDate('called_at', '<=', $to)
                        ->orderByDesc('called_at');

                    if ($tool !== null) {
                        $query->where('tool', $tool);
                    }

                    foreach ($query->limit($perTenantLimit)->get() as $row) {
                        $allLogs[] = array_merge(
                            (array) $row,
                            ['tenant_id' => $tenant->id, 'tenant_name' => $tenant->name]
                        );
                    }
                });
            } catch (Throwable) {
                // Tenant DB not ready or table missing
            }
        }

        usort($allLogs, static fn (array $a, array $b): int => strcmp(
            (string) ($b['called_at'] ?? ''),
            (string) ($a['called_at'] ?? '')
        ));

        $total  = count($allLogs);
        $offset = ($page - 1) * $perPage;
        $items  = array_slice($allLogs, $offset, $perPage);

        return response()->json([
            'data' => $items,
            'meta' => [
                'total'        => $total,
                'current_page' => $page,
                'per_page'     => $perPage,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $tenantId = $request->input('tenant_id');
        $tool     = $request->input('tool');
        $from     = $request->input('from', now()->subDays(30)->toDateString());
        $to       = $request->input('to', now()->toDateString());

        $tenantsQuery = Tenant::where('status', 'actif');
        if ($tenantId !== null && $tenantId !== '') {
            $tenantsQuery->where('id', $tenantId);
        }

        $allLogs = [];

        foreach ($tenantsQuery->cursor() as $tenant) {
            try {
                $tenant->run(function () use ($tenant, $tool, $from, $to, &$allLogs): void {
                    if (! DB::getSchemaBuilder()->hasTable('mcp_audit_logs')) {
                        return;
                    }

                    $query = DB::table('mcp_audit_logs')
                        ->whereDate('called_at', '>=', $from)
                        ->whereDate('called_at', '<=', $to)
                        ->orderByDesc('called_at');

                    if ($tool !== null && $tool !== '') {
                        $query->where('tool', $tool);
                    }

                    foreach ($query->get() as $row) {
                        $allLogs[] = array_merge(
                            (array) $row,
                            ['tenant_id' => $tenant->id, 'tenant_name' => $tenant->name]
                        );
                    }
                });
            } catch (Throwable) {
            }
        }

        usort($allLogs, static fn (array $a, array $b): int => strcmp(
            (string) ($b['called_at'] ?? ''),
            (string) ($a['called_at'] ?? '')
        ));

        $filename = 'audit-logs-' . $from . '-' . $to . '.csv';

        return response()->streamDownload(function () use ($allLogs): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Entreprise', 'ID Entreprise', 'Outil', 'Appelé le', 'Entrée', 'Sortie']);
            foreach ($allLogs as $log) {
                fputcsv($output, [
                    $log['tenant_name'] ?? '',
                    $log['tenant_id']   ?? '',
                    $log['tool']        ?? '',
                    $log['called_at']   ?? '',
                    json_encode($log['input'] ?? null),
                    json_encode($log['output'] ?? null),
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
