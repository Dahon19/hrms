<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceRecorded implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public array $row)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('attendance.daily');
    }

    public function broadcastAs(): string
    {
        return 'AttendanceRecorded';
    }

    public function broadcastWith(): array
    {
        return [
            'row' => $this->row,
        ];
    }
}
