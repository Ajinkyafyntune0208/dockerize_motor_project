<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VahanExcelReady extends Mailable
{
    use Queueable, SerializesModels;
    protected $url, $expiryTime;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($url, $expiryTime)
    {
        $this->url = $url;
        $this->expiryTime = $expiryTime;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Vahan Upload Excel Report')
            ->view('excelSendVahanImport')
            ->with([
                'url' => $this->url,
                'expirytime' => $this->expiryTime,
            ]);
    }
}
