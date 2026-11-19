<?php

namespace Darory\WhatsApp\Tests\Unit;

use Darory\WhatsApp\DaroryWhatsApp;
use Darory\WhatsApp\Exceptions\AuthenticationException;
use Darory\WhatsApp\Exceptions\ConnectionException;
use Darory\WhatsApp\Exceptions\DaroryWhatsAppException;
use Darory\WhatsApp\Exceptions\FileTooLargeException;
use Darory\WhatsApp\Exceptions\InvalidRequestException;
use Darory\WhatsApp\Exceptions\NumberNotOnWhatsAppException;
use Darory\WhatsApp\Exceptions\SessionNotConnectedException;
use Darory\WhatsApp\Exceptions\SessionNotFoundException;
use Darory\WhatsApp\Media;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class DaroryWhatsAppTest extends TestCase
{
    /**
     * @var array<int, array{request: RequestInterface}>
     */
    protected array $history = [];

    /**
     * @param  array<int, Response|\Throwable>  $responses
     */
    protected function client(array $responses): DaroryWhatsApp
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new DaroryWhatsApp('secret-token', 12, 'https://wa.example.test/wa-api/', http: new Client(['handler' => $stack]));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected static function json(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    protected function lastRequest(): RequestInterface
    {
        return end($this->history)['request'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function lastJson(): array
    {
        return json_decode((string) $this->lastRequest()->getBody(), true);
    }

    public function test_it_sends_a_text_message_with_the_auth_headers(): void
    {
        $sent = $this->client([self::json([
            'status' => 'success',
            'instanceId' => '12',
            'data' => ['key' => ['remoteJid' => '201012345678@s.whatsapp.net', 'fromMe' => true, 'id' => 'BAE5D8F2A34B'], 'messageTimestamp' => '1710000000'],
        ])])->sendMessage('+20 101 234 5678', 'Your order has shipped');

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://wa.example.test/wa-api/send-message', (string) $request->getUri());
        $this->assertSame('Bearer secret-token', $request->getHeaderLine('Authorization'));
        $this->assertSame('12', $request->getHeaderLine('X-INSTANCE-ID'));
        $this->assertSame(['number' => '201012345678', 'message' => 'Your order has shipped'], $this->lastJson());

        $this->assertSame('BAE5D8F2A34B', $sent->id());
        $this->assertSame('201012345678@s.whatsapp.net', $sent->to);
        $this->assertSame(1710000000, $sent->timestamp);
    }

    public function test_an_empty_message_or_bad_number_is_refused_before_any_request(): void
    {
        $client = $this->client([]);

        try {
            $client->sendMessage('201012345678', '   ');
            $this->fail('An empty message was accepted.');
        } catch (InvalidArgumentException) {
        }

        try {
            $client->sendMessage('12345', 'Hi');
            $this->fail('A too short number was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('country code', $exception->getMessage());
        }

        $this->assertSame([], $this->history);
    }

    public function test_the_credentials_are_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DaroryWhatsApp('', 12);
    }

    public function test_it_uploads_a_local_file_as_multipart(): void
    {
        $this->client([self::json(['status' => 'success', 'data' => ['key' => ['id' => 'DOC1']]])])
            ->sendDocument('201012345678', __DIR__.'/../fixtures/invoice.pdf', 'Your invoice');

        $request = $this->lastRequest();
        $body = (string) $request->getBody();

        $this->assertSame('https://wa.example.test/wa-api/send-media', (string) $request->getUri());
        $this->assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
        $this->assertMatchesRegularExpression('/name="number"\r\nContent-Length: 12\r\n\r\n201012345678/', $body);
        $this->assertMatchesRegularExpression('/name="type"\r\nContent-Length: 8\r\n\r\ndocument/', $body);
        $this->assertStringContainsString('Your invoice', $body);
        $this->assertStringContainsString('name="file"; filename="invoice.pdf"', $body);
        $this->assertStringContainsString('Content-Type: application/pdf', $body);
        $this->assertStringContainsString('% fake invoice', $body);
    }

    public function test_it_sends_a_url_or_base64_as_json(): void
    {
        $client = $this->client([
            self::json(['status' => 'success', 'data' => ['key' => ['id' => 'IMG1']]]),
            self::json(['status' => 'success', 'data' => ['key' => ['id' => 'DOC2']]]),
        ]);

        $client->sendImage('201012345678', 'https://example.com/images/offer.jpg', 'Our offer');
        $this->assertSame([
            'number' => '201012345678',
            'type' => 'image',
            'caption' => 'Our offer',
            'fileName' => 'offer.jpg',
            'mimetype' => 'image/jpeg',
            'url' => 'https://example.com/images/offer.jpg',
        ], $this->lastJson());

        $client->sendMedia('201012345678', Media::fromBase64('data:application/pdf;base64,JVBERi0=', 'invoice.pdf'));
        $this->assertSame([
            'number' => '201012345678',
            'type' => 'document',
            'fileName' => 'invoice.pdf',
            'mimetype' => 'application/pdf',
            'base64' => 'data:application/pdf;base64,JVBERi0=',
        ], $this->lastJson());
    }

    public function test_a_voice_note_is_sent_as_ptt_audio_without_a_caption(): void
    {
        $this->client([self::json(['status' => 'success', 'data' => ['key' => ['id' => 'PTT1']]])])
            ->sendMedia('201012345678', Media::fromUrl('https://example.com/voice.ogg')->caption('ignored')->asVoiceNote());

        $this->assertSame([
            'number' => '201012345678',
            'type' => 'audio',
            'fileName' => 'voice.ogg',
            'mimetype' => 'audio/ogg',
            'ptt' => true,
            'url' => 'https://example.com/voice.ogg',
        ], $this->lastJson());
    }

    public function test_it_checks_numbers_and_profile_pictures(): void
    {
        $client = $this->client([
            self::json(['status' => 'success', 'data' => ['status' => 'success', 'data' => ['isRegisteredUser' => true]]]),
            self::json(['status' => 'success', 'data' => ['status' => 'success', 'data' => ['isRegisteredUser' => false]]]),
            self::json(['status' => 'success', 'data' => ['status' => 'success', 'data' => ['profilePicUrl' => 'https://pps.whatsapp.net/pic.jpg']]]),
            self::json(['status' => 'success', 'data' => ['status' => 'success', 'data' => ['profilePicUrl' => null]]]),
        ]);

        $this->assertTrue($client->isRegistered('201012345678'));
        $this->assertSame(['number' => '201012345678'], $this->lastJson());
        $this->assertFalse($client->isRegistered('201099999999'));
        $this->assertSame('https://pps.whatsapp.net/pic.jpg', $client->profilePictureUrl('201012345678'));
        $this->assertNull($client->profilePictureUrl('201012345678'));
    }

    public function test_it_reads_the_session_status(): void
    {
        $client = $this->client([
            self::json(['session' => '12', 'status' => 'connected', 'instance' => [
                'instance_id' => '12', 'current_status' => 'ready', 'number' => '201000000000@c.us', 'name' => 'Darory', 'profile_pic' => 'https://pps.whatsapp.net/me.jpg',
            ]]),
            self::json(['session' => '12', 'status' => 'disconnected', 'qr' => 'data:image/png;base64,iVBOR']),
        ]);

        $session = $client->session();
        $this->assertSame('GET', $this->lastRequest()->getMethod());
        $this->assertTrue($session->isConnected());
        $this->assertSame('201000000000', $session->number);
        $this->assertSame('Darory', $session->name);
        $this->assertSame('https://pps.whatsapp.net/me.jpg', $session->profilePictureUrl);
        $this->assertFalse($session->needsScan());

        $session = $client->session();
        $this->assertFalse($session->isConnected());
        $this->assertTrue($session->needsScan());
        $this->assertSame('data:image/png;base64,iVBOR', $session->qrCode);
    }

    public function test_connect_returns_a_qr_code_and_pairing_returns_a_code(): void
    {
        $client = $this->client([
            self::json(['qr' => 'data:image/png;base64,QR']),
            self::json(['status' => 'success', 'pairingCode' => 'ABCD1234']),
            self::json(['status' => 'connected', 'message' => 'Session is already connected']),
            self::json(['session' => '12', 'status' => 'logged out', 'qr' => 'data:image/png;base64,NEW']),
            self::json(['status' => 'success', 'data' => ['chatId' => '201012345678@c.us', 'deleted' => true]]),
        ]);

        $session = $client->connect();
        $this->assertSame('https://wa.example.test/wa-api/qr', (string) $this->lastRequest()->getUri());
        $this->assertTrue($session->needsScan());

        $this->assertSame('ABCD1234', $client->pairingCode('+201012345678'));
        $this->assertSame(['number' => '201012345678'], $this->lastJson());
        $this->assertNull($client->pairingCode('201012345678'));

        $session = $client->logout();
        $this->assertSame('logged out', $session->status);
        $this->assertSame('data:image/png;base64,NEW', $session->qrCode);

        $this->assertTrue($client->deleteChat('201012345678'));
        $this->assertSame(['chatId' => '201012345678'], $this->lastJson());
    }

    /**
     * @return array<string, array{0: int, 1: array<string, mixed>, 2: class-string<DaroryWhatsAppException>}>
     */
    public static function errors(): array
    {
        return [
            'wrong token' => [401, ['error' => 'Unauthorized token'], AuthenticationException::class],
            'unknown instance' => [404, ['error' => 'Instance 12 not found'], SessionNotFoundException::class],
            'session gone' => [404, ['status' => 'error', 'message' => 'Session not found'], SessionNotFoundException::class],
            'not connected' => [400, ['status' => 'error', 'message' => 'Session not ready yet'], SessionNotConnectedException::class],
            'no whatsapp' => [400, ['status' => 'error', 'message' => 'Number 201012345678 is not on WhatsApp'], NumberNotOnWhatsAppException::class],
            'too large' => [413, ['status' => 'error', 'message' => 'File too large (max 50 MB)'], FileTooLargeException::class],
            'missing field' => [400, ['status' => 'error', 'message' => 'session, number, and message are required'], InvalidRequestException::class],
            'server error' => [500, ['status' => 'error', 'message' => 'Internal server error'], DaroryWhatsAppException::class],
            'error with 200' => [200, ['status' => 'error', 'message' => 'Something failed'], DaroryWhatsAppException::class],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  class-string<DaroryWhatsAppException>  $expected
     */
    #[DataProvider('errors')]
    public function test_errors_become_specific_exceptions(int $status, array $body, string $expected): void
    {
        try {
            $this->client([self::json($body, $status)])->sendMessage('201012345678', 'Hi');
            $this->fail('No exception was thrown.');
        } catch (DaroryWhatsAppException $exception) {
            $this->assertSame($expected, $exception::class);
            $this->assertSame($status, $exception->statusCode());
            $this->assertSame($body, $exception->response());
            $this->assertSame($body['message'] ?? $body['error'], $exception->getMessage());
        }
    }

    public function test_a_network_failure_is_a_connection_exception(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Could not reach the Darory WhatsApp API');

        $this->client([new ConnectException('Connection refused', new Request('POST', 'https://wa.example.test'))])
            ->sendMessage('201012345678', 'Hi');
    }
}
