<?php

namespace Darory\WhatsApp\Responses;

/**
 * A message the gateway accepted. Keep id() to match it with later webhook events.
 */
final class SentMessage
{
    /**
     * @param  array<string, mixed>  $raw  The full gateway response.
     */
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $to,
        public readonly ?int $timestamp,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): self
    {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $key = is_array($data['key'] ?? null) ? $data['key'] : [];
        $id = $key['id'] ?? ($data['id']['id'] ?? ($data['id'] ?? null));
        $timestamp = $data['messageTimestamp'] ?? ($data['timestamp'] ?? null);

        return new self(
            is_scalar($id) ? (string) $id : null,
            isset($key['remoteJid']) ? (string) $key['remoteJid'] : null,
            is_numeric($timestamp) ? (int) $timestamp : null,
            $response,
        );
    }

    public function id(): ?string
    {
        return $this->id;
    }
}
