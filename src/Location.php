<?php

namespace Darory\WhatsApp;

use InvalidArgumentException;

/**
 * A location pin: sent with sendLocation(), or read from an incoming "location" message.
 *
 *     $whatsapp->sendLocation('201012345678', 30.0444, 31.2357, 'Darory Office', 'Tahrir Square, Cairo');
 */
final class Location
{
    /**
     * @param  float  $latitude  Between -90 and 90.
     * @param  float  $longitude  Between -180 and 180.
     * @param  string|null  $name  Place name shown above the map.
     * @param  string|null  $address  Address line shown under the name.
     */
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?string $name = null,
        public readonly ?string $address = null,
    ) {
        if (! is_finite($latitude) || $latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException("The latitude [{$latitude}] must be between -90 and 90.");
        }

        if (! is_finite($longitude) || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException("The longitude [{$longitude}] must be between -180 and 180.");
        }
    }

    public static function make(float $latitude, float $longitude, ?string $name = null, ?string $address = null): self
    {
        return new self($latitude, $longitude, self::clean($name), self::clean($address));
    }

    /**
     * Accepts either a Location or the latitude/longitude pair, as sendLocation() does.
     *
     * @internal
     */
    public static function resolve(self|float $latitude, ?float $longitude, ?string $name, ?string $address): self
    {
        if ($latitude instanceof self) {
            return $latitude;
        }

        if ($longitude === null) {
            throw new InvalidArgumentException('The longitude is required.');
        }

        return self::make($latitude, $longitude, $name, $address);
    }

    /**
     * A Google Maps link to the pin.
     */
    public function mapsUrl(): string
    {
        return 'https://www.google.com/maps?q='.$this->latitude.','.$this->longitude;
    }

    /**
     * The JSON body of POST /send-location, without the number.
     *
     * @return array{latitude: float, longitude: float, name?: string, address?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'name' => $this->name,
            'address' => $this->address,
        ], fn ($value) => $value !== null);
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
