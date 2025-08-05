<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class IcTimeoutNotification extends Notification
{
    use Queueable;
    protected $details;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($details)
    {
        $this->details = $details;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['slack'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\SlackMessage
     */
    public function toSlack($notifiable)
    {
        if (getCommonConfig('slack.notification.isEnabled') != 'Y') {
            return false;
        }
        if (getCommonConfig('slack.IcTimeoutNotification.isEnabled') != 'Y') {
            return false;
        }
        $channel_name = getCommonConfig('slack.IcTimeoutNotification.channel.name');
        $url = url('admin/log') . '/' . ($this->details['log_id'] ?? '') . '?transaction_type=' . ($this->details['transaction_type'] ?? '');
        $ic_name = Str::upper(Str::replace('_', ' ', $this->details['company']));
        return (new SlackMessage)
            ->content($ic_name . ' - API timed out on broker : ' . Str::Upper(config('constants.motorConstant.SMS_FOLDER')))
            ->from('Motor 2.0 Bot', ':robot_face:')
            ->to($channel_name)
            ->attachment(function (SlackAttachment $attachment) use ($notifiable, $ic_name, $url) {
                $attachment->fields([
                    'IC Name' => $ic_name,
                    'Trace ID' => customEncrypt($this->details['enquiry_id']),
                    'Product' => $this->details['product'],
                    'Section' => $this->details['section'],
                    'Method Name' => $this->details['method_name'],
                    'Transaction Type' => $this->details['transaction_type'],
                    'Response Time' => $this->details['response_time'],
                    'Log URL' => $url
                ]);
            });
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
