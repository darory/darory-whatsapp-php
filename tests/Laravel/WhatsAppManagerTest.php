<?php

namespace Darory\WhatsApp\Tests\Laravel;

use Darory\WhatsApp\Contracts\WhatsAppClient;
use Darory\WhatsApp\DaroryWhatsApp;
use Darory\WhatsApp\Exceptions\NumberNotOnWhatsAppException;
use Darory\WhatsApp\Exceptions\SessionNotConnectedException;
use Darory\WhatsApp\Laravel\Facades\WhatsApp;
use Darory\WhatsApp\Laravel\WhatsAppManager;
use Darory\WhatsApp\Media;
use Darory\WhatsApp\Testing\RecordedMessage;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\AssertionFailedError;

class WhatsAppManagerTest extends TestCase
{
    public function test_configured_instances_are_resolved_by_name(): void
    {
        $this->assertInstanceOf(DaroryWhatsApp::class, WhatsApp::instance());
        $this->assertSame('12', WhatsApp::instance()->instanceId());
        $this->assertSame('34', WhatsApp::instance('sales')->instanceId());
        $this->assertSame(WhatsApp::instance('sales'), WhatsApp::instance('sales'));
        $this->assertSame('12', app(WhatsAppClient::class)->instanceId());
        $this->assertSame('99', WhatsApp::using('tenant-token', 99)->instanceId());
    }

    public function test_a_missing_or_incomplete_instance_has_a_clear_error(): void
    {
        try {
            WhatsApp::instance('support');
            $this->fail('An unknown instance was resolved.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('[support] is not configured', $exception->getMessage());
        }

        config(['darory-whatsapp.instances.default.token' => null]);
        $this->app->forgetInstance(WhatsAppManager::class);
        WhatsApp::clearResolvedInstances();

        $this->expectExceptionMessage('DARORY_WA_TOKEN');
        WhatsApp::instance();
    }

    public function test_calls_on_the_facade_go_to_the_default_instance(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['status' => 'success', 'data' => ['key' => ['id' => 'ABC']]])),
        ]));
        $stack->push(Middleware::history($history));
        $this->app->instance(WhatsAppManager::class, new WhatsAppManager(config('darory-whatsapp'), new Client(['handler' => $stack])));

        $this->assertSame('ABC', WhatsApp::sendMessage('201012345678', 'Hello')->id());
        $this->assertSame('12', $history[0]['request']->getHeaderLine('X-INSTANCE-ID'));
        $this->assertSame('Bearer main-token', $history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame('https://wa.darory.com/wa-api/send-message', (string) $history[0]['request']->getUri());
    }

    public function test_the_fake_records_messages_from_every_instance(): void
    {
        $fake = WhatsApp::fake();

        WhatsApp::sendMessage('+20 101 234 5678', 'Your order has shipped');
        WhatsApp::instance('sales')->sendImage('201099999999', 'https://example.com/offer.jpg', 'Offer');
        WhatsApp::using('tenant-token', 77)->sendMessage('201011111111', 'Hi');

        $fake->assertSentCount(3);
        $fake->assertSentTo('201012345678', fn (RecordedMessage $message): bool => $message->content() === 'Your order has shipped' && $message->instanceId === 'default');
        $fake->assertSentTo('201099999999', fn (RecordedMessage $message): bool => $message->isMedia() && $message->media->type() === Media::TYPE_IMAGE && $message->instanceId === 'sales');
        $fake->assertSent(fn (RecordedMessage $message): bool => $message->instanceId === '77');
        $fake->assertNotSentTo('201000000000');

        $this->expectException(AssertionFailedError::class);
        $fake->assertSentTo('201000000000');
    }

    public function test_the_fake_records_seen_and_typing(): void
    {
        $fake = WhatsApp::fake();

        WhatsApp::markAsSeen('+20 101 234 5678', 'MSG1');
        WhatsApp::markAsSeen('120363000000000000@g.us');
        WhatsApp::sendTyping('201012345678', recording: true);

        $fake->assertMarkedAsSeen('201012345678', 'MSG1');
        $fake->assertMarkedAsSeen('120363000000000000@g.us', 'ANY'); // the whole chat was marked
        $fake->assertTyping('201012345678', recording: true);
        $fake->assertNothingSent();

        $this->expectException(AssertionFailedError::class);
        $fake->assertTyping('201012345678');
    }

    public function test_the_fake_can_simulate_failures(): void
    {
        $fake = WhatsApp::fake()->withUnregistered('201000000000');

        $this->assertFalse(WhatsApp::isRegistered('201000000000'));
        $this->assertTrue(WhatsApp::isRegistered('201012345678'));

        try {
            WhatsApp::sendMessage('201000000000', 'Hi');
            $this->fail('Sending to an unregistered number did not throw.');
        } catch (NumberNotOnWhatsAppException) {
            $fake->assertNothingSent();
        }

        $fake->failWith(new SessionNotConnectedException('Session not ready yet', 400));
        $this->expectException(SessionNotConnectedException::class);
        WhatsApp::sendMessage('201012345678', 'Hi');
    }

    public function test_the_check_command_reports_the_connection_and_webhook_url(): void
    {
        $this->artisan('darory-whatsapp:check', ['instance' => 'missing'])
            ->expectsOutputToContain('[missing] is not configured')
            ->assertFailed();

        WhatsApp::fake();

        $this->artisan('darory-whatsapp:check')
            ->expectsOutputToContain('connected')
            ->expectsOutputToContain('darory-whatsapp/webhook?secret=hook-secret')
            ->assertSuccessful();
    }
}
