<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAuditAccess($request);

        $filters = $this->filters($request);
        $logs = $this->baseQuery($filters)
            ->paginate(10)
            ->withQueryString();

        $logs->setCollection(
            $logs->getCollection()->map(fn (AuditLog $log) => $this->decorateLog($log))
        );

        return view('audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizeAuditAccess($request);

        $filters = $this->filters($request);
        $logs = $this->baseQuery($filters)->get()->map(fn (AuditLog $log) => $this->decorateLog($log));
        $filename = 'audit-logs-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Time', 'User', 'Action', 'Model', 'Record', 'Summary', 'IP']);

            foreach ($logs as $log) {
                fputcsv($out, [
                    optional($log->created_at)->format('M d, Y H:i'),
                    $log->user?->name ?? 'System',
                    ucfirst((string) $log->action),
                    class_basename((string) $log->auditable_type),
                    (string) $log->auditable_id,
                    (string) ($log->summary_text ?? '-'),
                    (string) ($log->ip_address ?? '-'),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function print(Request $request)
    {
        $this->authorizeAuditAccess($request);

        $filters = $this->filters($request);
        $logs = $this->baseQuery($filters)
            ->limit(250)
            ->get()
            ->map(fn (AuditLog $log) => $this->decorateLog($log));

        $pdf = Pdf::loadView('audit-logs.print', [
            'logs' => $logs,
            'filters' => $filters,
            'printedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('audit-logs-' . now()->format('Ymd_His') . '.pdf', ['Attachment' => false]);
    }

    private function authorizeAuditAccess(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            abort(403);
        }
    }

    /**
     * @return array{action:string,user:string,start:string,end:string}
     */
    private function filters(Request $request): array
    {
        return [
            'action' => trim((string) $request->query('action', '')),
            'user' => trim((string) $request->query('user', '')),
            'start' => trim((string) $request->query('start', '')),
            'end' => trim((string) $request->query('end', '')),
        ];
    }

    private function baseQuery(array $filters)
    {
        return AuditLog::with('user')
            ->when($filters['action'] !== '', function ($query) use ($filters) {
                $query->whereRaw('LOWER(action) = ?', [Str::lower($filters['action'])]);
            })
            ->when($filters['user'] !== '', function ($query) use ($filters) {
                $query->whereHas('user', function ($userQuery) use ($filters) {
                    $userQuery->where('name', $filters['user']);
                });
            })
            ->when($filters['start'] !== '', function ($query) use ($filters) {
                $query->whereDate('created_at', '>=', $filters['start']);
            })
            ->when($filters['end'] !== '', function ($query) use ($filters) {
                $query->whereDate('created_at', '<=', $filters['end']);
            })
            ->orderByDesc('created_at');
    }

    private function decorateLog(AuditLog $log): AuditLog
    {
        $log->setAttribute('summary_text', $this->buildSummary($log));

        return $log;
    }

    private function buildSummary(AuditLog $log): string
    {
        $metadata = is_array($log->metadata) ? $log->metadata : [];
        $action = Str::lower((string) $log->action);
        $modelName = class_basename((string) $log->auditable_type) ?: 'Record';

        if (isset($metadata['changes']) && is_array($metadata['changes']) && !empty($metadata['changes'])) {
            $keys = array_keys($metadata['changes']);
            return 'Changed ' . implode(', ', array_slice($keys, 0, 4)) . (count($keys) > 4 ? '...' : '');
        }

        if (isset($metadata['attributes']) && is_array($metadata['attributes']) && !empty($metadata['attributes'])) {
            $keys = array_keys($metadata['attributes']);
            return 'Fields: ' . implode(', ', array_slice($keys, 0, 4)) . (count($keys) > 4 ? '...' : '');
        }

        foreach ([
            'message',
            'remarks',
            'reason',
            'title',
            'description',
            'job_title',
            'employee_name',
            'applicant_name',
            'award_title',
            'section',
            'status',
        ] as $key) {
            $value = trim((string) ($metadata[$key] ?? ''));
            if ($value !== '') {
                return Str::limit($value, 90);
            }
        }

        foreach ([
            ['employee_id', 'Employee'],
            ['profile_id', 'Profile'],
            ['reward_record_id', 'Reward'],
            ['job_posting_id', 'Job Posting'],
            ['travel_order_id', 'Travel Order'],
        ] as [$key, $label]) {
            $value = trim((string) ($metadata[$key] ?? ''));
            if ($value !== '') {
                return $label . ' #' . $value;
            }
        }

        if (($metadata['section'] ?? null) && ($metadata['employee_id'] ?? null)) {
            return Str::headline((string) $metadata['section']) . ' updated for employee #' . $metadata['employee_id'];
        }

        if (($metadata['award_type'] ?? null) && ($metadata['employee_id'] ?? null)) {
            return Str::headline((string) $metadata['award_type']) . ' reward assigned to employee #' . $metadata['employee_id'];
        }

        $pairs = collect($metadata)
            ->filter(fn ($value, $key) => is_scalar($value) && $value !== '' && !in_array($key, ['ip_address', 'user_agent'], true))
            ->map(fn ($value, $key) => Str::headline((string) $key) . ': ' . $value)
            ->values();

        if ($pairs->isNotEmpty()) {
            return Str::limit($pairs->take(2)->implode(' · '), 90);
        }

        return Str::headline($action) . ' ' . $modelName . ' #' . $log->auditable_id;
    }
}
