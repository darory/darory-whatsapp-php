<?php

namespace Darory\WhatsApp\Laravel\Events;

use Darory\WhatsApp\Webhooks\IncomingMessage;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when the webhook route receives a message. Listen to it to reply, log or save chats.
 */
class MessageReceived
{
    use Dispatchable;

    public function __construct(public readonly IncomingMessage $message) {}
}
