<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KafkaJobFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $failedData;

    public function __construct($failedData)
    {
        $this->failedData = $failedData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->failedData['title'])->view('Email.renewbuy.KafkaFailedMail')->with('failedData', $this->failedData);
        //return $this->subject('Kafka Job Failed - ' . date('d-m-Y'))->setBody();
    }
}
