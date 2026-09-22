<?php

namespace App\Http\Controllers;

use App\Models\Master;
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
}
