<?php

namespace Darory\WhatsApp\Contracts;

use Darory\WhatsApp\Location;
use Darory\WhatsApp\Media;
use Darory\WhatsApp\Responses\SentMessage;
use Darory\WhatsApp\Responses\Session;

/**
 * Everything one Darory WhatsApp instance (one connected number) can do.
 */
interface WhatsAppClient
{
    /**
     * The instance ID sent in the X-INSTANCE-ID header.
     */
    public function instanceId(): string;

    /**
     * Send a text message.
     */
    public function sendMessage(string $number, string $message): SentMessage;

    /**
     * Send an image, video, audio file, voice note or document.
     */
    public function sendMedia(string $number, Media $media): SentMessage;

    /**
     * Send a location pin, optionally with a place name and address.
     */
    public function sendLocation(string $number, Location|float $latitude, ?float $longitude = null, ?string $name = null, ?string $address = null): SentMessage;

    /**
     * Whether the number has a WhatsApp account.
     */
    public function isRegistered(string $number): bool;

    /**
     * The public profile picture URL of a number, or null when it has none or hides it.
     */
    public function profilePictureUrl(string $number): ?string;

    /**
     * The connection status, with the account details when connected or a QR code when not.
     */
    public function session(): Session;

    /**
     * Start the session if needed and return a QR code to scan, or the account details when already connected.
     */
    public function connect(): Session;

    /**
     * Get an 8 character code to link the number from WhatsApp > Linked devices > Link with phone number.
     * Returns null when the instance is already connected.
     */
    public function pairingCode(string $number): ?string;

    /**
     * Log the number out of WhatsApp. The returned session holds a fresh QR code.
     */
    public function logout(): Session;

    /**
     * Delete the whole chat with a number from the connected phone.
     */
    public function deleteChat(string $number): bool;
}
