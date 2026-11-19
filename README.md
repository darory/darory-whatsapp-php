# Darory WhatsApp for PHP & Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/darory/whatsapp.svg)](https://packagist.org/packages/darory/whatsapp)
[![Total Downloads](https://img.shields.io/packagist/dt/darory/whatsapp.svg)](https://packagist.org/packages/darory/whatsapp)
[![PHP Version](https://img.shields.io/packagist/dependency-v/darory/whatsapp/php.svg)](https://packagist.org/packages/darory/whatsapp)
[![License](https://img.shields.io/packagist/l/darory/whatsapp.svg)](LICENSE)

Send WhatsApp messages and files, check numbers, connect sessions and receive incoming messages through the [Darory WhatsApp API](https://darory.com), from any PHP 8.1+ project, with first-class Laravel support.

```php
WhatsApp::sendMessage('201012345678', 'Your order #1042 has shipped 🚚');
```

- Text, images, videos, audio, voice notes and documents (upload a file, send a URL or Base64)
- Number check, profile pictures, QR code and pairing code login, logout
- Several WhatsApp numbers in one app, or one number per customer (SaaS)
- Laravel: facade, notification channel, incoming-message webhook with events, test fake, artisan check
- Clear exceptions: number not on WhatsApp, session not connected, wrong token, file too large...

## Contents

- [Installation](#installation)
- [Plain PHP](#plain-php)
- [Laravel](#laravel)
  - [Sending messages](#sending-messages)
  - [Sending files](#sending-files)
  - [Sending a location](#sending-a-location)
  - [Several numbers](#several-numbers)
  - [Notifications](#notifications)
  - [Receiving messages (webhook)](#receiving-messages-webhook)
  - [Connecting a number](#connecting-a-number)
  - [Testing](#testing)
- [Errors](#errors)
- [API reference](#api-reference)

## Installation

```bash
composer require darory/whatsapp
```

Get your **API token** and **instance ID** from the Darory client area: *WhatsApp → your number → API*. Each connected WhatsApp number is one instance with its own token. Keep the token secret, never commit it or send it to the browser.

Phone numbers are always the full international number: the country code without `+` or `00`, e.g. `201012345678` for Egypt or `966501234567` for Saudi Arabia. The package also accepts `+20 101 234 5678`, `0020…` and `201012345678@c.us`, and normalises them. A local number like `01012345678` can't be guessed and is refused.

## Plain PHP

```php
use Darory\WhatsApp\DaroryWhatsApp;
use Darory\WhatsApp\Media;

$whatsapp = new DaroryWhatsApp(
    token: getenv('DARORY_WA_TOKEN'),
    instanceId: getenv('DARORY_WA_INSTANCE_ID'),
);

$sent = $whatsapp->sendMessage('201012345678', 'Hello from PHP');
echo $sent->id(); // keep it to match later webhook events

$whatsapp->sendDocument('201012345678', __DIR__.'/invoice.pdf', 'Your invoice');
$whatsapp->sendImage('201012345678', 'https://example.com/offer.jpg', 'This week only');
$whatsapp->sendLocation('201012345678', 30.0444, 31.2357, 'Darory Office', 'Tahrir Square, Cairo');

if ($whatsapp->isRegistered('201099999999')) {
    // the number has WhatsApp
}
```

The constructor also takes `baseUrl`, `timeout` (seconds, default 60) and your own Guzzle `http` client (for proxies or middleware).

Receiving messages without Laravel: point a webhook at a PHP script and parse the body.

```php
use Darory\WhatsApp\Webhooks\IncomingMessage;

if (($_GET['secret'] ?? '') !== getenv('DARORY_WA_WEBHOOK_SECRET')) {
    http_response_code(403);
    exit;
}

$message = IncomingMessage::fromPayload(json_decode(file_get_contents('php://input'), true));

if (! $message->fromMe && $message->isText()) {
    // $message->from, $message->body, $message->type ...
}
```

## Laravel

Laravel 10, 11 and 12 are supported. The service provider and the `WhatsApp` facade register themselves. Add your credentials to `.env`:

```dotenv
DARORY_WA_TOKEN=your-api-token
DARORY_WA_INSTANCE_ID=12
DARORY_WA_WEBHOOK_SECRET=a-long-random-string
```

Then check that everything works:

```bash
php artisan darory-whatsapp:check
```

It prints the connection status, the connected number and the webhook URL to register. To change more settings, publish the config file:

```bash
php artisan vendor:publish --tag=darory-whatsapp-config
```

### Sending messages

```php
use Darory\WhatsApp\Laravel\Facades\WhatsApp;

WhatsApp::sendMessage($user->phone, "Hi {$user->name}, your code is 4821");
```

You can also inject `Darory\WhatsApp\Contracts\WhatsAppClient` (the default instance) or `Darory\WhatsApp\Laravel\WhatsAppManager` (all of them).

Sending makes an HTTP call that can take a few seconds, longer for files. In web requests, send from a queued job or a queued notification.

### Sending files

Every send method accepts a URL, a local path or a `Media` object:

```php
use Darory\WhatsApp\Media;

WhatsApp::sendImage($phone, 'https://example.com/products/42.jpg', 'Back in stock');
WhatsApp::sendVideo($phone, storage_path('app/videos/demo.mp4'), 'How it works');
WhatsApp::sendDocument($phone, storage_path('app/invoices/1042.pdf'), 'Your invoice', 'Invoice-1042.pdf');
WhatsApp::sendAudio($phone, 'https://example.com/podcast.mp3');
WhatsApp::sendVoiceNote($phone, storage_path('app/voice/welcome.ogg'));
```

Use `Media` for files in memory, Base64, or more control:

```php
WhatsApp::sendMedia($phone, Media::fromContents(Storage::get('reports/may.xlsx'), 'May report.xlsx'));
WhatsApp::sendMedia($phone, Media::fromContents($pdf->output(), 'invoice.pdf')->caption('Thank you!'));
WhatsApp::sendMedia($phone, Media::fromBase64($dataUri, 'receipt.png'));
WhatsApp::sendMedia($phone, Media::fromPath($path)->asDocument()); // send a photo as a file, uncompressed
```

| Source | How it's sent | Notes |
|---|---|---|
| `Media::fromPath($path)` | multipart upload | Best for files on your server, up to 50 MB |
| `Media::fromContents($bytes, $name)` | multipart upload | Generated files, `Storage::get()` |
| `Media::fromUrl($url)` | the gateway downloads it | The link must be public and point straight at the file |
| `Media::fromBase64($b64, $name)` | JSON | Small files only, Base64 is a third larger |

The type (image, video, audio or document) is guessed from the file name or MIME type; force it with `asImage()`, `asVideo()`, `asAudio()`, `asDocument()` or `asVoiceNote()`. Captions work on images, videos and documents; WhatsApp drops them on audio. Voice notes play everywhere only as **OGG/Opus**: `ffmpeg -i input.mp3 -c:a libopus -b:a 32k -ac 1 voice.ogg`.

### Sending a location

Send a pin the customer can open in Google Maps or Apple Maps. The name and address are optional:

```php
use Darory\WhatsApp\Location;

WhatsApp::sendLocation($phone, 30.0444, 31.2357);
WhatsApp::sendLocation($phone, 30.0444, 31.2357, 'Darory Office', 'Tahrir Square, Cairo');

// Or build it once and reuse it
$branch = Location::make(30.0444, 31.2357, 'Darory Office', 'Tahrir Square, Cairo');
WhatsApp::sendLocation($phone, $branch);
```

The latitude must be between -90 and 90 and the longitude between -180 and 180, otherwise an `InvalidArgumentException` is thrown before any request is made.

### Several numbers

Add each number to `config/darory-whatsapp.php`:

```php
'instances' => [
    'default' => ['token' => env('DARORY_WA_TOKEN'), 'instance_id' => env('DARORY_WA_INSTANCE_ID')],
    'sales' => ['token' => env('DARORY_WA_SALES_TOKEN'), 'instance_id' => env('DARORY_WA_SALES_INSTANCE_ID')],
],
```

```php
WhatsApp::instance('sales')->sendMessage($lead->phone, 'Thanks for your interest!');
```

If you store credentials yourself, for example one number per customer in a SaaS, build the client on the fly:

```php
WhatsApp::using($tenant->wa_token, $tenant->wa_instance_id)->sendMessage($phone, $text);
```

### Notifications

Return `'whatsapp'` from `via()` and add `toWhatsApp()`:

```php
use Darory\WhatsApp\Laravel\Notifications\WhatsAppMessage;
use Darory\WhatsApp\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class InvoicePaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'whatsapp'];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        return WhatsAppMessage::create("Hi {$notifiable->name}, we received your payment. Your invoice is attached.")
            ->media(Media::fromPath($this->invoice->pdfPath())); // optional; the text becomes the caption
            // ->location(Location::make(30.0444, 31.2357, 'Our branch'))  also send a pin, after the text or file
            // ->instance('sales')   send from another configured number
            // ->to('201012345678')  override the recipient
    }
}
```

Tell Laravel which number to use on your model:

```php
class User extends Authenticatable
{
    use Notifiable;

    public function routeNotificationForWhatsApp(): ?string
    {
        return $this->phone; // with the country code
    }
}
```

Or notify any number on demand:

```php
Notification::route('whatsapp', '201012345678')->notify(new InvoicePaid($invoice));
```

`toWhatsApp()` may also return a plain string. When there is no number or no content, nothing is sent. With `location()`, the text (or the file) is sent first and the pin right after it; `WhatsAppMessage::create()->location(...)` sends only the pin.

### Receiving messages (webhook)

The package adds `POST /darory-whatsapp/webhook`, outside the `web` middleware so CSRF does not apply. Register it once:

1. Set `DARORY_WA_WEBHOOK_SECRET` in `.env` to a long random string. The gateway does not sign webhooks, so this secret in the URL is what keeps others out.
2. Run `php artisan darory-whatsapp:check` and copy the webhook URL it prints, e.g. `https://your-app.com/darory-whatsapp/webhook?secret=...`.
3. In the Darory client area open *WhatsApp → your number → Webhooks*, add the URL and tick the **message** event.

Then listen for `MessageReceived`:

```php
use Darory\WhatsApp\Laravel\Events\MessageReceived;
use Darory\WhatsApp\Laravel\Facades\WhatsApp;
use Illuminate\Contracts\Queue\ShouldQueue;

class ReplyToCustomer implements ShouldQueue
{
    public function handle(MessageReceived $event): void
    {
        $message = $event->message;

        if ($message->isGroup() || ! $message->isText()) {
            return;
        }

        if (str_contains(mb_strtolower($message->body), 'price')) {
            WhatsApp::sendMessage($message->from, 'Our price list: https://example.com/prices');
        }
    }
}
```

Laravel 11 and later discover the listener automatically; on Laravel 10, register it in `EventServiceProvider`. Use a queued listener so the webhook answers quickly.

`IncomingMessage` gives you `instanceId`, `id`, `from` (the chat's number), `author` (the member who wrote it in a group), `chatId`, `body`, `type` (`chat`, `image`, `video`, `audio`, `ptt`, `document`, `sticker`, `location`, `contact`...), `fromMe`, `hasMedia`, `isForwarded`, `timestamp`, plus `isText()`, `isGroup()`, `isLocation()` and `raw()` for the original WhatsApp message.

When a customer shares a location, `location()` returns it as a `Location` with `latitude`, `longitude`, `name`, `address` and `mapsUrl()` (null for any other type):

```php
if ($location = $event->message->location()) {
    $order->update(['lat' => $location->latitude, 'lng' => $location->longitude]);
}
```

Messages you send from the phone itself are skipped; set `webhook.ignore_own_messages` to `false` to receive them. Every accepted webhook also fires `WebhookReceived` with the raw payload. To change the path or add middleware (rate limiting, IP allow-list), edit the `webhook` section of the config, or set `DARORY_WA_WEBHOOK_ENABLED=false` and use your own route with `IncomingMessage::fromPayload($request->json()->all())`.

### Connecting a number

You can link a number from the Darory client area, or from your own app:

```php
$session = WhatsApp::session();

if ($session->isConnected()) {
    echo "{$session->name} ({$session->number})";
} else {
    $session = WhatsApp::connect();           // starts the session
    echo '<img src="'.e($session->qrCode).'">'; // a data:image/png;base64 URI, scan it with WhatsApp
}

// Or link without a camera: WhatsApp > Linked devices > Link with phone number
$code = WhatsApp::pairingCode('201012345678'); // e.g. "ABCD1234", null if already connected

WhatsApp::logout(); // returns a session with a fresh QR code
```

### Testing

Swap the client for a fake, so nothing is sent, then assert on what would have been sent:

```php
use Darory\WhatsApp\Laravel\Facades\WhatsApp;
use Darory\WhatsApp\Testing\RecordedMessage;

public function test_the_customer_gets_a_whatsapp_when_the_order_ships(): void
{
    $whatsapp = WhatsApp::fake();

    $this->post(route('orders.ship', $order));

    $whatsapp->assertSentTo($order->customer->phone, fn (RecordedMessage $message) => str_contains($message->content(), 'shipped'));
    $whatsapp->assertSentCount(1);
}
```

Available assertions: `assertSent(?callable)`, `assertSentTo($number, ?callable)`, `assertNotSentTo($number)`, `assertSentCount($n)` and `assertNothingSent()`. `sent(?callable)` returns the recorded messages; each has `instanceId`, `number`, `text`, `media`, `location`, `isMedia()`, `isLocation()` and `content()` (the text, the caption or the location name).

To simulate problems:

```php
WhatsApp::fake()->withUnregistered('201000000000');  // isRegistered() is false and sending throws
WhatsApp::fake()->failWith(new SessionNotConnectedException('Session not ready yet', 400));
WhatsApp::fake()->connected(false);                   // session() reports a QR code
```

## Errors

Every error extends `Darory\WhatsApp\Exceptions\DaroryWhatsAppException`, which has `statusCode()` and `response()` (the decoded error body).

| Exception | When |
|---|---|
| `NumberNotOnWhatsAppException` | The recipient has no WhatsApp account |
| `SessionNotConnectedException` | The number is not connected: scan the QR code |
| `SessionNotFoundException` | The instance isn't running on the gateway (404) |
| `AuthenticationException` | Wrong token or instance ID (401) |
| `FileTooLargeException` | The file is over 50 MB (413) |
| `InvalidRequestException` | A field is missing or wrong (400) |
| `ConnectionException` | The gateway could not be reached (network, DNS, timeout) |

Invalid input, such as an empty message, a malformed number or a missing file, throws `InvalidArgumentException` before any request is made.

```php
use Darory\WhatsApp\Exceptions\DaroryWhatsAppException;
use Darory\WhatsApp\Exceptions\NumberNotOnWhatsAppException;

try {
    WhatsApp::sendMessage($phone, $text);
} catch (NumberNotOnWhatsAppException) {
    $customer->update(['whatsapp' => false]);
} catch (DaroryWhatsAppException $e) {
    report($e);
}
```

## API reference

| Method | Returns |
|---|---|
| `sendMessage(string $number, string $message)` | `SentMessage` |
| `sendMedia(string $number, Media $media)` | `SentMessage` |
| `sendImage / sendVideo($number, $source, ?$caption)` | `SentMessage` |
| `sendDocument($number, $source, ?$caption, ?$fileName)` | `SentMessage` |
| `sendAudio / sendVoiceNote($number, $source)` | `SentMessage` |
| `sendLocation($number, $latitude, $longitude, ?$name, ?$address)` or `sendLocation($number, Location $location)` | `SentMessage` |
| `isRegistered(string $number)` | `bool` |
| `profilePictureUrl(string $number)` | `?string` |
| `session()` | `Session` |
| `connect()` | `Session` with a QR code, or the account when connected |
| `pairingCode(string $number)` | `?string` |
| `logout()` | `Session` with a fresh QR code |
| `deleteChat(string $number)` | `bool` |

`SentMessage` has `id`, `to`, `timestamp` and `raw`. `Session` has `status`, `number`, `name`, `profilePictureUrl`, `qrCode`, `raw`, `isConnected()` and `needsScan()`.

The raw REST API is documented in the Darory client area if you need to call it from another language.

## Running the package tests

```bash
composer install
composer test
```

## License

MIT. See [LICENSE](LICENSE).
