<?php

namespace App\Mail;

use App\Models\ConfigSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordPolicyMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    protected $user;
    protected $token;
    public function __construct($user,$token)
    {
        $this->user = $user;
        $this->token = $token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    { 
        return $this->subject('Password Reset Notification for Your '. config('constants.brokerName') .' Account')->view('Email.password-policy.PasswordPolicyMail', ['name' => $this->user->name,'email' => $this->user->email,'date' => $this->user->password_expire_at,'token' => $this->token ]); 
    }
}
