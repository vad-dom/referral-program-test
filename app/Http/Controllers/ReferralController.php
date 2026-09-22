<?php

namespace App\Http\Controllers;

use App\Models\Master;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function attach(Request $request, ReferralService $referrals): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        /** @var Master|null $master */
        $master = $request->attributes->get('current_master');

        if ($master === null) {
            return response()->json(['message' => 'Master not found. Set X-Master-Id header.'], 401);
        }

        $referral = $referrals->registerReferral($master, $validated['code']);

        if ($referral === null) {
            return response()->json(['message' => 'Invalid referral code or self-referral.'], 422);
        }

        return response()->json([
            'referral' => [
                'id' => $referral->id,
                'referrer_master_id' => $referral->referrer_master_id,
                'referred_master_id' => $referral->referred_master_id,
                'status' => $referral->status,
            ],
            'created' => $referral->wasRecentlyCreated,
        ], $referral->wasRecentlyCreated ? 201 : 200);
    }

    public function my(Request $request): JsonResponse
    {
        /** @var Master|null $master */
        $master = $request->attributes->get('current_master');

        if ($master === null) {
            return response()->json(['message' => 'Master not found. Set X-Master-Id header.'], 401);
        }

        $referrals = Referral::query()
            ->where('referrer_master_id', $master->id)
            ->with('referredMaster')
            ->orderBy('created_at')
            ->get();

        $earnedByReferralId = ReferralEarning::query()
            ->whereIn('referral_id', $referrals->pluck('id'))
            ->get()
            ->groupBy('referral_id')
            ->map(fn ($items) => (int) $items->sum('amount'));

        $items = $referrals->map(function (Referral $referral) use ($earnedByReferralId) {
            return [
                'name' => $referral->referredMaster->name,
                'attached_at' => $referral->created_at?->toIso8601String(),
                'counted' => $referral->status === Referral::STATUS_REWARDED,
                'earned_amount' => $earnedByReferralId->get($referral->id, 0),
            ];
        });

        return response()->json(['referrals' => $items]);
    }

    public function earnings(Request $request): JsonResponse
    {
        /** @var Master|null $master */
        $master = $request->attributes->get('current_master');

        if ($master === null) {
            return response()->json(['message' => 'Master not found. Set X-Master-Id header.'], 401);
        }

        $earningsQuery = ReferralEarning::query()
            ->where('referrer_master_id', $master->id);

        $countedReferrals = Referral::query()
            ->where('referrer_master_id', $master->id)
            ->where('status', Referral::STATUS_REWARDED)
            ->count();

        return response()->json([
            'total_accrued' => (int) (clone $earningsQuery)->sum('amount'),
            'pending' => (int) (clone $earningsQuery)->where('status', ReferralEarning::STATUS_PENDING)->sum('amount'),
            'paid' => (int) (clone $earningsQuery)->where('status', ReferralEarning::STATUS_PAID)->sum('amount'),
            'counted_referrals' => $countedReferrals,
        ]);
    }
}
