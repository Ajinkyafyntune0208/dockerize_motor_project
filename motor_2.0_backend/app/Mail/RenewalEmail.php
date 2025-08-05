<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RenewalEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $mailData;

    public function __construct($mailData)
    {
        $this->mailData = $mailData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $configFolder = config('constants.motorConstant.SMS_FOLDER');
        $days = $this->mailData["renewalDays"] ?? '';

        if (!empty($days)) {
            return $this->subject($this->mailData['title'])
                ->view("Email.{$configFolder}.RenewalEmail{$days}")
                ->with('mailData', $this->mailData);
        }

        return $this->subject($this->mailData['title'])
            ->view("Email.{$configFolder}.RenewalEmail")
            ->with('mailData', $this->mailData);
    }
}
