<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class KafkaDataPushValidation extends Notification
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
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toSlack($notifiable)
    {
        if (getCommonConfig('slack.notification.isEnabled') != 'Y') {
            return false;
        }
        if (getCommonConfig('slack.kafkaFailedValidation.enable') != 'Y') {
            return false;
        }
        unset($this->details['name'], $this->details['title']);
        $channel_name = getCommonConfig('slack.kafkaFailedValidation.channel.name');
        return (new SlackMessage)
            ->content('RB : Kafka payload validation failed !')
            ->from('Motor 2.0 Bot', ':robot_face:')
            ->to($channel_name)
            ->attachment(function (SlackAttachment $attachment) use ($notifiable) {
                $attachment->fields($this->details);
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
