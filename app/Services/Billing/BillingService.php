<?php

namespace App\Services\Billing;

use App\Models\Campaign;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrderPaid;
use App\Services\Byl\BylClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Захиалга (эрхийн бичиг + салбарын нэмэлт + онцлох байршил) үүсгэж,
 * byl.mn нэхэмжлэх гаргах, төлбөр батлагдахад идэвхжүүлэх.
 */
class BillingService
{
    public function __construct(
        protected BylClient $byl,
        protected CampaignService $campaigns,
    ) {
    }

    /**
     * @param array{
     *   plan?: string|null,
     *   extra_branches?: int,
     *   campaigns?: array<int, array{type: string, business_id: int, branch_id?: int|null, category_id?: int|null, district?: string|null, city?: string|null, keyword?: string|null, days: int}>
     * } $payload
     */
    public function createOrder(User $user, Organization $organization, array $payload): Order
    {
        $items = [];
        $total = 0;

        // --- Эрхийн бичиг --------------------------------------------------
        $plan = $payload['plan'] ?? null;

        $planPeriod = $payload['plan_period'] ?? 'yearly';

        if ($plan !== null && $plan !== 'free') {
            $planConfig = config("billing.plans.{$plan}");

            if ($planConfig === null) {
                throw ValidationException::withMessages(['plan' => 'Буруу эрхийн бичиг сонгосон байна.']);
            }

            // Сарын эсвэл жилийн үнэ — сарын үнэгүй эрхийг сараар зарахгүй
            if ($planPeriod === 'monthly' && empty($planConfig['price_monthly'])) {
                throw ValidationException::withMessages(['plan' => 'Энэ эрх зөвхөн жилээр зарагдана.']);
            }

            $planPrice = $planPeriod === 'monthly' ? (int) $planConfig['price_monthly'] : (int) $planConfig['price'];
            $planLabel = $planPeriod === 'monthly' ? '1 сар' : $planConfig['term_years'].' жил';

            $total += $planPrice;
            $items[] = [
                'type' => 'plan',
                'name' => $planConfig['name'].' эрх · '.$planLabel,
                'meta' => $organization->name,
                'amount' => $planPrice,
                'discount' => 0,
                'payload' => ['plan' => $plan, 'period' => $planPeriod],
            ];
        }

        // --- Салбарын нэмэлт ------------------------------------------------
        $extraBranches = max(0, (int) ($payload['extra_branches'] ?? 0));

        if ($extraBranches > 0) {
            $addonPrice = (int) config('billing.branch_addon_price');
            $amount = $addonPrice * $extraBranches;
            $total += $amount;
            $items[] = [
                'type' => 'branch_addon',
                'name' => "Салбар ({$extraBranches} нэмэлт)",
                'meta' => '+₮'.number_format($addonPrice).' тутам',
                'amount' => $amount,
                'discount' => 0,
                'payload' => ['count' => $extraBranches],
            ];
        }

        // --- Онцлох байршил ---------------------------------------------------
        $effectivePlan = $plan !== null && $plan !== 'free' ? $plan : $organization->effectivePlan();
        $pendingCampaigns = [];

        foreach ($payload['campaigns'] ?? [] as $c) {
            $adConfig = config("billing.ads.{$c['type']}");

            if ($adConfig === null) {
                throw ValidationException::withMessages(['campaigns' => 'Буруу сурталчилгааны төрөл.']);
            }

            $days = (int) $c['days'];
            $price = $adConfig['prices'][$days] ?? null;

            if ($price === null) {
                throw ValidationException::withMessages(['campaigns' => 'Хугацаа 7, 14 эсвэл 30 хоног байна.']);
            }

            $discount = 0;
            if ($effectivePlan === 'business' && ($adConfig['business_plan_discount'] ?? 0) > 0) {
                $discount = (int) round($price * $adConfig['business_plan_discount']);
            }

            // Зай дүүрсэн targeting-д худалдахгүй — «нэг ангилалд ихдээ 3 зар» хатуу лимит
            $slotState = $this->campaigns->slotState(
                $c['type'],
                $c['category_id'] ?? null,
                $c['district'] ?? null,
                $c['city'] ?? null,
                ! empty($c['keyword']) ? mb_strtolower(trim($c['keyword'])) : null,
            );

            if ($slotState['occupied'] + $slotState['queued'] >= $slotState['total']) {
                $freesAt = $slotState['running']->min('ends_at');

                throw ValidationException::withMessages([
                    'campaigns' => 'Энэ байршилд бүх зай эзлэгдсэн байна ('
                        .$slotState['total'].' зай).'
                        .($freesAt ? ' Ойрын зай '.$freesAt->format('Y-m-d').'-нд суларна.' : ''),
                ]);
            }

            $total += $price - $discount;

            $metaParts = array_filter([
                $c['category_name'] ?? null,
                $c['district'] ?? null,
                $c['city'] ?? null,
                ! empty($c['keyword']) ? '“'.$c['keyword'].'”' : null,
                $days.' хоног',
            ]);

            $items[] = [
                'type' => 'campaign',
                'name' => $adConfig['name'],
                'meta' => implode(' · ', $metaParts),
                'amount' => $price,
                'discount' => $discount,
                'payload' => $c,
            ];

            $pendingCampaigns[] = [
                'business_id' => $c['business_id'],
                'branch_id' => $c['branch_id'] ?? null,
                'type' => $c['type'],
                'category_id' => $c['category_id'] ?? null,
                'district' => $c['district'] ?? null,
                'city' => $c['city'] ?? null,
                'keyword' => ! empty($c['keyword']) ? mb_strtolower(trim($c['keyword'])) : null,
                'days' => $days,
                'price' => $price - $discount,
            ];
        }

        if ($total <= 0 && $items === []) {
            throw ValidationException::withMessages(['total' => 'Захиалга хоосон байна.']);
        }

        $order = DB::transaction(function () use ($user, $organization, $items, $pendingCampaigns, $total) {
            $order = Order::create([
                'number' => Order::generateNumber(),
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'total' => $total,
                'status' => 'pending',
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            foreach ($pendingCampaigns as $c) {
                Campaign::create([
                    ...$c,
                    'organization_id' => $organization->id,
                    'order_id' => $order->id,
                    'status' => 'pending_payment',
                ]);
            }

            return $order;
        });

        if (! $this->byl->enabled()) {
            // Fail-open хориотой: авто-төлөлт зөвхөн local/testing орчинд.
            if (! app()->environment('local', 'testing')) {
                Log::error('byl.mn: токен тохируулаагүй байхад захиалга үүсгэх оролдлого', ['order' => $order->number]);
                $this->voidOrder($order);

                throw ValidationException::withMessages([
                    'total' => 'Төлбөрийн систем түр ажиллахгүй байна. Хэсэг хугацааны дараа дахин оролдоно уу.',
                ]);
            }

            // Dev горим: төлбөрийг шууд батална.
            $this->markPaid($order->fresh(), ['dev' => true]);

            return $order->refresh();
        }

        // Checkout API-г transaction-ий ГАДНА дуудна (түгжээ барихгүй,
        // rollback үед byl талд эзэнгүй checkout үлдэхгүй)
        try {
            $checkout = $this->byl->createCheckout(
                $total,
                sprintf('%s — %s', $order->number, $organization->name),
                $order->number,
                url("/orders/{$order->id}/pay?return=success"),
                url("/orders/{$order->id}/pay?return=cancel"),
            );
        } catch (RequestException $e) {
            Log::error('byl.mn: checkout үүсгэж чадсангүй', ['order' => $order->number, 'status' => $e->response?->status()]);
            $this->voidOrder($order);

            throw ValidationException::withMessages([
                'total' => 'Төлбөрийн систем түр ажиллахгүй байна. Хэсэг хугацааны дараа дахин оролдоно уу.',
            ]);
        }

        $order->update([
            'byl_checkout_id' => $checkout['id'] ?? null,
            'invoice_url' => $checkout['url'] ?? null,
            'provider_payload' => $checkout,
        ]);

        return $order->refresh();
    }

    /**
     * Байгаа pending захиалгын төлөвийг byl.mn-ээс шалгах (polling fallback).
     */
    public function sync(Order $order): Order
    {
        if ($order->status !== 'pending' || $order->byl_checkout_id === null || ! $this->byl->enabled()) {
            return $order;
        }

        $checkout = $this->byl->getCheckout($order->byl_checkout_id);

        match ($checkout['status'] ?? null) {
            'complete' => $this->markPaid($order, $checkout),
            'expired' => $this->voidOrder($order, $checkout),
            default => null,
        };

        return $order->refresh();
    }

    public function voidOrder(Order $order, array $payload = [], string $status = 'void'): void
    {
        $order->update(['status' => $status, 'provider_payload' => $payload ?: $order->provider_payload]);
        $order->campaigns()->where('status', 'pending_payment')->update(['status' => 'canceled']);
    }

    /**
     * Идэвхжүүлэлт — давхар webhook-д idempotent.
     */
    public function markPaid(Order $order, array $payload = []): void
    {
        DB::transaction(function () use ($order, $payload) {
            $order = Order::lockForUpdate()->find($order->id);

            if ($order === null || $order->status === 'paid') {
                return;
            }

            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'provider_payload' => $payload ?: $order->provider_payload,
            ]);

            // 1. Эрхийн бичиг идэвхжүүлэх
            foreach ($order->items()->where('type', 'plan')->get() as $item) {
                $plan = $item->payload['plan'] ?? null;
                $planConfig = config("billing.plans.{$plan}");

                if ($planConfig === null) {
                    continue;
                }

                $organization = $order->organization;
                $period = $item->payload['period'] ?? 'yearly';

                // Ижил эрх идэвхтэй бол хугацаан дээр нь сунгана
                $from = $organization->plan === $plan
                        && $organization->plan_expires_at !== null
                        && $organization->plan_expires_at->isFuture()
                    ? $organization->plan_expires_at
                    : now();

                $organization->update([
                    'plan' => $plan,
                    'plan_term_years' => $planConfig['term_years'],
                    'plan_period' => $period,
                    'plan_started_at' => $organization->plan === $plan ? $organization->plan_started_at ?? now() : now(),
                    // Сараар бол 1 сар, жилээр бол term_years жилээр сунгана
                    'plan_expires_at' => $period === 'monthly' ? $from->copy()->addMonth() : $from->copy()->addYears($planConfig['term_years']),
                ]);

                // Бизнес эрх — баталгаажсан тэмдэг
                if (! empty($planConfig['verified_badge'])) {
                    $organization->businesses()->update(['is_verified' => true]);
                }
            }

            // 2. Салбарын нэмэлт — байгууллагын лимитийг бодитоор нэмнэ
            foreach ($order->items()->where('type', 'branch_addon')->get() as $item) {
                $count = (int) ($item->payload['count'] ?? 0);

                if ($count > 0) {
                    $order->organization->increment('extra_branches', $count);
                }
            }

            // 3. Онцлох байршил идэвхжүүлэх / дараалалд оруулах
            foreach ($order->campaigns()->where('status', 'pending_payment')->get() as $campaign) {
                $this->campaigns->activateOrQueue($campaign);
            }

            // 4. Худалдан авагчид баримт илгээнэ
            $order->user?->notify(new OrderPaid($order->load('items')));
        });
    }
}
