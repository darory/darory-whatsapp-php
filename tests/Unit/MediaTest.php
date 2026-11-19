<?php

namespace Darory\WhatsApp\Tests\Unit;

use Darory\WhatsApp\Media;
use Darory\WhatsApp\Support\PhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaTest extends TestCase
{
    public function test_the_type_is_guessed_from_the_file_name_or_mime_type(): void
    {
        $this->assertSame('image', Media::fromUrl('https://example.com/a/photo.PNG')->type());
        $this->assertSame('video', Media::fromUrl('https://example.com/clip.mp4')->type());
        $this->assertSame('audio', Media::fromUrl('https://example.com/song.mp3')->type());
        $this->assertSame('document', Media::fromUrl('https://example.com/report.xlsx')->type());
        $this->assertSame('document', Media::fromUrl('https://example.com/download?id=5')->type());
        $this->assertSame('image', Media::fromBase64('data:image/webp;base64,AAAA')->type());
        $this->assertSame('document', Media::fromContents('x', 'notes.unknown')->mimeType('application/x-custom')->type());

        // An upload of unknown type is left for the gateway to detect
        $this->assertNull(Media::fromContents('x', 'notes.unknown')->type());
    }

    public function test_an_explicit_type_wins_and_each_change_returns_a_copy(): void
    {
        $photo = Media::fromUrl('https://example.com/photo.jpg');
        $asDocument = $photo->asDocument()->caption('Original quality')->fileName('photo-full.jpg');

        $this->assertSame('image', $photo->type());
        $this->assertNull($photo->getCaption());
        $this->assertSame('document', $asDocument->type());
        $this->assertSame('photo-full.jpg', $asDocument->getFileName());
        $this->assertSame('Original quality', $asDocument->getCaption());
    }

    public function test_from_picks_a_url_or_a_path(): void
    {
        $this->assertSame('url', Media::from('https://example.com/a.jpg')->source());
        $this->assertSame('path', Media::from(__DIR__.'/../fixtures/invoice.pdf')->source());
    }

    public function test_invalid_sources_are_refused(): void
    {
        foreach ([
            fn () => Media::fromPath('/no/such/file.pdf'),
            fn () => Media::fromUrl('ftp://example.com/a.jpg'),
            fn () => Media::fromUrl('not a url'),
            fn () => Media::fromBase64(''),
            fn () => Media::fromContents('', 'a.pdf'),
            fn () => Media::fromContents('x', ' '),
        ] as $index => $make) {
            try {
                $make();
                $this->fail("Source #{$index} was accepted.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_in_memory_contents_are_uploaded_with_their_name(): void
    {
        $options = Media::fromContents('PDFDATA', 'فاتورة.pdf')->caption('Invoice')->toRequestOptions('201012345678');
        $file = end($options['multipart']);

        $this->assertSame(['name' => 'file', 'contents' => 'PDFDATA', 'filename' => 'فاتورة.pdf', 'headers' => ['Content-Type' => 'application/pdf']], $file);
        $this->assertContains(['name' => 'caption', 'contents' => 'Invoice'], $options['multipart']);
        $this->assertContains(['name' => 'type', 'contents' => 'document'], $options['multipart']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function numbers(): array
    {
        return [
            'plain' => ['201012345678', '201012345678'],
            'plus and spaces' => ['+20 101 234 5678', '201012345678'],
            'double zero' => ['0020-101-234-5678', '201012345678'],
            'chat id' => ['201012345678@c.us', '201012345678'],
            'jid' => ['201012345678@s.whatsapp.net', '201012345678'],
            'saudi' => ['+966 50 123 4567', '966501234567'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_phone_numbers_are_normalized(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }
}
