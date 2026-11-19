<?php

namespace Darory\WhatsApp\Support;

/**
 * @internal
 */
final class MimeTypes
{
    /**
     * @var array<string, string>
     */
    private const BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        '3gp' => 'video/3gpp',
        'mov' => 'video/quicktime',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'aac' => 'audio/aac',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'opus' => 'audio/ogg',
        'wav' => 'audio/wav',
        'amr' => 'audio/amr',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
        'json' => 'application/json',
        'apk' => 'application/vnd.android.package-archive',
    ];

    public static function fromFileName(string $fileName): ?string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return self::BY_EXTENSION[$extension] ?? null;
    }

    /**
     * The WhatsApp message type for a MIME type. Anything that isn't an image, video or audio is a document.
     */
    public static function typeFor(string $mimeType): string
    {
        $mimeType = strtolower($mimeType);

        return match (true) {
            str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml' => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };
    }
}
