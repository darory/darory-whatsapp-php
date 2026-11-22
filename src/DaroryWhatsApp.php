<?php

namespace Darory\WhatsApp;

use Darory\WhatsApp\Concerns\SendsMedia;
use Darory\WhatsApp\Contracts\WhatsAppClient;
use Darory\WhatsApp\Exceptions\ConnectionException;
use Darory\WhatsApp\Exceptions\DaroryWhatsAppException;
use Darory\WhatsApp\Responses\SentMessage;
use Darory\WhatsApp\Responses\Session;
use Darory\WhatsApp\Support\PhoneNumber;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;

/**
 * Client for one Darory WhatsApp instance.
 *
 *     $whatsapp = new DaroryWhatsApp(token: getenv('DARORY_WA_TOKEN'), instanceId: '12');
 *     $whatsapp->sendMessage('201012345678', 'Your order has shipped');
 */
class DaroryWhatsApp implements WhatsAppClient
{
    use SendsMedia;

    public const DEFAULT_BASE_URL = 'https://wa.darory.com/wa-api';

    public const MAX_TYPING_SECONDS = 60;

    protected string $instanceId;

    protected ClientInterface $http;

    /**
     * @param  string  $token  The instance API token from the Darory client area. Keep it secret.
     * @param  string|int  $instanceId  The instance ID (X-INSTANCE-ID).
     * @param  string  $baseUrl  The gateway URL.
     * @param  float  $timeout  Seconds to wait for a response. Sending a large file can take a while.
     * @param  ClientInterface|null  $http  A Guzzle client to use instead of the default one (proxies, middleware, tests).
     */
    public function __construct(
        protected string $token,
        string|int $instanceId,
        protected string $baseUrl = self::DEFAULT_BASE_URL,
        protected float $timeout = 60.0,
        ?ClientInterface $http = null,
    ) {
        if (trim($token) === '') {
            throw new InvalidArgumentException('The Darory WhatsApp API token is empty.');
        }

        $this->instanceId = trim((string) $instanceId);

        if ($this->instanceId === '') {
            throw new InvalidArgumentException('The Darory WhatsApp instance ID is empty.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->http = $http ?? new GuzzleClient;
    }

    public function instanceId(): string
    {
        return $this->instanceId;
    }

    public function sendMessage(string $number, string $message): SentMessage
    {
        if (trim($message) === '') {
            throw new InvalidArgumentException('The message is empty.');
        }

        $response = $this->request('POST', '/send-message', [
            'json' => ['number' => PhoneNumber::normalize($number), 'message' => $message],
        ]);

        return SentMessage::fromResponse($response);
    }

    public function sendMedia(string $number, Media $media): SentMessage
    {
        $response = $this->request('POST', '/send-media', $media->toRequestOptions(PhoneNumber::normalize($number)));

        return SentMessage::fromResponse($response);
    }

    public function sendLocation(string $number, Location|float $latitude, ?float $longitude = null, ?string $name = null, ?string $address = null): SentMessage
    {
        $location = Location::resolve($latitude, $longitude, $name, $address);

        $response = $this->request('POST', '/send-location', [
            'json' => ['number' => PhoneNumber::normalize($number), ...$location->toArray()],
        ]);

        return SentMessage::fromResponse($response);
    }

    public function markAsSeen(string $chat, string|array|null $messageIds = null): array
    {
        $payload = ['number' => PhoneNumber::chat($chat)];
        $ids = self::messageIds($messageIds);

        if ($ids !== []) {
            $payload['messageIds'] = $ids;
        }

        $response = $this->request('POST', '/mark-as-seen', ['json' => $payload]);

        return array_values(array_map('strval', (array) ($response['data']['marked'] ?? [])));
    }

    public function sendTyping(string $chat, int|float $seconds = 5, bool $recording = false): void
    {
        if ($seconds < 0 || $seconds > self::MAX_TYPING_SECONDS) {
            throw new InvalidArgumentException('The typing duration must be between 0 and '.self::MAX_TYPING_SECONDS.' seconds.');
        }

        $this->request('POST', '/send-typing', [
            'json' => ['number' => PhoneNumber::chat($chat), 'state' => $recording ? 'recording' : 'typing', 'duration' => $seconds],
        ]);
    }

    public function stopTyping(string $chat): void
    {
        $this->request('POST', '/send-typing', [
            'json' => ['number' => PhoneNumber::chat($chat), 'state' => 'paused'],
        ]);
    }

    public function react(string $chat, string $messageId, string $emoji, ?string $participant = null, bool $fromMe = false): void
    {
        $this->sendReaction($chat, $messageId, self::emoji($emoji), $participant, $fromMe);
    }

    public function removeReaction(string $chat, string $messageId, ?string $participant = null, bool $fromMe = false): void
    {
        $this->sendReaction($chat, $messageId, null, $participant, $fromMe);
    }

    protected function sendReaction(string $chat, string $messageId, ?string $emoji, ?string $participant, bool $fromMe): void
    {
        $payload = ['number' => PhoneNumber::chat($chat), 'messageId' => self::messageId($messageId)];
        $payload += $emoji === null ? ['remove' => true] : ['emoji' => $emoji];

        if ($participant !== null && trim($participant) !== '') {
            $payload['participant'] = PhoneNumber::chat($participant);
        }

        if ($fromMe) {
            $payload['fromMe'] = true;
        }

        $this->request('POST', '/send-reaction', ['json' => $payload]);
    }

    public static function messageId(string $messageId): string
    {
        $messageId = trim($messageId);

        if ($messageId === '') {
            throw new InvalidArgumentException('The message ID is required.');
        }

        return $messageId;
    }

    /**
     * A loose emoji check matching the gateway: not empty, no letters or spaces.
     */
    public static function emoji(string $emoji): string
    {
        $emoji = trim($emoji);

        if ($emoji === '' || strlen($emoji) > 64 || preg_match('/[\p{L}\s]/u', $emoji) !== 0) {
            throw new InvalidArgumentException('The reaction must be a single emoji, e.g. 👍. Use removeReaction() to remove one.');
        }

        return $emoji;
    }

    /**
     * @param  string|array<int, mixed>|null  $messageIds
     * @return array<int, string>
     */
    public static function messageIds(string|array|null $messageIds): array
    {
        $ids = array_map(fn ($id): string => trim((string) $id), (array) $messageIds);

        return array_values(array_unique(array_filter($ids, fn (string $id): bool => $id !== '')));
    }

    public function isRegistered(string $number): bool
    {
        $response = $this->request('POST', '/is-registered-user', [
            'json' => ['number' => PhoneNumber::normalize($number)],
        ]);

        return (bool) ($response['data']['data']['isRegisteredUser'] ?? false);
    }

    public function profilePictureUrl(string $number): ?string
    {
        $response = $this->request('POST', '/get-profile-pic-url', [
            'json' => ['number' => PhoneNumber::normalize($number)],
        ]);

        $url = $response['data']['data']['profilePicUrl'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function session(): Session
    {
        return Session::fromResponse($this->request('GET', '/session'));
    }

    public function connect(): Session
    {
        return Session::fromResponse($this->request('POST', '/qr'));
    }

    public function pairingCode(string $number): ?string
    {
        $response = $this->request('POST', '/pair-code', [
            'json' => ['number' => PhoneNumber::normalize($number)],
        ]);

        $code = $response['pairingCode'] ?? null;

        return is_string($code) && $code !== '' ? $code : null;
    }

    public function logout(): Session
    {
        return Session::fromResponse($this->request('POST', '/logout'));
    }

    public function deleteChat(string $number): bool
    {
        $response = $this->request('POST', '/delete-chat-by-id', [
            'json' => ['chatId' => str_ends_with($number, '@g.us') ? $number : PhoneNumber::normalize($number)],
        ]);

        return (bool) ($response['data']['deleted'] ?? false);
    }

    /**
     * Send a request to the gateway and return the decoded JSON body.
     *
     * @param  array<string, mixed>  $options  Guzzle request options.
     * @return array<string, mixed>
     *
     * @throws DaroryWhatsAppException
     */
    protected function request(string $method, string $path, array $options = []): array
    {
        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer '.$this->token,
            'X-INSTANCE-ID' => $this->instanceId,
            'Accept' => 'application/json',
        ]);
        $options['http_errors'] = false;
        $options['timeout'] ??= $this->timeout;
        $options['connect_timeout'] ??= 10;

        try {
            $response = $this->http->request($method, $this->baseUrl.$path, $options);
        } catch (GuzzleException $exception) {
            throw new ConnectionException('Could not reach the Darory WhatsApp API: '.$exception->getMessage(), 0, [], $exception);
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);
        $body = is_array($body) ? $body : [];

        if ($status >= 400 || ($body['status'] ?? null) === 'error') {
            throw DaroryWhatsAppException::fromResponse($status, $body);
        }

        return $body;
    }
}
