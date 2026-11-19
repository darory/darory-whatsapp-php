<?php

namespace Darory\WhatsApp\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired for every accepted webhook, whatever its event, with the raw body.
 */
class WebhookReceived
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $event,
        public readonly array $payload,
    ) {}
}
