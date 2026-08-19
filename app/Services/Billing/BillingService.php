<?php

namespace App\Services\Billing;

use App\Models\Campaign;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use App\Services\Byl\BylClient;
use Illuminate\Support\Facades\DB;
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

        if ($plan !== null && $plan !== 'free') {
            $planConfig = config("billing.plans.{$plan}");

            if ($planConfig === null) {
                throw ValidationException::withMessages(['plan' => 'Буруу эрхийн бичиг сонгосон байна.']);
            }

            $total += $planConfig['price'];
            $items[] = [
                'type' => 'plan',
                'name' => $planConfig['name'].' эрх · '.$planConfig['term_years'].' жил',
                'meta' => $organization->name,
                'amount' => $planConfig['price'],
                'discount' => 0,
                'payload' => ['plan' => $plan],
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

        return DB::transaction(function () use ($user, $organization, $items, $pendingCampaigns, $total) {
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

            if (! $this->byl->enabled()) {
                // Dev горим: төлбөрийг шууд батална.
                $this->markPaid($order->fresh(), ['dev' => true]);

                return $order->refresh();
            }

            $invoice = $this->byl->createInvoice(
                $total,
                sprintf('%s — %s', $order->number, $organization->name),
            );

            $order->update([
                'byl_invoice_id' => $invoice['id'] ?? null,
                'invoice_url' => $invoice['url'] ?? null,
                'provider_payload' => $invoice,
            ]);

            return $order->refresh();
        });
    }

    /**
     * Байгаа pending захиалгын төлөвийг byl.mn-ээс шалгах (polling fallback).
     */
    public function sync(Order $order): Order
    {
        if ($order->status !== 'pending' || $order->byl_invoice_id === null || ! $this->byl->enabled()) {
            return $order;
        }

        $invoice = $this->byl->getInvoice($order->byl_invoice_id);

        match ($invoice['status'] ?? null) {
            'paid' => $this->markPaid($order, $invoice),
            'void' => $order->update(['status' => 'void', 'provider_payload' => $invoice]),
            default => null,
        };

        return $order->refresh();
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

                // Ижил эрх идэвхтэй бол хугацаан дээр нь сунгана
                $from = $organization->plan === $plan
                        && $organization->plan_expires_at !== null
                        && $organization->plan_expires_at->isFuture()
                    ? $organization->plan_expires_at
                    : now();

                $organization->update([
                    'plan' => $plan,
                    'plan_term_years' => $planConfig['term_years'],
                    'plan_started_at' => $organization->plan === $plan ? $organization->plan_started_at ?? now() : now(),
                    'plan_expires_at' => $from->copy()->addYears($planConfig['term_years']),
                ]);

                // Бизнес эрх — баталгаажсан тэмдэг
                if (! empty($planConfig['verified_badge'])) {
                    $organization->businesses()->update(['is_verified' => true]);
                }
            }

            // 2. Онцлох байршил идэвхжүүлэх / дараалалд оруулах
            foreach ($order->campaigns()->where('status', 'pending_payment')->get() as $campaign) {
                $this->campaigns->activateOrQueue($campaign);
            }
        });
    }
}
