<?php

namespace Darory\WhatsApp\Testing;

use Darory\WhatsApp\Media;

/**
 * A message the fake would have sent.
 */
final class RecordedMessage
{
    public function __construct(
        public readonly string $instanceId,
        public readonly string $number,
        public readonly ?string $text = null,
        public readonly ?Media $media = null,
    ) {}

    public function isMedia(): bool
    {
        return $this->media !== null;
    }

    /**
     * The text, or the caption of a file.
     */
    public function content(): ?string
    {
        return $this->text ?? $this->media?->getCaption();
    }
}
