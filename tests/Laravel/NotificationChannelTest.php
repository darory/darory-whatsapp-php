<?php

namespace Darory\WhatsApp\Tests\Laravel;

use Darory\WhatsApp\Laravel\Facades\WhatsApp;
use Darory\WhatsApp\Laravel\Notifications\WhatsAppChannel;
use Darory\WhatsApp\Laravel\Notifications\WhatsAppMessage;
use Darory\WhatsApp\Location;
use Darory\WhatsApp\Media;
use Darory\WhatsApp\Testing\RecordedMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationChannelTest extends TestCase
{
    public function test_a_notification_is_sent_to_the_notifiable_number(): void
    {
        $fake = WhatsApp::fake();

        (new Customer('+20 101 234 5678'))->notify(new OrderShipped(WhatsAppMessage::create('Your order has shipped')));

        $fake->assertSentTo('201012345678', fn (RecordedMessage $message): bool => $message->text === 'Your order has shipped' && $message->instanceId === 'default');
    }

    public function test_a_file_can_be_sent_from_another_instance_with_the_text_as_caption(): void
    {
        $fake = WhatsApp::fake();

        (new Customer('201012345678'))->notify(new OrderShipped(
            WhatsAppMessage::create('Your invoice')->media(Media::fromContents('PDF', 'invoice.pdf'))->instance('sales')
        ));

        $fake->assertSentTo('201012345678', fn (RecordedMessage $message): bool => $message->isMedia()
            && $message->content() === 'Your invoice'
            && $message->media->type() === Media::TYPE_DOCUMENT
            && $message->instanceId === 'sales');
    }

    public function test_a_location_is_sent_after_the_text(): void
    {
        $fake = WhatsApp::fake();

        (new Customer('201012345678'))->notify(new OrderShipped(
            WhatsAppMessage::create('Pick up your order here')->location(Location::make(30.0444, 31.2357, 'Darory Office'))
        ));

        $sent = $fake->sent();
        $this->assertCount(2, $sent);
        $this->assertSame('Pick up your order here', $sent[0]->text);
        $this->assertTrue($sent[1]->isLocation());
        $this->assertSame('Darory Office', $sent[1]->content());
        $this->assertSame(31.2357, $sent[1]->location->longitude);

        // A pin on its own
        (new Customer('201099999999'))->notify(new OrderShipped(WhatsAppMessage::create()->location(Location::make(1.5, 2.5))));
        $fake->assertSentTo('201099999999', fn (RecordedMessage $message): bool => $message->isLocation() && $message->content() === null);
        $fake->assertSentCount(3);
    }

    public function test_on_demand_routes_and_the_channel_class_name_work(): void
    {
        $fake = WhatsApp::fake();

        NotificationFacade::route('whatsapp', '201011111111')->notify(new OrderShipped('A plain string works too'));
        NotificationFacade::route(WhatsAppChannel::class, '201022222222')->notify(new OrderShipped('Hi', WhatsAppChannel::class));

        $fake->assertSentTo('201011111111', fn (RecordedMessage $message): bool => $message->text === 'A plain string works too');
        $fake->assertSentTo('201022222222');
    }

    public function test_nothing_is_sent_without_a_number_or_content(): void
    {
        $fake = WhatsApp::fake();

        (new Customer(null))->notify(new OrderShipped('Hi'));
        (new Customer('201012345678'))->notify(new OrderShipped(WhatsAppMessage::create('  ')));

        $fake->assertNothingSent();
    }
}

class Customer
{
    use Notifiable;

    public function __construct(public ?string $phone) {}

    public function routeNotificationForWhatsApp(): ?string
    {
        return $this->phone;
    }
}

class OrderShipped extends Notification
{
    public function __construct(
        protected WhatsAppMessage|string $message,
        protected string $channel = 'whatsapp',
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [$this->channel];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage|string
    {
        return $this->message;
    }
}
