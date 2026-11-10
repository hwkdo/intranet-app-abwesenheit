<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Notifications;

use Hwkdo\IntranetAppAbwesenheit\IntranetAppAbwesenheit;
use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

abstract class MailboxDelegationNotification extends IntranetNotification
{
    public function __construct(
        public readonly bool $forDelegate,
        public readonly string $ownerName,
        public readonly string $delegateName,
    ) {
        parent::__construct();
    }

    abstract protected function title(): string;

    abstract protected function body(): string;

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->title())
            ->line($this->body());

        $url = $this->actionUrl();

        if ($url !== null) {
            $message->action('Abwesenheit öffnen', $url);
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: $this->title(),
            body: $this->body(),
            url: $this->actionUrl(),
            appIdentifier: IntranetAppAbwesenheit::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        $message = (new WebPushMessage)
            ->title($this->title())
            ->body($this->body());

        $url = $this->actionUrl();

        if ($url !== null) {
            $message->data(['url' => $url]);
        }

        return $message;
    }

    /**
     * @return array{preview: string, topic: string, url?: string}
     */
    public function toTeams(object $notifiable): array
    {
        $payload = [
            'preview' => $this->body(),
            'topic' => IntranetAppAbwesenheit::app_name(),
        ];

        $url = $this->actionUrl();

        if ($url !== null) {
            $payload['url'] = $url;
        }

        return $payload;
    }

    protected function actionUrl(): ?string
    {
        if ($this->forDelegate) {
            return null;
        }

        return route('apps.abwesenheit.meine');
    }
}
