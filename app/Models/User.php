<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'gender',
        'role',
        'archived_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password_notice_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function positionName(): string
    {
        $employee = $this->employee;
        $position = $employee?->positions->first()?->position?->position;

        if (!$position) {
            return 'employee';
        }

        $normalized = strtolower(trim($position));
        if ($normalized === 'dean') {
            return 'head';
        }

        return $normalized;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isReadOnlyStaff(): bool
    {
        return $this->positionName() === 'head';
    }

    public function canViewData(): bool
    {
        return $this->isAdmin() || $this->isReadOnlyStaff();
    }

    public function canAccessDashboard(): bool
    {
        return \App\Services\AccessControl::canAccessDashboard($this);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function canManageOffboarding(): bool
    {
        return $this->isAdmin() || \App\Services\AccessControl::isHrHead($this);
    }
}
