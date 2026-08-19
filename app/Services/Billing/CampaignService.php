<?php

namespace App\Services\Billing;

use App\Models\Campaign;
use Illuminate\Support\Facades\Cache;

/**
 * Онцлох зайн амьдралын мөчлөг: дууссаныг хаах, дараалалд байгааг
 * зай суларсан үед FIFO дарааллаар идэвхжүүлэх.
 */
class CampaignService
{
    /**
     * Cheap opportunistic sync — public featured жагсаалт татахад дуудагдана.
     * 60 секундэд нэгээс олонгүй ажиллана.
     */
    public function sync(): void
    {
        if (! Cache::add('campaigns:sync-lock', 1, 60)) {
            return;
        }

        $this->syncNow();
    }

    public function syncNow(): void
    {
        // 1. Хугацаа дууссаныг хаана
        Campaign::where('status', 'active')->where('ends_at', '<=', now())->update(['status' => 'expired']);

        // 2. Дараалалд байгааг зай гарвал идэвхжүүлнэ
        Campaign::where('status', 'queued')
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (Campaign $c) => implode('|', [$c->type, $c->category_id, $c->district, $c->city, $c->keyword]))
            ->each(function ($queue) {
                /** @var Campaign $first */
                $first = $queue->first();
                $slots = (int) config("billing.ads.{$first->type}.slots", 3);

                foreach ($queue as $campaign) {
                    $occupied = Campaign::occupiedSlots(
                        $campaign->type,
                        $campaign->category_id,
                        $campaign->district,
                        $campaign->city,
                        $campaign->keyword,
                    );

                    if ($occupied >= $slots) {
                        break;
                    }

                    $this->activate($campaign, $occupied + 1);
                }
            });
    }

    public function activate(Campaign $campaign, ?int $slot = null): void
    {
        $campaign->update([
            'status' => 'active',
            'slot' => $slot ?? ($campaign->slot ?: Campaign::occupiedSlots(
                $campaign->type, $campaign->category_id, $campaign->district, $campaign->city, $campaign->keyword,
            ) + 1),
            'starts_at' => now(),
            'ends_at' => now()->addDays($campaign->days),
        ]);
    }

    /**
     * Төлбөр батлагдсаны дараа: зай сул бол шууд идэвхжүүлнэ, дүүрсэн бол дараалалд.
     */
    public function activateOrQueue(Campaign $campaign): void
    {
        $slots = (int) config("billing.ads.{$campaign->type}.slots", 3);
        $occupied = Campaign::occupiedSlots(
            $campaign->type, $campaign->category_id, $campaign->district, $campaign->city, $campaign->keyword,
        );

        if ($occupied < $slots) {
            $this->activate($campaign, $occupied + 1);
        } else {
            $campaign->update(['status' => 'queued']);
        }
    }

    /**
     * Зайн төлөв: [нийт, эзэлсэн, дараалалд].
     */
    public function slotState(string $type, ?int $categoryId = null, ?string $district = null, ?string $city = null, ?string $keyword = null): array
    {
        $this->sync();

        $slots = (int) config("billing.ads.{$type}.slots", 3);
        $running = Campaign::query()->running()
            ->where('type', $type)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($district, fn ($q) => $q->where('district', $district))
            ->when($city, fn ($q) => $q->where('city', $city))
            ->when($keyword, fn ($q) => $q->where('keyword', $keyword))
            ->orderBy('slot')
            ->get(['id', 'slot', 'ends_at', 'business_id']);

        $queued = Campaign::query()
            ->where('status', 'queued')
            ->where('type', $type)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($district, fn ($q) => $q->where('district', $district))
            ->when($city, fn ($q) => $q->where('city', $city))
            ->when($keyword, fn ($q) => $q->where('keyword', $keyword))
            ->count();

        return [
            'total' => $slots,
            'occupied' => $running->count(),
            'queued' => $queued,
            'running' => $running,
        ];
    }
}
