<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\Setting;
use App\Models\Tap;
use App\Models\TapMultiplier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserBooster;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class TapController extends Controller
{

    public function index(Request $request)
    {
        $range = $request->input('range', 'month');
        $search = $request->input('search');
        $minTaps = $request->input('min_taps');

        $startDate = match ($range) {
            'today' => Carbon::today(),
            'week'  => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            default => null,
        };

        $tapQuery = Tap::select(
            'user_id',
            DB::raw('SUM(tap_count) as taps_count'),
            DB::raw('SUM(earned_ldp) as earned_tokens'),
            DB::raw('MAX(created_at) as last_tap_time')
        )
            ->with('user')
            ->groupBy('user_id');

        if ($startDate) {
            $tapQuery->where('created_at', '>=', $startDate);
        }

        if ($search) {
            $tapQuery->whereHas('user', function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }
        if ($minTaps) {
            $tapQuery->havingRaw('SUM(tap_count) >= ?', [$minTaps]);
        }

        $taps = $tapQuery->orderByDesc('taps_count')->get();

        $filteredTaps = Tap::query();
        if ($startDate) $filteredTaps->where('created_at', '>=', $startDate);
        $totalTaps = $filteredTaps->sum('tap_count');
        $totalEarned = $filteredTaps->sum('earned_ldp');

        $activeUsers = $taps->count();
        $firstTap = $filteredTaps->min('created_at');
        $daysActive = Carbon::parse($firstTap)->diffInDays(Carbon::now()) ?: 1;

        $dailyAverageTaps = $totalTaps / $daysActive;
        $dailyAverageEarnings = $totalEarned / $daysActive;

        $peakHoursRaw = Tap::select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as total'));
        if ($startDate) $peakHoursRaw->where('created_at', '>=', $startDate);
        $peakHoursRaw = $peakHoursRaw->groupBy('hour')->pluck('total', 'hour')->toArray();

        $peakHours = [];
        for ($i = 0; $i < 24; $i++) {
            $peakHours[$i] = $peakHoursRaw[$i] ?? 0;
        }

        $currentRate = 10;
        $dailyCap = 10;
        $multipliers = TapMultiplier::get();



        return view('admin.taps.index', compact(
            'taps',
            'totalTaps',
            'totalEarned',
            'activeUsers',
            'dailyAverageTaps',
            'dailyAverageEarnings',
            'peakHours',
            'currentRate',
            'dailyCap',
            'multipliers'
        ));
    }

    /**
     * Récupérer le classement hebdomadaire
     */
    public function getWeeklyLeaderboard(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $tapQuery = Tap::select(
            'user_id',
            DB::raw('SUM(tap_count) as taps_count'),
            DB::raw('SUM(earned_ldp) as earned_tokens'),
            DB::raw('MAX(created_at) as last_tap_time')
        )
            ->with('user')
            ->whereBetween('created_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ])
            ->groupBy('user_id')
            ->orderByDesc('taps_count')
            ->take(10)
            ->get();

        // Formater les données pour l'API
        $leaderboard = $tapQuery->map(function ($tap, $index) use ($user) {
            return [
                'rank' => $index + 1,
                'user_id' => $tap->user_id,
                'username' => $tap->user->name ?? 'Utilisateur',
                'taps_count' => (int) $tap->taps_count,
                'earned_tokens' => (float) $tap->earned_tokens,
                'last_tap_time' => $tap->last_tap_time,
                'is_current_user' => $tap->user_id === $user->id,
            ];
        });

        // Trouver la position de l'utilisateur actuel s'il n'est pas dans le top 10
        $userRank = null;
        $userStats = null;

        if (!$leaderboard->contains('is_current_user', true)) {
            $allUsersTaps = Tap::select(
                'user_id',
                DB::raw('SUM(tap_count) as taps_count'),
                DB::raw('SUM(earned_ldp) as earned_tokens')
            )
                ->whereBetween('created_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ])
                ->groupBy('user_id')
                ->orderByDesc('taps_count')
                ->get();

            $userPosition = $allUsersTaps->search(function ($item) use ($user) {
                return $item->user_id === $user->id;
            });

            if ($userPosition !== false) {
                $userTap = $allUsersTaps[$userPosition];
                $userRank = $userPosition + 1;
                $userStats = [
                    'rank' => $userRank,
                    'user_id' => $user->id,
                    'username' => $user->name ?? 'Vous',
                    'taps_count' => (int) $userTap->taps_count,
                    'earned_tokens' => (float) $userTap->earned_tokens,
                    'is_current_user' => true,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'leaderboard' => $leaderboard->values(),
            'user_stats' => $userStats,
            'period' => [
                'start' => Carbon::now()->startOfWeek()->toDateTimeString(),
                'end' => Carbon::now()->endOfWeek()->toDateTimeString(),
            ]
        ]);
    }

    public function accountStats(Request $request)
    {
        $telegramId = $request->query('telegram_id');
        $user = User::where('telegram_id', $telegramId)->first();

        if (!$user) {
            return redirect()->back()->with("error", "Utilisateur non trouvé");
        }
        return response()->json([
            'days_streak' => $user->days_streak ?? 0,
            'total_mir_earned' => $user->total_mir_earned ?? 0,
            'active_stakes' => $user->staking()->count(),
            'total_bets' => $user->bets()->count(),
        ]);
    }


    public function tap(Request $request)
    {
        // Log de debug pour voir si on arrive même jusqu'ici
        Log::info('=== DÉBUT TAP CONTROLLER ===', [
            'headers' => $request->headers->all(),
            'auth_check' => Auth::check(),
            'auth_id' => Auth::id(),
            'sanctum_token' => $request->bearerToken(),
        ]);

        try {
            Log::info('Début de la fonction tap', [
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            $request->validate([
                'value' => 'required|integer|min:1'
            ]);

            Log::info('Validation passée');

            $user = User::find(Auth::id());
            if (!$user) {
                Log::error('Utilisateur non trouvé', ['auth_id' => Auth::id()]);
                return response()->json(['error' => 'Utilisateur non trouvé'], 404);
            }

            Log::info('Utilisateur trouvé', ['user_id' => $user->id]);

            // ✅ Vérifier le blocage administratif (is_blocked)
            if ($user->is_blocked) {
                Log::warning('Utilisateur bloqué par admin', ['user_id' => $user->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte a été bloqué par un administrateur.',
                    'blocked_by_admin' => true,
                    'is_blocked' => true,
                ], 403);
            }

            $tapValue = $request->input('value');
            Log::info('Tap value', ['value' => $tapValue]);


        $lastTapDate = $user->last_tap_at ? Carbon::parse($user->last_tap_at)->toDateString() : null;
        $today = now()->toDateString();

        if (($lastTapDate && $lastTapDate != $today) || ($user->tapped_out_at && $user->tapped_out_at != $today)
        ) {

            $user->tapped_out_at = null;
            // $user->shown_at_30 = false; // Plus nécessaire - captcha désactivé
            // $user->shown_at_60 = false; // Plus nécessaire - captcha désactivé
            // $user->shown_at_90 = false; // Plus nécessaire - captcha désactivé
            $user->taps = 0;
            $user->save();
            $user = $user->fresh();
            Cache::forget("tap_days_count_{$user->id}");
        }

        // Récupérer le booster actif
        $userBooster = UserBooster::with('booster')
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        $multiplier = (int) ($userBooster?->booster->coefficient ?? 1);
        $gainPerTap = (float) (Setting::where('tag', 'GAIN_PAR_TAP')->value('value') ?? 0.00000005);

        // Récupérer la limite de taps basée sur le coefficient
        $tapLimit = TapMultiplier::where('coefficient', $multiplier)->value('required_taps')
            ?? TapMultiplier::where('coefficient', 1)->value('required_taps')
            ?? 500;



        $tapDayKey = "tap_days_count_{$user->id}";
        $alreadyTapped = Cache::get($tapDayKey, 0);

        // ✅ Synchroniser le cache avec la base de données si nécessaire
        $dbTaps = $user->taps ?? 0;
        if ($alreadyTapped == 0 && $dbTaps > 0) {
            $alreadyTapped = $dbTaps;
            Cache::put($tapDayKey, $dbTaps, now()->addDay());
            Log::info("🔄 Synchronisation cache dans tap() - Cache: 0, BD: $dbTaps");
        }

        $availableTaps = $tapLimit - $alreadyTapped;

        $bloque = false;


        $DayTapCount = Cache::get($tapDayKey, 0) + $tapValue;
        $progress = $tapLimit > 0 ? ($DayTapCount / $tapLimit) : 0;

        // Vérifier si l'utilisateur a déjà atteint la limite
        if ($user->tapped_out_at === now()->toDateString()) {
            $bloque = true;
            Cache::forget("tap_days_count_{$user->id}");

            return response()->json([
                'success' => false,
                'message' => 'Limite de taps atteinte pour aujourd\'hui',
                'earned' => 0,
                'new_balance' => $user->balance_ldp,
                'tap_count' => $tapLimit,
                'multiplier' => $multiplier,
                'bloque' => true,
            ]);
        }

        // Tronquer si dépassement
        if ($tapValue > $availableTaps) {
            $tapValue = max(0, $availableTaps);
            $bloque = true;

            // Enregistrer les derniers taps
            if ($tapValue > 0) {
                Tap::create([
                    'user_id' => $user->id,
                    'tap_count' => $tapValue,
                    'earned_ldp' => $tapValue * $gainPerTap,
                ]);
            }

            $user->tapped_out_at = now()->toDateString();
            $user->save();
            $user = $user->fresh();
        }

        $earned = $tapValue * $gainPerTap;
        $DayTapCount = Cache::get($tapDayKey, 0) + $tapValue;

        // Sauvegarder dans le cache pour 24h
        Cache::put($tapDayKey, $DayTapCount, now()->addDay());

        // Enregistrer les taps si pas bloqué
        if (!$bloque && $tapValue > 0) {
            Tap::create([
                'user_id' => $user->id,
                'tap_count' => $tapValue,
                'earned_ldp' => $earned,
            ]);
        }

        // Mettre à jour l'utilisateur
        $user->last_tap_at = now();
        $user->balance_ldp += $earned;
        $user->taps = $DayTapCount;
        $user->save();
        $user = $user->fresh();

        // Gestion du parrainage (premier tap seulement)
        $gain_par_parrainage = (float) (Setting::where('tag', 'GAIN_PARRAINAGE')->value('value') ?? 0.01);

        if ($user->referrer_id) {
            $parrain = User::find($user->referrer_id);

            if ($parrain && $parrain->id !== $user->id) {
                // Vérifier si le parrain a déjà été payé pour ce filleul
                $verification_versement_parrain = Referral::where("user_id", $user->id)
                    ->where("referred_user_id", $parrain->id)
                    ->first();

                if (!$verification_versement_parrain) {
                    // Créditer le parrain
                    $parrain->balance_ldp += $gain_par_parrainage;
                    $parrain->save();

                    // Enregistrer le parrainage
                    Referral::create([
                        'user_id' => $user->id,
                        'referred_user_id' => $parrain->id,
                        'reward_ldp' => $gain_par_parrainage
                    ]);

                    // Créer une transaction
                    Transaction::create([
                        'user_id' => $parrain->id,
                        'type' => 'reward',
                        'amount' => $gain_par_parrainage,
                        'currency' => "LDP",
                        'status' => 'completed',
                        'reference' => strtoupper(Str::random(12)),
                        'source' => 'internal',
                        'method' => "referral",
                        'description' => "Récompense de parrainage",
                        "ip_address" => $request->ip(),
                        "user_agent" => $request->header('User-Agent'),
                        'metadata' => json_encode([
                            'referral_user_id' => $user->id,
                            'referral_username' => $user->username ?? $user->email
                        ]),
                        "status_detail" => null,
                    ]);
                }
            }
        }

        Log::info('Fin de la fonction tap - succès', [
            'user_id' => $user->id,
            'earned' => $earned,
            'tap_count' => $DayTapCount,
            'tap_limit' => $tapLimit,
            'multiplier' => $multiplier,
            'bloque' => $bloque
        ]);

        return response()->json([
            'success' => true,
            'earned' => $earned,
            'new_balance' => $user->balance_ldp,
            'tap_count' => $DayTapCount,
            'tap_limit' => $tapLimit,
            'multiplier' => $multiplier,
            'bloque' => $bloque,
            'available_taps' => max(0, $tapLimit - $DayTapCount),
        ]);

        } catch (\Exception $e) {
            Log::error('Erreur dans la fonction tap', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage(),
                'error' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Récupérer les statistiques de tap de l'utilisateur
     */
    public function getTapStats(Request $request)
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

        $multiplier = (int) ($userBooster?->booster->coefficient ?? 1);
        $tapLimit = TapMultiplier::where('coefficient', $multiplier)->value('required_taps')
            ?? TapMultiplier::where('coefficient', 1)->value('required_taps')
            ?? 500;
        $tapDayKey = "tap_days_count_{$user->id}";
        $currentTaps = Cache::get($tapDayKey, 0);

        // ✅ Utiliser la valeur de la base de données si le cache est vide/incohérent
        $dbTaps = $user->taps ?? 0;
        if ($currentTaps == 0 && $dbTaps > 0) {
            $currentTaps = $dbTaps;
            Log::info("🔄 Synchronisation getTapStats - Utilisation des taps de la BD: $dbTaps");
        }

        return response()->json([
            'success' => true,
            'tap_count' => $currentTaps,
            'tap_limit' => $tapLimit,
            'multiplier' => $multiplier,
            'balance_ldp' => $user->balance_ldp,
            'available_taps' => max(0, $tapLimit - $currentTaps),
            'is_blocked' => $user->tapped_out_at === now()->toDateString(),
        ]);
    }


    public function getBalances(Request $request)
    {
        $telegramId = $request->input('telegram_id');

        if (!$telegramId) {
            return response()->json(['error' => 'telegram_id manquant'], 400);
        }

        $user = User::where('telegram_id', $telegramId)->first();
        if (!$user) {
            return redirect()->back()->with("error", "Utilisateur non trouvé");
        }

        return response()->json([
            'ldp' => $user->balance_ldp ?? 0,
            'usdt' => $user->balance_usdt ?? 0,
        ]);
    }


    /*
     * ============================================================
     * FONCTIONS DE GESTION DU CAPTCHA DÉSACTIVÉES
     * Ces fonctions ne sont plus utilisées car le système de
     * vérification humaine a été supprimé. Les utilisateurs peuvent
     * continuer à taper jusqu'à atteindre leur limite journalière.
     * ============================================================
     */

    // /**
    //  * Marquer qu'un captcha a été montré à l'utilisateur
    //  */
    // public function markCaptchaShown(Request $request)
    // {
    //     $request->validate([
    //         'threshold' => 'required|in:30,60,90'
    //     ]);

    //     $user = User::find(Auth::id());
    //     if (!$user) {
    //         return response()->json(['error' => 'Utilisateur non trouvé'], 404);
    //     }

    //     $threshold = $request->input('threshold');

    //     // Mettre à jour le flag correspondant
    //     switch ($threshold) {
    //         case '30':
    //             $user->shown_at_30 = true;
    //             break;
    //         case '60':
    //             $user->shown_at_60 = true;
    //             break;
    //         case '90':
    //             $user->shown_at_90 = true;
    //             break;
    //     }

    //     $user->save();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Captcha marqué comme affiché',
    //         'shown_at_30' => (bool) $user->shown_at_30,
    //         'shown_at_60' => (bool) $user->shown_at_60,
    //         'shown_at_90' => (bool) $user->shown_at_90,
    //     ]);
    // }

    // /**
    //  * Gérer l'échec du captcha après 3 tentatives
    //  */
    // public function handleFailedCaptcha(Request $request)
    // {
    //     $user = User::find(Auth::id());
    //     if (!$user) {
    //         return response()->json(['error' => 'Utilisateur non trouvé'], 404);
    //     }

    //     Log::warning("Utilisateur bloqué après 3 erreurs captcha", [
    //         'user_id' => $user->id,
    //         'is_blocked_before' => $user->is_blocked
    //     ]);

    //     // Mettre l'utilisateur comme bloqué
    //     $user->is_blocked = 1;
    //     $user->save();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Utilisateur bloqué suite à 3 erreurs captcha',
    //         'is_blocked' => true
    //     ]);
    // }

    // /**
    //  * Gérer la réussite du captcha - mettre le bon flag shown_at_X à true
    //  */
    // public function handleCaptchaSuccess(Request $request)
    // {
    //     $user = User::find(Auth::id());
    //     if (!$user) {
    //         return response()->json(['error' => 'Utilisateur non trouvé'], 404);
    //     }

    //     // Déterminer quelle étape de captcha nous sommes
    //     $stepToShow = 30; // Par défaut
    //     if (!$user->shown_at_30) {
    //         $stepToShow = 30;
    //     } elseif (!$user->shown_at_60) {
    //         $stepToShow = 60;
    //     } elseif (!$user->shown_at_90) {
    //         $stepToShow = 90;
    //     } else {
    //         $stepToShow = 90; // Déjà tout montré
    //     }

    //     Log::info("Captcha réussi - Passage à l'étape $stepToShow", [
    //         'user_id' => $user->id,
    //         'is_blocked_before' => $user->is_blocked,
    //         'shown_at_30_before' => $user->shown_at_30,
    //         'shown_at_60_before' => $user->shown_at_60,
    //         'shown_at_90_before' => $user->shown_at_90,
    //         'step_to_show' => $stepToShow,
    //     ]);

    //     // Remettre is_blocked à false et mettre le flag shown_at_X approprié à true
    //     $user->is_blocked = 0;

    //     // Mettre uniquement le flag de l'étape actuelle à true
    //     if ($stepToShow == 30) {
    //         $user->shown_at_30 = true;
    //     } elseif ($stepToShow == 60) {
    //         $user->shown_at_60 = true;
    //     } elseif ($stepToShow == 90) {
    //         $user->shown_at_90 = true;
    //     }

    //     $user->save();

    //     return response()->json([
    //         'success' => true,
    //         'message' => "Captcha réussi - Passage à l'étape $stepToShow",
    //         'is_blocked' => false,
    //         'shown_at_30' => (bool) $user->shown_at_30,
    //         'shown_at_60' => (bool) $user->shown_at_60,
    //         'shown_at_90' => (bool) $user->shown_at_90,
    //         'step_shown' => $stepToShow,
    //     ]);
    // }

    /**
     * Vérifier si l'utilisateur a été débloqué (limite journalière ou captcha)
     */
    public function checkUnblockStatus(Request $request)
    {
        $user = User::find(Auth::id());
        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $needsRefresh = false;
        $today = now()->toDateString();

        // État actuel du blocage
        $isBlockedByDailyLimit = $user->tapped_out_at === $today;
        $isBlockedByCaptcha = $user->is_blocked == true;
        $isCurrentlyBlocked = $isBlockedByDailyLimit || $isBlockedByCaptcha;

        // Vérifier si l'utilisateur était bloqué par la limite journalière mais ne l'est plus
        if ($user->tapped_out_at !== $today) {
            Cache::forget("tap_days_count_{$user->id}");
            $needsRefresh = true;
        }

        // Vérifier si l'utilisateur était bloqué par captcha mais ne l'est plus
        // Si déblocage par admin le même jour, on ne reset pas les taps
        if (!$user->is_blocked) {
            $needsRefresh = true;
        }

        // Ajouter l'information si c'est un nouveau jour pour le frontend
        $lastTapDate = $user->last_tap_at ? Carbon::parse($user->last_tap_at)->toDateString() : null;
        $isNewDay = $lastTapDate !== $today;

        return response()->json([
            'success' => true,
            'needs_refresh' => $needsRefresh,
            'tapped_out_at' => $user->tapped_out_at,
            'is_blocked_by_admin' => $isBlockedByCaptcha, // Renommé pour clarté
            'is_blocked_by_daily_limit' => $isBlockedByDailyLimit,
            'is_blocked' => $isCurrentlyBlocked,
            'is_new_day' => $isNewDay, // ✅ Information pour le frontend
            'last_tap_date' => $lastTapDate,
            'today' => $today,
        ]);
    }
}
