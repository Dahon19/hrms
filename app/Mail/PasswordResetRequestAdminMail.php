<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetRequestAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $userName;
    public string $userEmail;
    public string $requestedAt;
    public string $requestIp;

    public function __construct(string $userName, string $userEmail, string $requestedAt, string $requestIp)
    {
        $this->userName = $userName;
        $this->userEmail = $userEmail;
        $this->requestedAt = $requestedAt;
        $this->requestIp = $requestIp;
    }

    public function build()
    {
        return $this->subject('Password Reset Request - HRMS')
            ->view('emails.password-reset-request-admin');
    }
}

