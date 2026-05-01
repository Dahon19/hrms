<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AttendanceSettingsController extends Controller
{
    public function update(Request $request)
    {
        Gate::authorize('manage-attendance');

        $validated = $request->validate([
            'shift_start' => 'required',
            'shift_end' => 'required',
            'break_start' => 'required',
            'break_end' => 'required',
            'grace_minutes' => 'required|integer|min:0',
            'overtime_threshold_minutes' => 'required|integer|min:0',
            'weekend_overtime' => 'sometimes|boolean',
            'require_four_taps' => 'sometimes|boolean',
        ]);

        $setting = AttendanceSetting::current();
        
        $setting->update([
            'shift_start' => $request->shift_start,
            'shift_end' => $request->shift_end,
            'break_start' => $request->break_start,
            'break_end' => $request->break_end,
            'grace_minutes' => $request->integer('grace_minutes'),
            'overtime_threshold_minutes' => $request->integer('overtime_threshold_minutes'),
            'weekend_overtime' => $request->boolean('weekend_overtime'),
            'require_four_taps' => $request->boolean('require_four_taps'),
        ]);

        return redirect()->back()->with('success', 'Attendance settings updated successfully.');
    }
}
