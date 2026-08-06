<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department' => $this->department,
            'department_type' => $this->department_type,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_path ? asset('storage/' . $this->logo_path) : null,
            'type' => new DepartmentTypeResource($this->whenLoaded('type')),
            'employees_count' => $this->whenCounted('employees'),
            'positions_count' => $this->whenCounted('positions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'links' => [
                'self' => route('departments.show', $this->id),
                'employees' => route('departments.employees.index', $this->id),
                'positions' => route('departments.positions.index', $this->id),
            ],
        ];
    }
}
