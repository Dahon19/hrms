<?php

namespace App\View\Components;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

class Sidebar extends Component
{
    public User $user;
    public string $avatarUrl;
    public string $avatarFallback;
    public string $positionName;
    public bool $isPresidentApprover;
    public bool $isHrHead;
    public bool $isHrStaff;
    public bool $isManagement;
    public bool $isEmployeeActive;
    public int $pendingLeaveCount;

    public function __construct(?User $user = null)
    {
        $this->user = $user ?? Auth::user();
        $this->positionName = $this->user->positionName();

        $departmentName = strtolower(trim($this->user->employee?->department?->department ?? ''));
        $normalizedDept = preg_replace('/[^a-z0-9 ]/i', '', $departmentName) ?? '';
        $normalizedDept = trim(preg_replace('/\s+/', ' ', $normalizedDept) ?? '');

        $isPresidentOffice = $normalizedDept === 'presidents office';
        $this->isPresidentApprover = $this->positionName === 'head' && $isPresidentOffice;
        $this->isHrHead = $this->positionName === 'head' && $normalizedDept === 'hr department';
        $this->isHrStaff = $normalizedDept === 'hr department';
        $this->isManagement = $this->user->canViewData() || $this->isHrStaff;
        $this->isEmployeeActive = request()->routeIs('employees.*')
            || request()->routeIs('employee-documents.*')
            || request()->routeIs('documents.*')
            || request()->routeIs('pds.*')
            || request()->routeIs('spms.*')
            || request()->routeIs('eligibility.*')
            || request()->routeIs('rewards.*');

        $this->pendingLeaveCount = $this->resolvePendingLeaveCount();

        $this->avatarFallback = $this->buildAvatarFallback();
        $this->avatarUrl = $this->resolveAvatarUrl();
    }

    public function render(): View
    {
        return view('components.sidebar');
    }

    private function resolvePendingLeaveCount(): int
    {
        if ($this->isPresidentApprover) {
            return LeaveRequest::where('status', 'HR Approved')
                ->whereNull('president_reviewed_by')
                ->count();
        }

        if ($this->isHrHead) {
            return LeaveRequest::where('status', 'Approved')->count();
        }

        if ($this->positionName === 'head') {
            $departmentId = $this->user->employee?->department_id;
            if ($departmentId) {
                return LeaveRequest::where('status', 'Pending')
                    ->whereHas('employee', function ($query) use ($departmentId) {
                        $query->where('department_id', $departmentId);
                    })
                    ->count();
            }
        }

        return 0;
    }

    private function buildAvatarFallback(): string
    {
        $avatarLetter = strtoupper(substr($this->user->employee->first_name ?? $this->user->name ?? 'U', 0, 1));
        $safeLetter = htmlspecialchars($avatarLetter, ENT_QUOTES, 'UTF-8');
        $avatarSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">'
            . '<rect width="100%" height="100%" fill="#e9ecef"/>'
            . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="96" fill="#007bff">'
            . $safeLetter
            . '</text></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($avatarSvg);
    }

    private function resolveAvatarUrl(): string
    {
        if (empty($this->user->avatar)) {
            return '';
        }

        $parts = explode('/', $this->user->avatar);
        $folder = $parts[0] ?? null;
        $subfolder = $parts[1] ?? null;
        $filename = $parts[2] ?? null;

        if (!$folder || !$subfolder || !$filename) {
            return '';
        }

        return route('storage.file', [
            'folder' => $folder,
            'subfolder' => $subfolder,
            'filename' => $filename,
        ]);
    }
}



