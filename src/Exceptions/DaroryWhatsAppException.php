<?php

namespace Darory\WhatsApp\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for every error the client throws. Catch it to handle all of them at once.
 */
class DaroryWhatsAppException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $response  The decoded error body, when there is one.
     */
    public function __construct(
        string $message,
        protected int $statusCode = 0,
        protected array $response = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Pick the most specific exception for an error response.
     *
     * @param  array<string, mixed>  $body
     */
    public static function fromResponse(int $status, array $body): self
    {
        $message = (string) ($body['message'] ?? $body['error'] ?? ($status >= 400 ? "The Darory WhatsApp API returned HTTP {$status}." : 'The Darory WhatsApp API returned an error.'));
        $lower = strtolower($message);

        $class = match (true) {
            $status === 401 || $status === 403 || str_contains($lower, 'unauthorized') => AuthenticationException::class,
            str_contains($lower, 'is not on whatsapp') => NumberNotOnWhatsAppException::class,
            str_contains($lower, 'not ready') => SessionNotConnectedException::class,
            $status === 404 => SessionNotFoundException::class,
            $status === 413 || str_contains($lower, 'too large') => FileTooLargeException::class,
            $status === 400 || $status === 422 => InvalidRequestException::class,
            default => self::class,
        };

        return new $class($message, $status, $body);
    }

    /**
     * The HTTP status code, or 0 when the gateway could not be reached.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function response(): array
    {
        return $this->response;
    }
}
