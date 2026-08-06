<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'leave_type_id' => $this->leave_type_id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days_requested' => $this->start_date && $this->end_date 
                ? $this->start_date->diffInDays($this->end_date) + 1 
                : null,
            'status' => $this->status,
            'reason' => $this->reason,
            'attachment_path' => $this->attachment_path,
            'attachment_url' => $this->attachment_path ? asset('storage/' . $this->attachment_path) : null,
            'notes' => $this->notes,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'leave_type' => new LeaveTypeResource($this->whenLoaded('leaveType')),
            'head_reviewer' => new UserResource($this->whenLoaded('headReviewer')),
            'hr_reviewer' => new UserResource($this->whenLoaded('hrReviewer')),
            'president_reviewer' => new UserResource($this->whenLoaded('presidentReviewer')),
            'head_reviewed_at' => $this->head_reviewed_at?->toIso8601String(),
            'hr_reviewed_at' => $this->hr_reviewed_at?->toIso8601String(),
            'president_reviewed_at' => $this->president_reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'links' => [
                'self' => route('leave-requests.show', $this->id),
                'approve' => route('leave-requests.approve', $this->id),
                'reject' => route('leave-requests.reject', $this->id),
            ],
        ];
    }
}
