<?php

namespace App\Listeners;

use App\Events\ResultCreated;
use App\Mail\SpeedtestCompletedMail;
use App\Settings\GeneralSettings;
use App\Settings\NotificationSettings;
use App\Telegram\TelegramNotification;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

class SpeedtestCompletedListener
{
    public $generalSettings;

    public $notificationSettings;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        $this->generalSettings = new (GeneralSettings::class);

        $this->notificationSettings = new (NotificationSettings::class);
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(ResultCreated $event)
    {
        if ($this->notificationSettings->database_enabled) {
            if ($this->notificationSettings->database_on_speedtest_run) {
                Notification::make()
                    ->title('Speedtest completed')
                    ->success()
                    ->sendToDatabase($event->user);
            }
        }

        if ($this->notificationSettings->mail_enabled) {
            if ($this->notificationSettings->mail_on_speedtest_run && count($this->notificationSettings->mail_recipients)) {
                foreach ($this->notificationSettings->mail_recipients as $recipient) {
                    Mail::to($recipient)
                        ->send(new SpeedtestCompletedMail($event->result));
                }
            }
        }

        if ($this->notificationSettings->telegram_enabled) {
            if ($this->notificationSettings->telegram_on_speedtest_run && count($this->notificationSettings->telegram_recipients)) {
                foreach ($this->notificationSettings->telegram_recipients as $recipient) {
                    $download_value = toBits(convertSize($event->result->download), 2).' (Mbps)';

                    $upload_value = toBits(convertSize($event->result->upload), 2).' (Mbps)';

                    $ping_value = number_format($event->result->ping, 2).' (ms)';

                    $message = view('telegram.speedtest-completed', [
                        'id' => $event->result->id,
                        'site_name' => $this->generalSettings->site_name,
                        'ping' => $ping_value,
                        'download' => $download_value,
                        'upload' => $upload_value,
                    ])->render();

                    \Illuminate\Support\Facades\Notification::route('telegram_chat_id', $recipient['telegram_chat_id'])
                        ->notify(new TelegramNotification($message));
                }
            }
        }
    }
}
