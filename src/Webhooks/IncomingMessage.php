<?php

namespace Darory\WhatsApp\Webhooks;

use Darory\WhatsApp\Location;
use InvalidArgumentException;

/**
 * A "message" webhook sent by the gateway to your URL.
 *
 *     $message = IncomingMessage::fromPayload(json_decode(file_get_contents('php://input'), true));
 *     if (! $message->fromMe) { reply to $message->from ... }
 */
final class IncomingMessage
{
    /**
     * @param  string  $from  The chat's number (digits). In a group, the group ID digits.
     * @param  string  $author  Who wrote it: the member in a group, the same as $from in a private chat.
     * @param  string  $chatId  The WhatsApp chat ID, e.g. 201012345678@c.us or 1203...@g.us.
     * @param  string  $type  chat, image, video, audio, ptt, document, sticker, contact, location...
     * @param  array<string, mixed>  $payload  The whole webhook body, including the raw WhatsApp message.
     */
    public function __construct(
        public readonly string $instanceId,
        public readonly string $id,
        public readonly string $from,
        public readonly string $author,
        public readonly string $chatId,
        public readonly ?string $body,
        public readonly string $type,
        public readonly bool $fromMe,
        public readonly bool $hasMedia,
        public readonly bool $isForwarded,
        public readonly int $timestamp,
        public readonly array $payload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $message = $payload['data']['message'] ?? null;

        if (($payload['event'] ?? null) !== 'message' || ! is_array($message)) {
            throw new InvalidArgumentException('This is not a Darory WhatsApp "message" webhook.');
        }

        $id = is_array($message['id'] ?? null) ? $message['id'] : [];
        $chatId = (string) ($id['remote'] ?? $message['from'] ?? '');

        return new self(
            instanceId: (string) ($payload['instanceId'] ?? ''),
            id: (string) ($id['id'] ?? ''),
            from: self::digits((string) ($message['from'] ?? $chatId)),
            author: self::digits((string) ($message['to'] ?? $message['from'] ?? $chatId)),
            chatId: $chatId,
            body: isset($message['body']) && is_scalar($message['body']) ? (string) $message['body'] : null,
            type: (string) ($message['type'] ?? 'unknown'),
            fromMe: (bool) ($id['fromMe'] ?? false),
            hasMedia: (bool) ($message['hasMedia'] ?? false),
            isForwarded: (bool) ($message['isForwarded'] ?? false),
            timestamp: (int) ($message['timestamp'] ?? time()),
            payload: $payload,
        );
    }

    /**
     * Whether the message was sent in a group chat.
     */
    public function isGroup(): bool
    {
        return str_ends_with($this->chatId, '@g.us');
    }

    /**
     * A plain text message (not media, a sticker, a location...).
     */
    public function isText(): bool
    {
        return $this->type === 'chat' && $this->body !== null && $this->body !== '';
    }

    public function isLocation(): bool
    {
        return $this->type === 'location';
    }

    /**
     * The pin of a "location" message (with its name and address when the sender picked a place), or null.
     */
    public function location(): ?Location
    {
        if (! $this->isLocation()) {
            return null;
        }

        $message = $this->raw()['message'] ?? [];
        $message = $message['ephemeralMessage']['message'] ?? $message['viewOnceMessage']['message'] ?? $message['viewOnceMessageV2']['message'] ?? $message;
        $pin = $message['locationMessage'] ?? $message['liveLocationMessage'] ?? null;

        if (is_array($pin) && is_numeric($pin['degreesLatitude'] ?? null) && is_numeric($pin['degreesLongitude'] ?? null)) {
            return $this->safeLocation((float) $pin['degreesLatitude'], (float) $pin['degreesLongitude'], is_string($pin['name'] ?? null) ? $pin['name'] : null, is_string($pin['address'] ?? null) ? $pin['address'] : null);
        }

        // Fall back to the "latitude, longitude" body
        $parts = array_map('trim', explode(',', (string) $this->body));

        return count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])
            ? $this->safeLocation((float) $parts[0], (float) $parts[1])
            : null;
    }

    /**
     * A Location, or null when the coordinates are out of range, so a malformed webhook never throws.
     */
    protected function safeLocation(float $latitude, float $longitude, ?string $name = null, ?string $address = null): ?Location
    {
        try {
            return Location::make($latitude, $longitude, $name, $address);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The original WhatsApp message, for fields this class doesn't map.
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        $raw = $this->payload['data']['message']['raw'] ?? [];

        return is_array($raw) ? $raw : [];
    }

    private static function digits(string $jid): string
    {
        return preg_replace('/\D+/', '', preg_replace('/@.*$/', '', $jid) ?? '') ?? '';
    }
}
