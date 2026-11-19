<?php

namespace Darory\WhatsApp\Laravel\Notifications;

use Darory\WhatsApp\Laravel\WhatsAppManager;
use Darory\WhatsApp\Responses\SentMessage;
use Illuminate\Notifications\Notification;
use LogicException;

/**
 * Laravel notification channel. In via() return 'whatsapp' (or WhatsAppChannel::class),
 * add toWhatsApp($notifiable) to the notification and routeNotificationForWhatsApp() to the model.
 */
class WhatsAppChannel
{
    public function __construct(protected WhatsAppManager $manager) {}

    public function send(object $notifiable, Notification $notification): ?SentMessage
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            throw new LogicException(get_class($notification).' must have a toWhatsApp($notifiable) method to be sent over WhatsApp.');
        }

        $message = $notification->toWhatsApp($notifiable);

        if (is_string($message)) {
            $message = WhatsAppMessage::create($message);
        }

        if (! $message instanceof WhatsAppMessage) {
            return null;
        }

        $number = $message->to ?? $this->routeFor($notifiable, $notification);

        if ($number === null || trim($number) === '') {
            return null;
        }

        $client = $this->manager->instance($message->instance);

        if ($message->media !== null) {
            $media = $message->content !== null && $message->media->getCaption() === null
                ? $message->media->caption($message->content)
                : $message->media;

            return $client->sendMedia($number, $media);
        }

        if ($message->content === null || trim($message->content) === '') {
            return null;
        }

        return $client->sendMessage($number, $message->content);
    }

    protected function routeFor(object $notifiable, Notification $notification): ?string
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $route = $notifiable->routeNotificationFor('whatsapp', $notification)
            ?? $notifiable->routeNotificationFor(self::class, $notification);

        return $route === null ? null : (string) $route;
    }
}
