<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TalktoExpert extends Mailable
{
    use Queueable, SerializesModels;
    public $leadData;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($leadData)
    {
        $this->leadData= $leadData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('Email.TalktoExpert')->subject($this->leadData['subject'])
        ->with('mailData', $this->leadData);
    }
}
