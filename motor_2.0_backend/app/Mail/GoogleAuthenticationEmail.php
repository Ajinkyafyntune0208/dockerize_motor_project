<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GoogleAuthenticationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $qrCode;
    public $securityKey;
    public $path;
    public $extra;
    public $url;

    public function __construct($qrCode, $securityKey, $path, $extra = [], $url)
    {
        $this->qrCode = $qrCode;
        $this->securityKey = $securityKey;
        $this->path = $path;
        $this->extra = $extra;
        $this->url = $url;
        
    }

    public function build()
    {
        return $this->subject('2FA Verification: QR Code for Your Account')
        ->view('auth.googleAuthMailNew')
        ->with($this->extra);
    }
}
