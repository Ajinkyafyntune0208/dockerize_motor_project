<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessEmail extends Mailable
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
        if (empty($this->mailData['link'])) {
            return $this->subject($this->mailData['title'])
                ->view('Email.' . config('constants.motorConstant.SMS_FOLDER') . '.SuccessEmail')
                ->with('mailData', $this->mailData);
        }

        return $this->subject($this->mailData['title'])
            ->view('Email.'. config('constants.motorConstant.SMS_FOLDER') .'.SuccessEmail')
            ->with('mailData', $this->mailData)
            ->attachData(httpRequestNormal($this->mailData['link'],'GET',[],[],[], [], false)['response'], 'Policy Document.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
