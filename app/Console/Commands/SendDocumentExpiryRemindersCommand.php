<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\AccessControl;
use App\Services\HrmsNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SendDocumentExpiryRemindersCommand extends Command
{
    protected $signature   = 'documents:send-expiry-reminders {--days=14 : Warn this many days before expiry}';
    protected $description = 'Notify employees and HR when their documents are expiring soon.';

    public function __construct(private readonly HrmsNotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('employee_documents')) {
            $this->info('employee_documents table does not exist. Skipping.');
            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));

        $expiring = EmployeeDocument::query()
            ->with(['employee.user'])
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', today())
            ->whereDate('expires_at', '<=', now()->addDays($days))
            ->where(function ($q) use ($days) {
                // Only notify if not notified yet, or notified more than 3 days ago
                $q->whereNull('expiry_notified_at')
                  ->orWhereDate('expiry_notified_at', '<=', now()->subDays(3));
            })
            ->get();

        if ($expiring->isEmpty()) {
            $this->info('No expiring documents found within the next ' . $days . ' days.');
            return self::SUCCESS;
        }

        // Collect HR heads and admins to notify
        $hrRecipients = User::query()
            ->whereIn('role', ['admin'])
            ->orWhereHas('employee', fn ($q) => $q->whereHas('position', fn ($p) =>
                $p->where('position', 'LIKE', '%hr%')
            ))
            ->get();

        $notified = 0;

        foreach ($expiring as $document) {
            $employee = $document->employee;
            if (! $employee) {
                continue;
            }

            $employeeUser = $employee->user;
            $daysLeft     = (int) now()->diffInDays($document->expires_at, false);
            $docName      = $document->document_name ?? 'Employee document';

            // Notify the employee
            if ($employeeUser) {
                $this->notificationService->notifyUsers([$employeeUser], [
                    'type'       => 'warning',
                    'title'      => 'Document Expiring Soon',
                    'message'    => "Your document \"{$docName}\" expires in {$daysLeft} day(s). Please renew it promptly.",
                    'module'     => 'documents',
                    'event_key'  => 'document.expiry_reminder',
                    'record_id'  => $document->id,
                    'route_name' => 'employee-documents.index',
                    'route_params' => ['employee_id' => $employee->id],
                ]);
            }

            // Notify HR recipients
            if ($hrRecipients->isNotEmpty()) {
                $employeeName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
                $this->notificationService->notifyUsers($hrRecipients, [
                    'type'        => 'warning',
                    'title'       => 'Employee Document Expiring',
                    'message'     => "\"{$docName}\" for {$employeeName} expires in {$daysLeft} day(s).",
                    'module'      => 'documents',
                    'event_key'   => 'document.expiry_reminder_hr',
                    'record_id'   => $document->id,
                    'route_name'  => 'employee-documents.index',
                ]);
            }

            // Stamp notification time
            $document->update(['expiry_notified_at' => now()]);
            $notified++;
        }

        $this->info("Sent expiry reminders for {$notified} document(s).");
        return self::SUCCESS;
    }
}
