<?php

namespace App\Http\Controllers\Api\V1\Console;

use App\Http\Controllers\Controller;
use App\Models\BranchStat;
use App\Models\Organization;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Бизнес зөвлөлийн статистик (Стандарт/Бизнес эрхэд нээлттэй,
 * үнэгүй эрхэд үндсэн тоонууд л харагдана).
 */
class StatsController extends Controller
{
    public function show(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($organization->owner_id === $request->user()->id, 403);

        $days = min(90, max(7, (int) $request->query('days', 30)));
        $from = now()->subDays($days)->toDateString();

        $branchIds = $organization->businesses()->with('branches:id,business_id')->get()
            ->flatMap(fn ($b) => $b->branches->pluck('id'));

        $stats = BranchStat::whereIn('branch_id', $branchIds)
            ->where('date', '>=', $from)
            ->get();

        $prevStats = BranchStat::whereIn('branch_id', $branchIds)
            ->whereBetween('date', [now()->subDays($days * 2)->toDateString(), $from])
            ->get();

        $delta = fn (int $current, int $prev) => $prev > 0 ? round(($current - $prev) / $prev * 100) : null;

        $totals = [
            'views' => $stats->sum('views'),
            'calls' => $stats->sum('calls'),
            'directions' => $stats->sum('directions'),
            'views_delta' => $delta($stats->sum('views'), $prevStats->sum('views')),
            'calls_delta' => $delta($stats->sum('calls'), $prevStats->sum('calls')),
            'directions_delta' => $delta($stats->sum('directions'), $prevStats->sum('directions')),
        ];

        // Графикийн цуврал (3 хоногийн bucket)
        $series = $stats->groupBy(fn (BranchStat $s) => $s->date->toDateString())
            ->map(fn ($group, $date) => [
                'date' => $date,
                'views' => $group->sum('views'),
                'calls' => $group->sum('calls'),
            ])
            ->sortKeys()
            ->values();

        // Хэрхэн олсон
        $sourceTotals = [
            'category' => $stats->sum('views_category'),
            'search' => $stats->sum('views_search'),
            'map' => $stats->sum('views_map'),
            'direct' => $stats->sum('views_direct'),
        ];
        $sourceSum = max(1, array_sum($sourceTotals));

        $sources = collect([
            ['name' => 'Ангиллын хайлт', 'value' => $sourceTotals['category']],
            ['name' => 'Шууд хайлт (нэрээр)', 'value' => $sourceTotals['search']],
            ['name' => 'Зураглал', 'value' => $sourceTotals['map']],
            ['name' => 'Шууд холбоос', 'value' => $sourceTotals['direct']],
        ])->map(fn ($s) => [...$s, 'pct' => round($s['value'] / $sourceSum * 100)]);

        // Хариу хүлээж буй сэтгэгдлүүд
        $pendingReviews = Review::whereIn('branch_id', $branchIds)
            ->whereNull('reply')
            ->with(['user', 'branch'])
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'totals' => $totals,
            'series' => $series,
            'sources' => $sources,
            'pending_reviews' => \App\Http\Resources\ReviewResource::collection($pendingReviews),
            'analytics_enabled' => (bool) ($organization->planConfig()['analytics'] ?? false),
        ]);
    }
}
