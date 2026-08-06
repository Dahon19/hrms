<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'date' => $this->date?->toDateString(),
            'morning_time_in' => $this->morning_time_in,
            'morning_time_out' => $this->morning_time_out,
            'afternoon_time_in' => $this->afternoon_time_in,
            'afternoon_time_out' => $this->afternoon_time_out,
            'first_in_time' => $this->firstInTime(),
            'last_out_time' => $this->lastOutTime(),
            'hours_worked' => $this->hours_worked,
            'total_minutes_worked' => $this->totalMinutesWorked(),
            'is_late' => $this->is_late,
            'is_undertime' => $this->is_undertime,
            'late_minutes' => $this->lateMinutes(),
            'status' => $this->status,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'links' => [
                'self' => route('attendance.show', $this->id),
            ],
        ];
    }
}
