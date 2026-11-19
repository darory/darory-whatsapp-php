<?php

namespace Darory\WhatsApp\Responses;

/**
 * The connection state of an instance. When connected it holds the account details;
 * when not, a QR code (a "data:image/png;base64,..." URI you can put straight in an <img src>).
 */
final class Session
{
    /**
     * @param  array<string, mixed>  $raw  The full gateway response.
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $number = null,
        public readonly ?string $name = null,
        public readonly ?string $profilePictureUrl = null,
        public readonly ?string $qrCode = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): self
    {
        $instance = is_array($response['instance'] ?? null) ? $response['instance'] : [];
        $status = (string) ($response['status'] ?? ($instance === [] ? 'disconnected' : 'connected'));
        $number = isset($instance['number']) ? preg_replace('/@.*$/', '', (string) $instance['number']) : null;

        return new self(
            $status,
            $number !== '' ? $number : null,
            isset($instance['name']) ? (string) $instance['name'] : null,
            isset($instance['profile_pic']) && $instance['profile_pic'] !== '' ? (string) $instance['profile_pic'] : null,
            isset($response['qr']) && $response['qr'] !== '' ? (string) $response['qr'] : null,
            $response,
        );
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    /**
     * Whether there is a QR code waiting to be scanned.
     */
    public function needsScan(): bool
    {
        return ! $this->isConnected() && $this->qrCode !== null;
    }
}
