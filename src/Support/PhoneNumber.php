<?php

namespace Darory\WhatsApp\Support;

use InvalidArgumentException;

/**
 * Turns "+20 101 234 5678", "0020-1012345678" or "201012345678@c.us" into "201012345678".
 * The number must include its country code: a local number such as "01012345678" can't be guessed.
 */
final class PhoneNumber
{
    public static function normalize(string $number): string
    {
        $digits = preg_replace('/\D+/', '', preg_replace('/@.*$/', '', trim($number)) ?? '') ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) < 7 || strlen($digits) > 15) {
            throw new InvalidArgumentException("[{$number}] is not a valid phone number. Use the full number with the country code, e.g. 201012345678.");
        }

        return $digits;
    }
}
