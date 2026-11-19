<?php

namespace Darory\WhatsApp\Concerns;

use Darory\WhatsApp\Media;
use Darory\WhatsApp\Responses\SentMessage;

/**
 * Shortcuts for sending each kind of file. A source is a Media object, a public URL or a local file path.
 */
trait SendsMedia
{
    public function sendImage(string $number, Media|string $image, ?string $caption = null): SentMessage
    {
        return $this->sendMedia($number, $this->prepareMedia($image, $caption)->asImage());
    }

    public function sendVideo(string $number, Media|string $video, ?string $caption = null): SentMessage
    {
        return $this->sendMedia($number, $this->prepareMedia($video, $caption)->asVideo());
    }

    public function sendAudio(string $number, Media|string $audio): SentMessage
    {
        return $this->sendMedia($number, $this->prepareMedia($audio)->asAudio());
    }

    /**
     * Send audio as a voice note. Use an OGG/Opus file so it plays on every phone.
     */
    public function sendVoiceNote(string $number, Media|string $audio): SentMessage
    {
        return $this->sendMedia($number, $this->prepareMedia($audio)->asVoiceNote());
    }

    public function sendDocument(string $number, Media|string $document, ?string $caption = null, ?string $fileName = null): SentMessage
    {
        $media = $this->prepareMedia($document, $caption)->asDocument();

        return $this->sendMedia($number, $fileName === null ? $media : $media->fileName($fileName));
    }

    protected function prepareMedia(Media|string $source, ?string $caption = null): Media
    {
        $media = $source instanceof Media ? $source : Media::from($source);

        return $caption === null ? $media : $media->caption($caption);
    }
}
