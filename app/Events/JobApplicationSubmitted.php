<?php

namespace App\Events;

use App\Models\Applicant;
use App\Models\JobPosting;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobApplicationSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Applicant $applicant,
        public JobPosting $jobPosting
    ) {
    }
}
