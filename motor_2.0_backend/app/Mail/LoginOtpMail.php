<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;
    public $otp;
    public $expiry;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($otp, $expiry)
    {
        $this->otp = $otp;
        $this->expiry = $expiry;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('auth.loginOtpMail');
    }
}
