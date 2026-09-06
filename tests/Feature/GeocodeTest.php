<?php

namespace Tests\Feature;

use App\Support\Geocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Дүүрэг/сум, хорооноос газрын зургийн төв олох.
 */
class GeocodeTest extends TestCase
{
    use RefreshDatabase;

    protected function nominatimHit(string $displayName, string $lat, string $lon): array
    {
        return ['nominatim.openstreetmap.org/*' => Http::response([[
            'lat' => $lat, 'lon' => $lon, 'display_name' => $displayName, 'type' => 'administrative',
        ]])];
    }

    public function test_ub_district_uses_the_static_city_centre_without_calling_nominatim(): void
    {
        Http::fake();

        $hit = Geocoder::lookup('Улаанбаатар', 'Баянзүрх');

        $this->assertSame('district', $hit['precision']);
        $this->assertSame(Geocoder::ZOOM_DISTRICT, $hit['zoom']);
        $this->assertEqualsWithDelta(47.921, $hit['lat'], 0.001);
        Http::assertNothingSent();
    }

    public function test_khoroo_refines_the_ub_district_when_nominatim_confirms_it(): void
    {
        Http::fake($this->nominatimHit('13-р хороо, Энх тайвны өргөн чөлөө, Баянзүрх дүүрэг, Улаанбаатар', '47.9124', '106.9766'));

        $hit = Geocoder::lookup('Улаанбаатар', 'Баянзүрх', '13');

        $this->assertSame('khoroo', $hit['precision']);
        $this->assertSame(Geocoder::ZOOM_KHOROO, $hit['zoom']);
        $this->assertEqualsWithDelta(47.9124, $hit['lat'], 0.0001);

        // «13» → «13-р хороо» болж хайгдана
        Http::assertSent(fn ($req) => str_contains($req['q'], '13-р хороо, Баянзүрх дүүрэг'));
    }

    public function test_khoroo_result_far_from_the_district_is_ignored(): void
    {
        // Нэр таарсан ч Дархан хавьд байвал дүүргийн төвөө үлдээнэ
        Http::fake($this->nominatimHit('13-р хороо, Баянзүрх дүүрэг, Улаанбаатар', '49.48', '105.92'));

        $hit = Geocoder::lookup('Улаанбаатар', 'Баянзүрх', '13-р хороо');

        $this->assertSame('district', $hit['precision']);
    }

    public function test_aimag_sum_is_accepted_only_when_the_name_matches(): void
    {
        // Nominatim «Хайрхан» гэхэд «Хангай» буцаадаг — авахгүй
        Http::fake($this->nominatimHit('Хангай сум, Архангай, Монгол улс', '48.5383', '101.9529'));

        $this->assertNull(Geocoder::lookup('Архангай', 'Хайрхан'));
    }

    public function test_aimag_sum_centre_comes_from_nominatim(): void
    {
        Http::fake($this->nominatimHit('Замын-Үүд сум, Дорноговь, Монгол улс', '43.8145', '111.8348'));

        $hit = Geocoder::lookup('Дорноговь', 'Замын-Үүд');

        $this->assertSame('district', $hit['precision']);
        $this->assertEqualsWithDelta(43.8145, $hit['lat'], 0.0001);
    }

    public function test_lookups_are_cached_so_nominatim_is_called_once(): void
    {
        Http::fake($this->nominatimHit('Замын-Үүд сум, Дорноговь, Монгол улс', '43.8145', '111.8348'));

        Geocoder::lookup('Дорноговь', 'Замын-Үүд');
        Geocoder::lookup('Дорноговь', 'Замын-Үүд');

        Http::assertSentCount(1);
    }

    public function test_network_failure_degrades_to_null_not_an_error(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => fn () => throw new \Exception('timeout')]);

        $this->assertNull(Geocoder::lookup('Дорноговь', 'Замын-Үүд'));
    }

    public function test_khoroo_normalisation(): void
    {
        $this->assertSame('13-р хороо', Geocoder::normalizeKhoroo('13'));
        $this->assertSame('13-р хороо', Geocoder::normalizeKhoroo('13-р'));
        $this->assertSame('13-р хороо', Geocoder::normalizeKhoroo(' 13 хороо '));
        $this->assertSame('Улаанхуаран', Geocoder::normalizeKhoroo('Улаанхуаран'));
        $this->assertNull(Geocoder::normalizeKhoroo('  '));
    }

    public function test_endpoint_returns_the_centre_and_validates_input(): void
    {
        Http::fake();

        $this->getJson('/api/v1/geocode?city='.urlencode('Улаанбаатар').'&district='.urlencode('Хан-Уул'))
            ->assertOk()
            ->assertJsonPath('data.precision', 'district');

        $this->getJson('/api/v1/geocode?city='.urlencode('Улаанбаатар'))->assertStatus(422);
    }
}
