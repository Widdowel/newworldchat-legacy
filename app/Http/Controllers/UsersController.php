<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Setting;
use App\Models\TapMultiplier;
use App\Models\Transaction;
use App\Models\UserBooster;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UsersController extends Controller
{
    public function index()
    {
        // Logic to retrieve and display users
        return view('admin.users.index');
    }


    public function getProfile(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Récupérer les taux de conversion
        $conversion_ldp_usdt = Setting::where("tag", "VALEUR_CONVERSION_LDP_VERS_USDT")->value('value') ?? 2;
        $conversion_usdt_ldp = Setting::where("tag", "VALEUR_CONVERSION_USDT_VERS_LDP")->value('value') ?? 1;

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'code' => $user->code,
                'telegram_id' => $user->telegram_id,
                'balance_ldp' => (float) $user->balance_ldp,
                'balance_usdt' => (float) $user->balance_usdt,
                'langue' => $user->langue ?? 'fr',
                'created_at' => $user->created_at,
                'taps' => $user->taps ?? 0,
            ],
            'rates' => [
                'ldp_to_usdt' => (float) $conversion_ldp_usdt,
                'usdt_to_ldp' => (float) $conversion_usdt_ldp,
            ],
            'balance_converted' => [
                'ldp_in_usdt' => $user->balance_ldp * $conversion_ldp_usdt,
                'usdt_in_ldp' => $user->balance_usdt * $conversion_usdt_ldp,
            ]
        ]);
    }

    /**
     * Mettre à jour le profil
     */
    public function updateProfile(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $request->validate([
            'username' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'langue' => 'nullable|in:fr,en',
        ]);

        if ($request->has('username')) {
            $user->name = $request->username;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('langue')) {
            $user->langue = $request->langue;
        }

        $user->save();
        $user = $user->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'langue' => $user->langue,
            ]
        ]);
    }

    /**
     * Statistiques utilisateur
     */
    public function getStatistics(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Calcul du streak de jours consécutifs
        $days_streak = 0;
        if ($user->last_tap_at) {
            $lastTap = \Carbon\Carbon::parse($user->last_tap_at);
            $today = \Carbon\Carbon::today();

            if ($lastTap->isToday()) {
                $days_streak = 1; // Simplifié, à adapter selon ta logique
            }
        }

        // Total LDP gagné (depuis les transactions)
        $total_ldp_earned = Transaction::where('user_id', $user->id)
            ->where('currency', 'LDP')
            ->whereIn('type', ['reward', 'deposit'])
            ->where('status', 'completed')
            ->sum('amount');

        // Nombre de stakes actifs (à adapter selon ton modèle)
        $active_stakes = 0; // Tu peux ajouter une table stakes si nécessaire

        // Total de paris (à adapter)
        $total_bets = 0;

        return response()->json([
            'success' => true,
            'statistics' => [
                'days_streak' => $days_streak,
                'total_ldp_earned' => (float) $total_ldp_earned,
                'active_stakes' => $active_stakes,
                'total_bets' => $total_bets,
                'total_taps' => $user->taps ?? 0,
            ]
        ]);
    }

    /**
     * Historique des transactions avec pagination
     */
    public function getTransactions(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $transactions = Transaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'transactions' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'reference' => $transaction->reference,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at,
                    'metadata' => json_decode($transaction->metadata),
                ];
            }),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }


    /**
     * Récupérer toutes les informations de l'utilisateur pour synchroniser l'app
     */
    public function me(Request $request)
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

        // 🔍 LOGS DÉTAILLÉS POUR COMPRENDRE
        Log::info("=== ANALYSE UTILISATEUR {$user->id} ===", [
            'is_blocked' => $user->is_blocked,
            'tapped_out_at' => $user->tapped_out_at,
            'last_tap_at' => $user->last_tap_at,
            'taps' => $user->taps,
            'cache_current_taps' => $currentTaps,
            'today' => $today = now()->toDateString(),
        ]);

        // ✅ SYNCHRONISER LE CACHE AVEC LA BASE si décalage détecté
        if ($user->taps > 0 && $currentTaps == 0) {
            Log::info("🔄 Synchronisation cache avec la base - Cache: $currentTaps, BD: {$user->taps}");
            Cache::put("tap_days_count_{$user->id}", $user->taps);
            $currentTaps = $user->taps;
        }

        // RÉINITIALISER SEULEMENT SI VRAIMENT NÉCESSAIRE
        $needsReset = false;
        $lastTapDate = $user->last_tap_at ? Carbon::parse($user->last_tap_at)->toDateString() : null;
        $today = now()->toDateString();

        Log::info("LOGIQUE DE DÉCISION", [
            'lastTapDate' => $lastTapDate,
            'today' => $today,
            'tapped_out_at' => $user->tapped_out_at,
            'condition_nouveau_jour' => ($lastTapDate && $lastTapDate != $today),
            'condition_deblocage_admin' => ($user->tapped_out_at && $user->tapped_out_at != $today && $lastTapDate == $today),
        ]);

        // Si c'est un nouveau jour, on reset tout SEULEMENT dans ce cas
        if ($lastTapDate && $lastTapDate != $today) {
            Log::info("🔄 Nouveau jour détecté pour l'utilisateur {$user->id} - Reset complet");

            // Réinitialiser tout
            $user->tapped_out_at = null;
            // $user->shown_at_30 = false; // Plus nécessaire - captcha désactivé
            // $user->shown_at_60 = false; // Plus nécessaire - captcha désactivé
            // $user->shown_at_90 = false; // Plus nécessaire - captcha désactivé
            $user->taps = 0;
            $user->save();
            $user = $user->fresh();

            Cache::forget("tap_days_count_{$user->id}");
            $currentTaps = 0;
            $needsReset = true;
        }
        // Si c'est le même jour et que l'utilisateur a été débloqué (tapped_out_at n'est plus aujourd'hui)
        elseif ($user->tapped_out_at && $user->tapped_out_at != $today && $lastTapDate == $today) {
            Log::info("🔓 Déblocage limite journalière détecté pour l'utilisateur {$user->id} - Conservation des taps");

            // Conserver les taps existants, juste nettoyer tapped_out_at
            $user->tapped_out_at = null;
            $user->save();

            $needsReset = false; // Important : ne PAS reset les taps
        }
        // Si aucune condition de reset n'est remplie (CAS NORMAL - déblocage is_blocked inclus)
        else {
            Log::info("✅ Pas de reset nécessaire - Conservation de l'état actuel");
            // NE RIEN FAIRE - conserver tous les taps existants
            $needsReset = false;
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'telegram_id' => $user->telegram_id,
                'balance_ldp' => (float) $user->balance_ldp,
                'balance_usdt' => (float) $user->balance_usdt,
                'is_premium' => (bool) $user->is_premium,
                'is_blocked' => (bool) $user->is_blocked,
                'langue' => $user->langue ?? 'fr',
                'last_tap_at' => $user->last_tap_at,
            ],
            'tap_state' => [
                'current_taps' => $currentTaps,
                'tap_limit' => $tapLimit,
                'multiplier' => $multiplier,
                'is_blocked' => $user->tapped_out_at === now()->toDateString(),
                // 'shown_at_30' => (bool) $user->shown_at_30, // Plus nécessaire - captcha désactivé
                // 'shown_at_60' => (bool) $user->shown_at_60, // Plus nécessaire - captcha désactivé
                // 'shown_at_90' => (bool) $user->shown_at_90, // Plus nécessaire - captcha désactivé
                'tapped_out_at' => $user->tapped_out_at,
                'needs_reset' => $needsReset,
            ],
            'booster' => $userBooster ? [
                'id' => $userBooster->booster->id,
                'name' => $userBooster->booster->name,
                'coefficient' => $userBooster->booster->coefficient,
                'expires_at' => $userBooster->expires_at,
            ] : null,
            'announcement' => now()->lt(\Carbon\Carbon::parse('2026-04-18 00:00:00')) ? [
                'title' => '🚀 Migration NewWorldChat — 17 avril 2026',
                'message_fr' => "Chers utilisateurs,\n\nNewWorldChat évolue vers une toute nouvelle plateforme plus performante, plus sécurisée et avec beaucoup plus de fonctionnalités !\n\n📅 Date de migration : 17 avril 2026\n\n✅ CE QUI NE CHANGE PAS :\n• Votre email et mot de passe restent les mêmes\n• Votre solde LDP sera converti en NWC (le nouveau token officiel)\n• Le minage tap-to-earn continue sur la nouvelle plateforme\n• La messagerie et les appels restent disponibles\n\n🆕 CE QUI CHANGE :\n• Nouvelle adresse : newworld.chat\n• LDP devient NWC (NewWorldChat Token) — bridgeable vers Solana\n• Nouveau système de minage avec des packs d'énergie\n• Ajout du staking (35-60% APY)\n• Ajout de la pool de liquidité (100-120% APY)\n• Shop e-commerce intégré\n• Appels vidéo améliorés avec programmation\n• Statuts et stories comme Instagram\n• Portefeuille multi-crypto (SOL, USDC, BNB)\n\n⚠️ ACTIONS REQUISES :\n• Tous les stakings en cours seront annulés et le capital vous sera rendu\n• Après le 17 avril, connectez-vous sur newworld.chat avec votre email et mot de passe actuels\n• Seuls les comptes avec un solde minimum de 50 LDP seront migrés automatiquement\n• Si votre solde est inférieur à 50 LDP, contactez-nous pour une migration manuelle\n\n📢 L'ancienne plateforme sera désactivée après la migration.\n\nL'équipe NEWWORLD GROUP",
                'message_en' => "Dear users,\n\nNewWorldChat is evolving into a brand new platform — faster, more secure, and packed with new features!\n\n📅 Migration date: April 17, 2026\n\n✅ WHAT STAYS THE SAME:\n• Your email and password remain the same\n• Your LDP balance will be converted to NWC (the new official token)\n• Tap-to-earn mining continues on the new platform\n• Messaging and calls remain available\n\n🆕 WHAT'S NEW:\n• New address: newworld.chat\n• LDP becomes NWC (NewWorldChat Token) — bridgeable to Solana\n• New mining system with energy packs\n• Staking added (35-60% APY)\n• Liquidity pool added (100-120% APY)\n• Integrated e-commerce shop\n• Enhanced video calls with scheduling\n• Instagram-like statuses and stories\n• Multi-crypto wallet (SOL, USDC, BNB)\n\n⚠️ REQUIRED ACTIONS:\n• All active stakings will be cancelled and capital returned\n• After April 17, log in at newworld.chat with your current email and password\n• Only accounts with a minimum balance of 50 LDP will be migrated automatically\n• If your balance is below 50 LDP, contact us for manual migration\n\n📢 The old platform will be deactivated after migration.\n\nThe NEWWORLD GROUP Team",
            ] : null,
        ]);
    }
}
