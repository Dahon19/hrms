<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LeaveTypeController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manage-leave-types');
        $search = $request->get('search');
        $types = LeaveType::query()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->get();

        return view('leaves.types', compact('types'));
    }

    public function create()
    {
        Gate::authorize('manage-leave-types');
        return view('leaves.types');
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-leave-types');
        $validated = $request->validate([
            'name'                => 'required|string|max:255|unique:leave_types,name',
            'color_code'          => 'required|string|size:7',
            'requires_attachment' => 'sometimes|boolean',
            'max_days'            => 'nullable|integer|min:1|max:365',
            'gender'              => 'nullable|string|in:male,female',
        ]);

        $validated['requires_attachment'] = $request->boolean('requires_attachment');
        LeaveType::create($validated);

        return redirect()->route('leave-types.index')->with('success', 'Leave type created.');
    }

    public function edit(LeaveType $leave_type)
    {
        Gate::authorize('manage-leave-types');
        return view('leaves.types', ['type' => $leave_type]);
    }

    public function update(Request $request, LeaveType $leave_type)
    {
        Gate::authorize('manage-leave-types');
        $validated = $request->validate([
            'name'                => 'required|string|max:255|unique:leave_types,name,' . $leave_type->id,
            'color_code'          => 'required|string|size:7',
            'requires_attachment' => 'sometimes|boolean',
            'max_days'            => 'nullable|integer|min:1|max:365',
            'gender'              => 'nullable|string|in:male,female',
        ]);

        $validated['requires_attachment'] = $request->boolean('requires_attachment');
        $leave_type->update($validated);

        return redirect()->route('leave-types.index')->with('success', 'Leave type updated.');
    }

    public function destroy(LeaveType $leave_type)
    {
        Gate::authorize('manage-leave-types');
        $leave_type->delete();
        return redirect()->route('leave-types.index')->with('success', 'Leave type deleted.');
    }
}
