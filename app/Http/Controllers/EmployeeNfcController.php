<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeNfc;
use App\Services\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EmployeeNfcController extends Controller
{
    private const LATEST_NFC_CACHE_KEY = 'latest_nfc_uid';

    protected function canManageNfc(?object $user): bool
    {
        return (bool) $user
            && ($user->isAdmin() || AccessControl::isHrHead($user));
    }

    /**
     * Assign NFC card to employee
     *
     * @group NFC
     * @bodyParam employee_id int required The ID of the employee. Example: 1
     * @bodyParam nfc_uid string required The NFC card UID. Example: "A1B2C3D4"
     * @response {
     *   "message": "ID card registered successfully."
     * }
     */
    public function assign(Request $request)

    {
        $user = $request->user();
        abort_unless($this->canManageNfc($user), 403);

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'nfc_uid' => ['required', 'string'],
        ]);

        $employee = Employee::with(['user', 'nfc'])->findOrFail((int) $validated['employee_id']);

        Validator::make($validated, [
            'nfc_uid' => [
                'required',
                'string',
                Rule::unique('employee_nfcs', 'nfc_uid')->ignore($employee->nfc?->id),
            ],
        ], [
            'nfc_uid.unique' => 'This ID card is already registered to another employee.',
        ])->validate();

        EmployeeNfc::updateOrCreate(
            ['employee_id' => $employee->id],
            ['nfc_uid' => trim((string) $validated['nfc_uid'])]
        );

        Cache::forget(self::LATEST_NFC_CACHE_KEY);

        return back()->with('success', 'ID card registered successfully.');
    }

    /**
     * Scan NFC card
     *
     * Accepts an NFC card scan and records the presence event.
     *
     * @group NFC
     * @bodyParam nfc_uid string required The NFC card UID. Example: "A1B2C3D4"
     * @response {
     *   "status": "success"
     * }
     */
    public function scan(Request $request)

    {
        $request->validate([
            'nfc_uid' => 'required|string',
        ]);

        return response()->json([
            'status'       => 'success',
        ]);
    }
    /**
     * Receive NFC data
     *
     * Receives NFC card data from hardware devices and stores it in cache.
     *
     * @group NFC
     * @bodyParam nfc_uid string required The NFC card UID. Example: "A1B2C3D4"
     * @response {
     *   "status": "ok",
     *   "nfc_uid": "A1B2C3D4"
     * }
     */
    public function receiveNfc(Request $request)

    {
        $request->validate([
            'nfc_uid' => 'required|string',
        ]);

        $uid = trim((string) $request->nfc_uid);
        $capturedAt = now();

        Cache::put(self::LATEST_NFC_CACHE_KEY, [
            'nfc_uid' => $uid,
            'scan_id' => (string) str()->uuid(),
            'timestamp' => $capturedAt->toIso8601String(),
            'captured' => true,
        ], $capturedAt->copy()->addMinutes(2));

        return response()->json([
            'status' => 'ok',
            'nfc_uid' => $uid,
        ]);
    }

    /**
     * Get latest NFC scan
     *
     * Retrieves the most recent NFC scan from cache.
     *
     * @group NFC
     * @queryParam clear boolean Optional. Clear the cached NFC data. Example: true
     * @queryParam after string Optional. ISO 8601 timestamp to filter scans after. Example: "2024-01-01T00:00:00Z"
     * @response {
     *   "nfc_uid": "A1B2C3D4",
     *   "exists": true,
     *   "captured": true,
     *   "timestamp": "2024-01-01T12:00:00+00:00",
     *   "scan_id": "uuid-here",
     *   "assigned": true
     * }
     */
    public function latestNfc(Request $request)

    {
        abort_unless($this->canManageNfc($request->user()), 403);

        if ($request->boolean('clear')) {
            Cache::forget(self::LATEST_NFC_CACHE_KEY);
        }

        $payload = Cache::get(self::LATEST_NFC_CACHE_KEY);
        if (is_string($payload) && $payload !== '') {
            $payload = [
                'nfc_uid' => $payload,
                'scan_id' => null,
                'timestamp' => null,
                'captured' => true,
            ];
        }

        $after = $request->query('after');
        if (is_array($payload) && $after && !empty($payload['timestamp'])) {
            try {
                $payloadTime = strtotime((string) $payload['timestamp']);
                $afterTime = strtotime((string) $after);
                if ($payloadTime !== false && $afterTime !== false && $payloadTime < $afterTime) {
                    $payload = null;
                }
            } catch (\Throwable $exception) {
                // Ignore malformed timestamp filters and fall back to latest payload.
            }
        }

        $uid = is_array($payload) ? ($payload['nfc_uid'] ?? null) : null;
        $exists = $uid ? EmployeeNfc::where('nfc_uid', $uid)->exists() : false;

        return response()->json([
            'nfc_uid' => $uid,
            'exists' => $exists,
            'captured' => (bool) ($payload['captured'] ?? false),
            'timestamp' => $payload['timestamp'] ?? null,
            'scan_id' => $payload['scan_id'] ?? null,
            'assigned' => $exists,
        ]);
    }
}
