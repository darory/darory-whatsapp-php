<?php

namespace Darory\WhatsApp\Testing;

use ArrayObject;
use Darory\WhatsApp\Concerns\SendsMedia;
use Darory\WhatsApp\Contracts\WhatsAppClient;
use Darory\WhatsApp\Exceptions\NumberNotOnWhatsAppException;
use Darory\WhatsApp\Location;
use Darory\WhatsApp\Media;
use Darory\WhatsApp\Responses\SentMessage;
use Darory\WhatsApp\Responses\Session;
use Darory\WhatsApp\Support\PhoneNumber;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * Stands in for the real client in tests: nothing is sent, every message is recorded.
 *
 *     $whatsapp = WhatsApp::fake();
 *     // ... run your code ...
 *     $whatsapp->assertSentTo('201012345678', fn ($message) => str_contains($message->content(), 'shipped'));
 */
class FakeWhatsApp implements WhatsAppClient
{
    use SendsMedia;

    /**
     * @var ArrayObject<int, RecordedMessage>
     */
    protected ArrayObject $recorded;

    /**
     * @var array<int, string>
     */
    protected array $unregistered = [];

    protected ?Throwable $failure = null;

    protected bool $connected = true;

    public function __construct(protected string $instanceId = 'fake')
    {
        $this->recorded = new ArrayObject;
    }

    public function instanceId(): string
    {
        return $this->instanceId;
    }

    /**
     * Treat these numbers as having no WhatsApp: isRegistered() is false and sending to them throws.
     */
    public function withUnregistered(string ...$numbers): static
    {
        $this->unregistered = array_merge($this->unregistered, array_map(PhoneNumber::normalize(...), $numbers));

        return $this;
    }

    /**
     * Make every send throw this exception, e.g. new SessionNotConnectedException('Session not ready yet', 400).
     */
    public function failWith(?Throwable $exception): static
    {
        $this->failure = $exception;

        return $this;
    }

    /**
     * Report the instance as connected or not from session().
     */
    public function connected(bool $connected = true): static
    {
        $this->connected = $connected;

        return $this;
    }

    public function sendMessage(string $number, string $message): SentMessage
    {
        return $this->record(new RecordedMessage($this->instanceId, PhoneNumber::normalize($number), text: $message));
    }

    public function sendMedia(string $number, Media $media): SentMessage
    {
        return $this->record(new RecordedMessage($this->instanceId, PhoneNumber::normalize($number), media: $media));
    }

    public function sendLocation(string $number, Location|float $latitude, ?float $longitude = null, ?string $name = null, ?string $address = null): SentMessage
    {
        $location = Location::resolve($latitude, $longitude, $name, $address);

        return $this->record(new RecordedMessage($this->instanceId, PhoneNumber::normalize($number), location: $location));
    }

    public function isRegistered(string $number): bool
    {
        return ! in_array(PhoneNumber::normalize($number), $this->unregistered, true);
    }

    public function profilePictureUrl(string $number): ?string
    {
        return null;
    }

    public function session(): Session
    {
        return $this->connected
            ? new Session('connected', '201000000000', 'Fake WhatsApp')
            : new Session('disconnected', qrCode: 'data:image/png;base64,ZmFrZQ==');
    }

    public function connect(): Session
    {
        return $this->session();
    }

    public function pairingCode(string $number): ?string
    {
        return $this->connected ? null : 'FAKE1234';
    }

    public function logout(): Session
    {
        $this->connected = false;

        return $this->session();
    }

    public function deleteChat(string $number): bool
    {
        return true;
    }

    /**
     * A view of this fake for another instance; messages are recorded in the same list.
     */
    public function forInstance(string $instanceId): static
    {
        $copy = clone $this;
        $copy->instanceId = $instanceId;

        return $copy;
    }

    /**
     * The recorded messages, optionally filtered by a callback.
     *
     * @param  (callable(RecordedMessage): bool)|null  $callback
     * @return array<int, RecordedMessage>
     */
    public function sent(?callable $callback = null): array
    {
        $messages = $this->recorded->getArrayCopy();

        return array_values($callback === null ? $messages : array_filter($messages, $callback));
    }

    /**
     * @param  (callable(RecordedMessage): bool)|null  $callback
     */
    public function assertSent(?callable $callback = null): void
    {
        Assert::assertNotEmpty($this->sent($callback), 'The expected WhatsApp message was not sent.');
    }

    /**
     * @param  (callable(RecordedMessage): bool)|null  $callback
     */
    public function assertSentTo(string $number, ?callable $callback = null): void
    {
        $number = PhoneNumber::normalize($number);

        Assert::assertNotEmpty(
            $this->sent(fn (RecordedMessage $message): bool => $message->number === $number && ($callback === null || $callback($message))),
            "The expected WhatsApp message was not sent to [{$number}]."
        );
    }

    public function assertNotSentTo(string $number): void
    {
        $number = PhoneNumber::normalize($number);

        Assert::assertEmpty(
            $this->sent(fn (RecordedMessage $message): bool => $message->number === $number),
            "An unexpected WhatsApp message was sent to [{$number}]."
        );
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent(), "Expected {$count} WhatsApp messages, but ".count($this->sent()).' were sent.');
    }

    public function assertNothingSent(): void
    {
        $this->assertSentCount(0);
    }

    protected function record(RecordedMessage $message): SentMessage
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        if (! $this->isRegistered($message->number)) {
            throw new NumberNotOnWhatsAppException("Number {$message->number} is not on WhatsApp", 400);
        }

        $this->recorded->append($message);
        $id = 'FAKE'.strtoupper(bin2hex(random_bytes(6)));

        return new SentMessage($id, $message->number.'@s.whatsapp.net', time(), ['status' => 'success', 'data' => ['key' => ['id' => $id]]]);
    }
}
