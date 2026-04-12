<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeNfc;
use App\Models\EmployeePosition;
use App\Models\OffboardingRecord;
use App\Models\Position;
use App\Models\User;
use App\Services\AccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function activate(Request $request, User $user): RedirectResponse
    {
        $authUser = $request->user();
        abort_unless($authUser && ($authUser->isAdmin() || AccessControl::isHrHead($authUser)), 403);

        $employee = $user->employee;
        $latestOffboarding = Employee::offboardingTablesAvailable()
            ? $employee?->offboardingRecords()->latest('created_at')->first()
            : null;

        if ($latestOffboarding) {
            if (in_array($latestOffboarding->status, OffboardingRecord::activeStatuses(), true)) {
                $latestOffboarding->loadMissing('clearanceItems');
                $hasPendingDepartmentHeadItem = $latestOffboarding->clearanceItems
                    ->where('owner_role', 'department_head')
                    ->contains(fn ($item) => in_array($item->status, ['pending', 'blocked'], true));

                if ($hasPendingDepartmentHeadItem) {
                    return redirect()->back()->with('error', 'Department head clearance is still pending or blocked. Reopen and complete the offboarding workflow before reactivating this account.');
                }

                return redirect()->back()->with('error', 'Reopen or complete the offboarding workflow before reactivating this account.');
            }

            if (in_array($latestOffboarding->status, [OffboardingRecord::STATUS_COMPLETED, OffboardingRecord::STATUS_ARCHIVED], true)) {
                return redirect()->back()->with('error', 'Finalized offboarding records cannot be reactivated through this flow.');
            }
        }

        $nfcUid = trim((string) $request->input('nfc_uid', ''));

        if ($nfcUid !== '' && $employee) {
            Validator::make([
                'nfc_uid' => $nfcUid,
            ], [
                'nfc_uid' => [
                    'nullable',
                    'string',
                    Rule::unique('employee_nfcs', 'nfc_uid')->ignore($employee->nfc?->id),
                ],
            ], [
                'nfc_uid.unique' => 'This RFID is already registered to another employee.',
            ])->validate();
        }

        if ($employee) {
            $reactivationBlockMessage = $this->getReactivationAvailabilityError($employee);
            if ($reactivationBlockMessage !== null) {
                return redirect()->back()->with('error', $reactivationBlockMessage);
            }
        }

        if ($user->archived_at !== null) {
            $user->archived_at = null;
            $user->save();
        }

        if ($employee) {
            if ($employee->status !== 'active') {
                $employee->status = 'active';
                $employee->save();
            }

            if ($nfcUid !== '') {
                EmployeeNfc::updateOrCreate(
                    ['employee_id' => $employee->id],
                    ['nfc_uid' => $nfcUid]
                );
                Cache::forget('latest_nfc_uid');
            }
        }

        return redirect()->back()->with('success', $nfcUid !== '' ? 'User account reactivated and RFID registered.' : 'User account reactivated.');
    }

    private function getReactivationAvailabilityError(Employee $employee): ?string
    {
        $department = $employee->department;
        if ($department && $department->department_type === 'Academic') {
            $departmentLimit = 20;
            $activeDepartmentCount = Employee::query()
                ->where('department_id', $department->id)
                ->where('id', '!=', $employee->id)
                ->whereHas('user', function ($query) {
                    $query->whereNull('archived_at');
                })
                ->count();

            if ($activeDepartmentCount >= $departmentLimit) {
                return 'This account cannot be reactivated because the department has no available employee slot.';
            }
        }

        $positionIds = EmployeePosition::query()
            ->where('employee_id', $employee->id)
            ->pluck('position_id')
            ->map(fn ($positionId) => (int) $positionId)
            ->filter()
            ->unique()
            ->values();

        if ($positionIds->isEmpty()) {
            return null;
        }

        $assignedPositions = Position::query()
            ->whereIn('id', $positionIds)
            ->get(['id', 'position', 'employee_limit']);

        foreach ($assignedPositions as $position) {
            $capacity = $position->capacityLimit();
            if ($capacity === null) {
                continue;
            }

            $activeOccupancy = EmployeePosition::query()
                ->where('position_id', $position->id)
                ->whereHas('employee', function ($query) use ($employee) {
                    $query->where('department_id', $employee->department_id)
                        ->where('id', '!=', $employee->id)
                        ->whereHas('user', function ($userQuery) {
                            $userQuery->whereNull('archived_at');
                        });
                })
                ->distinct('employee_id')
                ->count('employee_id');

            if ($activeOccupancy >= $capacity) {
                return 'This account cannot be reactivated because the assigned position is already filled or not vacant.';
            }
        }

        return null;
    }
}
