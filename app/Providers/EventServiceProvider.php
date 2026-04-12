<?php

namespace App\Providers;

use App\Events\JobApplicationSubmitted;
use App\Listeners\SendJobApplicationNotifications;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        JobApplicationSubmitted::class => [
            SendJobApplicationNotifications::class,
        ],
    ];
}
