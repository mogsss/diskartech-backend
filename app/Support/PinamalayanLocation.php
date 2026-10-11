<?php

namespace App\Support;

use Illuminate\Http\Request;

final class PinamalayanLocation
{
    public const MESSAGE = 'Please choose a location within Pinamalayan, Oriental Mindoro.';

    public static function contains(mixed $latitude, mixed $longitude): bool
    {
        if ((!is_int($latitude) && !is_float($latitude) && !is_string($latitude))
            || (!is_int($longitude) && !is_float($longitude) && !is_string($longitude))
            || !is_numeric($latitude) || !is_numeric($longitude)) return false;
        $lat = (float) $latitude; $lng = (float) $longitude;
        if (!is_finite($lat) || !is_finite($lng)) return false;
        static $boundary = null;
        $boundary ??= json_decode(file_get_contents(resource_path('data/pinamalayan-boundary.json')), true, 512, JSON_THROW_ON_ERROR);
        [$west, $south, $east, $north] = $boundary['bbox'];
        if ($lat < $south || $lat > $north || $lng < $west || $lng > $east) return false;
        $rings = $boundary['geometry']['coordinates'];
        if (!self::withinRing($lat, $lng, $rings[0])) return false;
        foreach (array_slice($rings, 1) as $hole) {
            if (self::withinRing($lat, $lng, $hole)) return false;
        }
        return true;
    }

    private static function withinRing(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i]; [$xj, $yj] = $ring[$j];
            $cross = ($lng - $xi) * ($yj - $yi) - ($lat - $yi) * ($xj - $xi);
            if (abs($cross) < 1e-12 && $lng >= min($xi, $xj) && $lng <= max($xi, $xj)
                && $lat >= min($yi, $yj) && $lat <= max($yi, $yj)) return true;
            if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) $inside = !$inside;
        }
        return $inside;
    }

    // Apply to supplied coordinates only; unrelated profile edits do not rewrite old locations.
    public static function rules(Request $request): array
    {
        foreach (['address', 'location', 'detailed_address'] as $field) {
            if (is_string($request->input($field))) $request->merge([$field => PlainTextInput::normalize($request->input($field))]);
        }
        $plainText = static function ($attribute, $value, $fail) {
            if (is_string($value) && PlainTextInput::containsMarkup($value)) $fail('The '.$attribute.' must contain plain text only.');
        };
        return [
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180',
                static function ($attribute, $value, $fail) use ($request) {
                    if (!self::contains($request->input('latitude'), $value)) $fail(self::MESSAGE);
                }],
            'address' => ['sometimes', 'nullable', 'string', 'max:500', $plainText],
            'location' => ['sometimes', 'nullable', 'string', 'max:500', $plainText],
            'detailed_address' => ['sometimes', 'nullable', 'string', 'max:500', $plainText],
        ];
    }
}
