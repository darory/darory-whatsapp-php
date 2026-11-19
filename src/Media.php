<?php

namespace Darory\WhatsApp;

use Darory\WhatsApp\Support\MimeTypes;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;

/**
 * A file to send: from a local path, raw contents, a public URL or Base64.
 * Each method returns a new copy, so a Media object can be reused safely.
 *
 *     Media::fromPath(storage_path('app/invoice.pdf'))->caption('Your invoice');
 *     Media::fromUrl('https://example.com/offer.jpg')->caption('This week only');
 *     Media::fromContents($pdfBytes, 'invoice.pdf');
 *     Media::fromPath('voice.ogg')->asVoiceNote();
 */
final class Media
{
    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const TYPE_AUDIO = 'audio';

    public const TYPE_DOCUMENT = 'document';

    private const SOURCE_PATH = 'path';

    private const SOURCE_CONTENTS = 'contents';

    private const SOURCE_URL = 'url';

    private const SOURCE_BASE64 = 'base64';

    private ?string $type = null;

    private ?string $caption = null;

    private ?string $fileName = null;

    private ?string $mimeType = null;

    private bool $voiceNote = false;

    private function __construct(
        private readonly string $source,
        private readonly string $value,
    ) {}

    /**
     * Upload a file from disk.
     */
    public static function fromPath(string $path): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("The file [{$path}] does not exist or is not readable.");
        }

        return (new self(self::SOURCE_PATH, $path))->withFileName(basename($path));
    }

    /**
     * Upload a file you hold in memory, e.g. a generated PDF or Storage::get().
     */
    public static function fromContents(string $contents, string $fileName, ?string $mimeType = null): self
    {
        if ($contents === '') {
            throw new InvalidArgumentException('The file contents are empty.');
        }

        $media = (new self(self::SOURCE_CONTENTS, $contents))->withFileName($fileName);

        return $mimeType === null ? $media : $media->mimeType($mimeType);
    }

    /**
     * Let the gateway download the file. The link must be public and point straight to the file.
     */
    public static function fromUrl(string $url): self
    {
        if (! preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("[{$url}] is not a valid http(s) URL.");
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $media = new self(self::SOURCE_URL, $url);

        return $path !== '' && str_contains(basename($path), '.') ? $media->withFileName(rawurldecode(basename($path))) : $media;
    }

    /**
     * Send Base64 content, with or without a "data:<mime>;base64," prefix. Best for small files.
     */
    public static function fromBase64(string $base64, ?string $fileName = null, ?string $mimeType = null): self
    {
        if (trim($base64) === '') {
            throw new InvalidArgumentException('The Base64 content is empty.');
        }

        $media = new self(self::SOURCE_BASE64, $base64);

        if (preg_match('#^data:([\w.+-]+/[\w.+-]+)[;,]#', $base64, $matches)) {
            $media = $media->mimeType($matches[1]);
        }

        if ($fileName !== null) {
            $media = $media->withFileName($fileName);
        }

        return $mimeType === null ? $media : $media->mimeType($mimeType);
    }

    /**
     * A URL when the source starts with http(s)://, otherwise a local file path.
     */
    public static function from(string $source): self
    {
        return preg_match('#^https?://#i', $source) ? self::fromUrl($source) : self::fromPath($source);
    }

    /**
     * Text shown under an image, video or document. WhatsApp ignores captions on audio.
     */
    public function caption(?string $caption): self
    {
        $copy = clone $this;
        $copy->caption = $caption === '' ? null : $caption;

        return $copy;
    }

    /**
     * The file name the recipient sees on a document.
     */
    public function fileName(string $fileName): self
    {
        return $this->withFileName($fileName);
    }

    public function mimeType(string $mimeType): self
    {
        $copy = clone $this;
        $copy->mimeType = $mimeType;

        return $copy;
    }

    public function asImage(): self
    {
        return $this->withType(self::TYPE_IMAGE);
    }

    public function asVideo(): self
    {
        return $this->withType(self::TYPE_VIDEO);
    }

    public function asAudio(): self
    {
        return $this->withType(self::TYPE_AUDIO);
    }

    /**
     * Any file, delivered as a downloadable attachment with its file name.
     */
    public function asDocument(): self
    {
        return $this->withType(self::TYPE_DOCUMENT);
    }

    /**
     * Audio shown as a voice note (microphone bubble). Use an OGG/Opus file.
     */
    public function asVoiceNote(): self
    {
        $copy = $this->withType(self::TYPE_AUDIO);
        $copy->voiceNote = true;

        return $copy;
    }

    /**
     * image, video, audio or document. Guessed from the MIME type or extension when not set.
     */
    public function type(): ?string
    {
        if ($this->type !== null) {
            return $this->type;
        }

        $mimeType = $this->resolvedMimeType();

        if ($mimeType !== null) {
            return MimeTypes::typeFor($mimeType);
        }

        // An upload is detected by the gateway; a URL or Base64 of unknown type is safest as a document
        return in_array($this->source, [self::SOURCE_PATH, self::SOURCE_CONTENTS], true) ? null : self::TYPE_DOCUMENT;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function isVoiceNote(): bool
    {
        return $this->voiceNote;
    }

    /**
     * Where the file comes from: path, contents, url or base64.
     */
    public function source(): string
    {
        return $this->source;
    }

    /**
     * The path, URL or Base64 string (raw bytes for in-memory contents).
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * The fields the gateway expects, without the file itself.
     *
     * @return array<string, string|bool>
     */
    public function fields(string $number): array
    {
        $fields = ['number' => $number];

        if (($type = $this->type()) !== null) {
            $fields['type'] = $type;
        }

        if ($this->caption !== null && $this->type() !== self::TYPE_AUDIO) {
            $fields['caption'] = $this->caption;
        }

        if ($this->fileName !== null) {
            $fields['fileName'] = $this->fileName;
        }

        if (($mimeType = $this->resolvedMimeType()) !== null) {
            $fields['mimetype'] = $mimeType;
        }

        if ($this->voiceNote) {
            $fields['ptt'] = true;
        }

        return $fields;
    }

    /**
     * Guzzle request options: multipart for files and contents, JSON for URL and Base64.
     *
     * @return array<string, mixed>
     */
    public function toRequestOptions(string $number): array
    {
        $fields = $this->fields($number);

        if ($this->source === self::SOURCE_URL || $this->source === self::SOURCE_BASE64) {
            return ['json' => $fields + [$this->source => $this->value]];
        }

        $multipart = [];

        foreach ($fields as $name => $value) {
            $multipart[] = ['name' => $name, 'contents' => is_bool($value) ? ($value ? 'true' : 'false') : $value];
        }

        $file = [
            'name' => 'file',
            'contents' => $this->source === self::SOURCE_PATH ? Utils::tryFopen($this->value, 'r') : $this->value,
            'filename' => $this->fileName ?? 'file',
        ];

        if (isset($fields['mimetype'])) {
            $file['headers'] = ['Content-Type' => $fields['mimetype']];
        }

        $multipart[] = $file;

        return ['multipart' => $multipart];
    }

    private function resolvedMimeType(): ?string
    {
        if ($this->mimeType !== null) {
            return $this->mimeType;
        }

        return $this->fileName !== null ? MimeTypes::fromFileName($this->fileName) : null;
    }

    private function withType(string $type): self
    {
        $copy = clone $this;
        $copy->type = $type;
        $copy->voiceNote = false;

        return $copy;
    }

    private function withFileName(string $fileName): self
    {
        if (trim($fileName) === '') {
            throw new InvalidArgumentException('The file name is empty.');
        }

        $copy = clone $this;
        $copy->fileName = $fileName;

        return $copy;
    }
}
