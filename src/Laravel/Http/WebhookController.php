<?php

namespace Darory\WhatsApp\Laravel\Http;

use Darory\WhatsApp\Laravel\Events\MessageReceived;
use Darory\WhatsApp\Laravel\Events\WebhookReceived;
use Darory\WhatsApp\Webhooks\IncomingMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('darory-whatsapp.webhook.secret', '');
        $given = (string) ($request->query('secret') ?? $request->header('X-Webhook-Secret', ''));

        if ($secret !== '' && ! hash_equals($secret, $given)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid webhook secret.'], 403);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;

        if (! is_string($event) || $event === '') {
            return new JsonResponse(['status' => 'error', 'message' => 'Not a Darory WhatsApp webhook.'], 422);
        }

        event(new WebhookReceived($event, $payload));

        if ($event === 'message') {
            try {
                $message = IncomingMessage::fromPayload($payload);
            } catch (InvalidArgumentException $exception) {
                return new JsonResponse(['status' => 'error', 'message' => $exception->getMessage()], 422);
            }

            if (! $message->fromMe || ! config('darory-whatsapp.webhook.ignore_own_messages', true)) {
                event(new MessageReceived($message));
            }
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
