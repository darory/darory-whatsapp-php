<?php

namespace Darory\WhatsApp\Tests\Laravel;

use Darory\WhatsApp\Laravel\Events\MessageReceived;
use Darory\WhatsApp\Laravel\Events\WebhookReceived;
use Darory\WhatsApp\Tests\Unit\IncomingMessageTest;
use Illuminate\Support\Facades\Event;

class WebhookTest extends TestCase
{
    public function test_an_incoming_message_fires_an_event(): void
    {
        Event::fake();

        $this->postJson('/darory-whatsapp/webhook?secret=hook-secret', IncomingMessageTest::payload())
            ->assertOk()->assertJson(['status' => 'ok']);

        Event::assertDispatched(MessageReceived::class, fn (MessageReceived $event): bool => $event->message->from === '201012345678'
            && $event->message->body === 'Hello, I need help'
            && $event->message->instanceId === '12');
        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event): bool => $event->event === 'message');
    }

    public function test_the_secret_is_required_when_set(): void
    {
        Event::fake();

        $this->postJson('/darory-whatsapp/webhook', IncomingMessageTest::payload())->assertForbidden();
        $this->postJson('/darory-whatsapp/webhook?secret=wrong', IncomingMessageTest::payload())->assertForbidden();
        $this->postJson('/darory-whatsapp/webhook', IncomingMessageTest::payload(), ['X-Webhook-Secret' => 'hook-secret'])->assertOk();

        Event::assertDispatchedTimes(MessageReceived::class, 1);
    }

    public function test_without_a_secret_the_route_is_open(): void
    {
        config(['darory-whatsapp.webhook.secret' => null]);
        Event::fake();

        $this->postJson('/darory-whatsapp/webhook', IncomingMessageTest::payload())->assertOk();

        Event::assertDispatched(MessageReceived::class);
    }

    public function test_own_messages_are_skipped_unless_asked_for(): void
    {
        Event::fake();
        $payload = IncomingMessageTest::payload(['id' => ['fromMe' => true, 'remote' => '201012345678@c.us', 'id' => 'ME1']]);

        $this->postJson('/darory-whatsapp/webhook?secret=hook-secret', $payload)->assertOk();
        Event::assertNotDispatched(MessageReceived::class);
        Event::assertDispatched(WebhookReceived::class);

        config(['darory-whatsapp.webhook.ignore_own_messages' => false]);
        $this->postJson('/darory-whatsapp/webhook?secret=hook-secret', $payload)->assertOk();
        Event::assertDispatched(MessageReceived::class, fn (MessageReceived $event): bool => $event->message->fromMe);
    }

    public function test_other_events_and_bad_payloads(): void
    {
        Event::fake();

        $this->postJson('/darory-whatsapp/webhook?secret=hook-secret', ['event' => 'status', 'instanceId' => '12', 'data' => ['status' => 'ready']])->assertOk();
        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event): bool => $event->event === 'status' && $event->payload['data']['status'] === 'ready');

        $this->postJson('/darory-whatsapp/webhook?secret=hook-secret', ['hello' => 'world'])->assertUnprocessable();
        $this->postJson('/darory-whatsapp/webhook?secret=hook-secret', ['event' => 'message', 'data' => []])->assertUnprocessable();

        Event::assertNotDispatched(MessageReceived::class);
    }
}
