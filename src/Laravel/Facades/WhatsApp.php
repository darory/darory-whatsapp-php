<?php

namespace Darory\WhatsApp\Laravel\Facades;

use Darory\WhatsApp\Contracts\WhatsAppClient;
use Darory\WhatsApp\Laravel\WhatsAppManager;
use Darory\WhatsApp\Location;
use Darory\WhatsApp\Media;
use Darory\WhatsApp\Responses\SentMessage;
use Darory\WhatsApp\Responses\Session;
use Darory\WhatsApp\Testing\FakeWhatsApp;
use Illuminate\Support\Facades\Facade;

/**
 * @method static WhatsAppClient instance(?string $name = null)
 * @method static WhatsAppClient using(string $token, string|int $instanceId)
 * @method static string webhookUrl()
 * @method static SentMessage sendMessage(string $number, string $message)
 * @method static SentMessage sendMedia(string $number, Media $media)
 * @method static SentMessage sendImage(string $number, Media|string $image, ?string $caption = null)
 * @method static SentMessage sendVideo(string $number, Media|string $video, ?string $caption = null)
 * @method static SentMessage sendAudio(string $number, Media|string $audio)
 * @method static SentMessage sendVoiceNote(string $number, Media|string $audio)
 * @method static SentMessage sendDocument(string $number, Media|string $document, ?string $caption = null, ?string $fileName = null)
 * @method static SentMessage sendLocation(string $number, Location|float $latitude, ?float $longitude = null, ?string $name = null, ?string $address = null)
 * @method static bool isRegistered(string $number)
 * @method static string|null profilePictureUrl(string $number)
 * @method static Session session()
 * @method static Session connect()
 * @method static string|null pairingCode(string $number)
 * @method static Session logout()
 * @method static bool deleteChat(string $number)
 *
 * @see WhatsAppManager
 */
class WhatsApp extends Facade
{
    /**
     * Send nothing during the test; assert on what would have been sent.
     */
    public static function fake(): FakeWhatsApp
    {
        return static::getFacadeRoot()->fake();
    }

    protected static function getFacadeAccessor(): string
    {
        return WhatsAppManager::class;
    }
}
