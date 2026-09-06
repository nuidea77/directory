<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Дүүрэг/сум, хорооноос газрын зургийн төвийг олно.
 *
 *  1. УБ-ын дүүрэг → config/geo.php-ийн хотын төв (Nominatim-ийн захиргааны
 *     төв хотоос хол тул ашиглахгүй).
 *  2. Хороо (УБ) → Nominatim; олдсон нэр нь хороо, дүүргээ агуулж байвал л авна.
 *  3. Аймгийн сум → Nominatim; нэр нь тохирвол л авна («Хайрхан» гэхэд
 *     «Хангай» буцаадаг тул).
 *
 * Олдохгүй бол null — frontend аймгийн төвөөр үргэлжилнэ.
 */
class Geocoder
{
    public const ZOOM_DISTRICT = 13;

    public const ZOOM_KHOROO = 15;

    /**
     * @return array{lat: float, lng: float, zoom: int, precision: string}|null
     */
    public static function lookup(string $city, ?string $district, ?string $khoroo = null): ?array
    {
        $city = trim($city);
        $district = trim((string) $district);
        $khoroo = self::normalizeKhoroo($khoroo);

        if ($district === '') {
            return null;
        }

        $isUb = $city === 'Улаанбаатар';
        $base = null;

        if ($isUb && ($static = config("geo.ub_districts.{$district}"))) {
            $base = [
                'lat' => (float) $static['lat'],
                'lng' => (float) $static['lng'],
                'zoom' => self::ZOOM_DISTRICT,
                'precision' => 'district',
            ];
        }

        // Хороо (зөвхөн УБ) — олдвол дүүргээс илүү нарийн
        if ($isUb && $khoroo !== null) {
            $hit = self::nominatim("{$khoroo}, {$district} дүүрэг, Улаанбаатар", [$khoroo, $district]);

            // Дүүргийн төвөөс 25 км дотор байвал л итгэнэ (буруу газар үсрэхээс)
            if ($hit !== null && ($base === null || self::distanceKm($hit, $base) <= 25)) {
                return [...$hit, 'zoom' => self::ZOOM_KHOROO, 'precision' => 'khoroo'];
            }
        }

        if ($base !== null) {
            return $base;
        }

        // Аймгийн сум
        if (! $isUb) {
            $hit = self::nominatim("{$district} сум, {$city}", [$district])
                ?? self::nominatim("{$district}, {$city}", [$district]);

            if ($hit !== null) {
                return [...$hit, 'zoom' => self::ZOOM_DISTRICT, 'precision' => 'district'];
            }
        }

        return null;
    }

    /**
     * «13», «13-р», «13 хороо», «13-р хороо» → «13-р хороо»; бусад текстийг хэвээр
     */
    public static function normalizeKhoroo(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,3})\b/u', $value, $m)) {
            return $m[1].'-р хороо';
        }

        return Str::limit($value, 60, '');
    }

    /**
     * Nominatim хайлт — үр дүнгийн нэр $mustContain бүрийг агуулж байвал л буцаана.
     *
     * @param  array<int, string>  $mustContain
     * @return array{lat: float, lng: float}|null
     */
    protected static function nominatim(string $query, array $mustContain): ?array
    {
        $cfg = config('geo.nominatim');
        $key = 'geo:nominatim:v1:'.md5(mb_strtolower($query));

        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        try {
            $response = Http::baseUrl($cfg['base_url'])
                ->withHeaders(['User-Agent' => $cfg['user_agent']])
                ->timeout($cfg['timeout'])
                ->get('/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => 'mn',
                    'accept-language' => 'mn',
                ]);

            $row = $response->ok() ? ($response->json()[0] ?? null) : null;
        } catch (Throwable) {
            // Сүлжээ/хугацаа — кэшлэхгүй, дараагийн удаа дахин оролдоно
            return null;
        }

        $name = mb_strtolower((string) ($row['display_name'] ?? ''));
        $matches = $row !== null && collect($mustContain)
            ->every(fn (string $part) => str_contains($name, mb_strtolower($part)));

        if (! $matches) {
            Cache::put($key, false, now()->addHours($cfg['negative_cache_hours']));

            return null;
        }

        $hit = ['lat' => round((float) $row['lat'], 6), 'lng' => round((float) $row['lon'], 6)];
        Cache::put($key, $hit, now()->addDays($cfg['cache_days']));

        return $hit;
    }

    /** @param array{lat: float, lng: float} $a */
    protected static function distanceKm(array $a, array $b): float
    {
        $r = 6371;
        $dLat = deg2rad($b['lat'] - $a['lat']);
        $dLng = deg2rad($b['lng'] - $a['lng']);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(sqrt($h));
    }
}
