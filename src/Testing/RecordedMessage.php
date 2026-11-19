<?php

namespace Darory\WhatsApp\Testing;

use Darory\WhatsApp\Location;
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
        public readonly ?Location $location = null,
    ) {}

    public function isMedia(): bool
    {
        return $this->media !== null;
    }

    public function isLocation(): bool
    {
        return $this->location !== null;
    }

    /**
     * The text, the caption of a file, or the name of a location.
     */
    public function content(): ?string
    {
        return $this->text ?? $this->media?->getCaption() ?? $this->location?->name;
    }
}
