<?php

namespace Darory\WhatsApp\Tests\Unit;

use Darory\WhatsApp\Webhooks\IncomingMessage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class IncomingMessageTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(array $message = []): array
    {
        return [
            'event' => 'message',
            'instanceId' => '12',
            'data' => ['message' => array_replace([
                'id' => ['fromMe' => false, 'remote' => '201012345678@c.us', 'id' => 'A1B2C3', '_serialized' => 'false_201012345678@c.us_A1B2C3'],
                'body' => 'Hello, I need help',
                'type' => 'chat',
                'from' => '201012345678@c.us',
                'to' => '201012345678@c.us',
                'timestamp' => 1710000000,
                'isForwarded' => false,
                'hasMedia' => false,
                'raw' => ['key' => ['id' => 'A1B2C3']],
            ], $message)],
        ];
    }

    public function test_a_private_text_message_is_mapped(): void
    {
        $message = IncomingMessage::fromPayload(self::payload());

        $this->assertSame('12', $message->instanceId);
        $this->assertSame('A1B2C3', $message->id);
        $this->assertSame('201012345678', $message->from);
        $this->assertSame('201012345678', $message->author);
        $this->assertSame('201012345678@c.us', $message->chatId);
        $this->assertSame('Hello, I need help', $message->body);
        $this->assertSame(1710000000, $message->timestamp);
        $this->assertTrue($message->isText());
        $this->assertFalse($message->isGroup());
        $this->assertFalse($message->fromMe);
        $this->assertSame(['key' => ['id' => 'A1B2C3']], $message->raw());
    }

    public function test_a_group_media_message_is_mapped(): void
    {
        $message = IncomingMessage::fromPayload(self::payload([
            'id' => ['fromMe' => false, 'remote' => '120363000000000000@g.us', 'id' => 'G1'],
            'from' => '120363000000000000@g.us',
            'to' => '966501234567@c.us',
            'type' => 'image',
            'body' => '',
            'hasMedia' => true,
        ]));

        $this->assertTrue($message->isGroup());
        $this->assertSame('966501234567', $message->author);
        $this->assertTrue($message->hasMedia);
        $this->assertFalse($message->isText());
    }

    public function test_other_payloads_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        IncomingMessage::fromPayload(['event' => 'status', 'data' => []]);
    }
}
