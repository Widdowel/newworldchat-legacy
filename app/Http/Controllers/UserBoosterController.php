<?php

namespace App\Http\Controllers;

use App\Models\TapMultiplier;
use App\Models\User;
use App\Models\UserBooster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserBoosterController extends Controller
{
    public function getActiveBooster(Request $request)
    {
        $user = User::find(Auth::id());
        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Récupérer le booster actif
        $userBooster = UserBooster::with('booster')
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$userBooster) {
            // Pas de booster actif, utiliser les valeurs par défaut
            $tapDayKey = "tap_days_count_{$user->id}";
            $currentTaps = Cache::get($tapDayKey, 0);
            $defaultLimit = TapMultiplier::where('coefficient', 1)->value('required_taps') ?? 500;

            return response()->json([
                'success' => true,
                'has_booster' => false,
                'coefficient' => 1,
                'tap_limit' => $defaultLimit,
                'current_taps' => $currentTaps,
                'balance_ldp' => $user->balance_ldp,
                'is_blocked' => $user->tapped_out_at === now()->toDateString(),
            ]);
        }

        // Récupérer la limite basée sur le coefficient
        $coefficient = (int) $userBooster->booster->coefficient;
        $tapLimit = TapMultiplier::where('coefficient', $coefficient)->value('required_taps')
            ?? TapMultiplier::where('coefficient', 1)->value('required_taps')
            ?? 500;

        $tapDayKey = "tap_days_count_{$user->id}";
        $currentTaps = Cache::get($tapDayKey, 0);

        return response()->json([
            'success' => true,
            'has_booster' => true,
            'booster' => [
                'id' => $userBooster->booster->id,
                'name' => $userBooster->booster->name,
                'coefficient' => $userBooster->booster->coefficient,
                'expires_at' => $userBooster->expires_at,
            ],
            'coefficient' => $coefficient,
            'tap_limit' => $tapLimit,
            'current_taps' => $currentTaps,
            'balance_ldp' => $user->balance_ldp,
            'is_blocked' => $user->tapped_out_at === now()->toDateString(),
        ]);
    }
}
