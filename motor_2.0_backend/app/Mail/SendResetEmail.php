<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendResetEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $url;
    public $extras = [];

    public function __construct($url, $extras = [])
    {
        $this->url = $url;
        $this->extras = $extras;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject("Password Reset Request")->view('forgot-password')->with([
            'url' => $this->url,
            'extras' => $this->extras
        ]);
    }
}
