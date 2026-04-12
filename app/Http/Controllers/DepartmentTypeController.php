<?php

namespace App\Http\Controllers;

use App\Models\DepartmentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class DepartmentTypeController extends Controller
{
    public function store(Request $request)
    {
        Gate::authorize('manage-departments');

        if (!Schema::hasTable('department_types')) {
            return redirect()->route('departments.index')->with('error', 'Department Types requires the latest database migration.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:department_types,name'],
        ]);

        DepartmentType::create([
            'name' => trim($validated['name']),
        ]);

        return redirect()->route('departments.index')->with('success', 'Department type created successfully.');
    }

    public function update(Request $request, DepartmentType $departmentType)
    {
        Gate::authorize('manage-departments');

        if (!Schema::hasTable('department_types')) {
            return redirect()->route('departments.index')->with('error', 'Department Types requires the latest database migration.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('department_types', 'name')->ignore($departmentType->id)],
        ]);

        $oldName = $departmentType->name;
        $newName = trim($validated['name']);

        $departmentType->update([
            'name' => $newName,
        ]);

        if ($oldName !== $newName) {
            \App\Models\Department::where('department_type', $oldName)->update([
                'department_type' => $newName,
            ]);
        }

        return redirect()->route('departments.index')->with('success', 'Department type updated successfully.');
    }

    public function destroy(DepartmentType $departmentType)
    {
        Gate::authorize('manage-departments');

        if (!Schema::hasTable('department_types')) {
            return redirect()->route('departments.index')->with('error', 'Department Types requires the latest database migration.');
        }

        \App\Models\Department::where('department_type', $departmentType->name)->update([
            'department_type' => null,
        ]);

        $departmentType->delete();

        return redirect()->route('departments.index')->with('success', 'Department type deleted successfully.');
    }
}
