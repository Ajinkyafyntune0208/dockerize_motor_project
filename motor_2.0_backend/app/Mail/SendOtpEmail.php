<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendOtpEmail extends Mailable
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
        if(config('constants.motorConstant.SMS_FOLDER')=='WhiteHorse')
        {
            return $this->subject($this->mailData['title'])->view('Email.'. config('constants.motorConstant.SMS_FOLDER') .'.otpMailLiberty')->with('mailData', $this->mailData);
        }
        else{
            return $this->subject($this->mailData['title'])->view('Email.'. config('constants.motorConstant.SMS_FOLDER') .'.otpMail')->with('mailData', $this->mailData);
        }
        
    }
}
