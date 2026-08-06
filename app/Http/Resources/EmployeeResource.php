<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'rfid' => $this->rfid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => "{$this->first_name} {$this->last_name}",
            'address' => $this->address,
            'hire_date' => $this->hire_date?->toDateString(),
            'status' => $this->status,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'positions' => PositionResource::collection($this->whenLoaded('positions')),
            'user' => new UserResource($this->whenLoaded('user')),
            'nfc' => new EmployeeNfcResource($this->whenLoaded('nfc')),
            'documents_count' => $this->whenCounted('documents'),
            'leave_requests_count' => $this->whenCounted('leaveRequests'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'links' => [
                'self' => route('employees.show', $this->id),
                'documents' => route('employees.documents.index', $this->id),
                'leave_requests' => route('employees.leave-requests.index', $this->id),
            ],
        ];
    }
}
