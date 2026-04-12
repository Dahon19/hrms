<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}.notifications', function ($user, int $userId) {
    return (int) $user->id === $userId;
});
