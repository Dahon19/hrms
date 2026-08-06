<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'gender' => $this->gender,
            'role' => $this->role,
            'is_admin' => $this->isAdmin(),
            'can_access_dashboard' => $this->canAccessDashboard(),
            'position_name' => $this->positionName(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'links' => [
                'self' => route('users.show', $this->id),
                'profile' => route('profile.edit'),
            ],
        ];
    }
}
