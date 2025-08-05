<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class FailedJob extends Notification
{
    use Queueable;
    protected $event;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($event)
    {
        $this->event = $event;
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
        if (getCommonConfig('slack.failedJob.enabled') != 'Y') {
            return false;
        }
        $channel_name = getCommonConfig('slack.failedJob.channel.name');
        return (new SlackMessage)
            ->content('A job failed at ' . Str::Upper(config('constants.motorConstant.SMS_FOLDER')))
            ->from('Motor 2.0 Bot', ':robot_face:')
            ->to($channel_name)
            ->attachment(function (SlackAttachment $attachment) use ($notifiable) {
                $attachment->fields([
                    'Exception message' => $this->event->exception->getMessage(),
                    'Job class' => $this->event->job->resolveName(),
                    'UUID' => $this->event->job->uuid(),
                    'Job ID' => $this->event->job->getJobId(),
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
