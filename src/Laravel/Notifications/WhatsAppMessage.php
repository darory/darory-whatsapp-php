<?php

namespace Darory\WhatsApp\Laravel\Notifications;

use Darory\WhatsApp\Location;
use Darory\WhatsApp\Media;

/**
 * What a notification's toWhatsApp() returns.
 *
 *     return WhatsAppMessage::create("Hi {$notifiable->name}, your order has shipped.");
 *     return WhatsAppMessage::create()->media(Media::fromPath($invoicePath)->caption('Your invoice'));
 *     return WhatsAppMessage::create('Your order is on its way')->location(Location::make(30.0444, 31.2357, 'Branch'));
 */
class WhatsAppMessage
{
    public ?Media $media = null;

    public ?Location $location = null;

    public ?string $to = null;

    public ?string $instance = null;

    public function __construct(public ?string $content = null) {}

    public static function create(?string $content = null): static
    {
        return new static($content);
    }

    public function content(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Send a file. The text content, if any, becomes its caption.
     */
    public function media(Media $media): static
    {
        $this->media = $media;

        return $this;
    }

    /**
     * Send a location pin. Any text content is sent first, as its own message.
     */
    public function location(Location $location): static
    {
        $this->location = $location;

        return $this;
    }

    /**
     * Send to this number instead of the notifiable's route.
     */
    public function to(string $number): static
    {
        $this->to = $number;

        return $this;
    }

    /**
     * Send from this configured instance instead of the default one.
     */
    public function instance(string $name): static
    {
        $this->instance = $name;

        return $this;
    }
}
