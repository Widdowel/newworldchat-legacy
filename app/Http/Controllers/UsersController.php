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
                'title' => 'Migration NewWorldChat - 17 avril 2026',
                'message_fr' => "Chers utilisateurs,\n\nNewWorldChat evolue vers une nouvelle plateforme plus performante et plus complete.\n\nDate de migration : 17 avril 2026\n\n--- Ce qui reste inchange ---\n- Votre mot de passe reste identique\n- Votre solde LDP sera integralement converti en NWC, le nouveau token officiel\n- Le minage tap-to-earn reste disponible\n- La messagerie et les appels sont maintenus\n\n--- Les nouveautes ---\n- Nouvelle adresse : newworld.chat\n- Le token LDP devient NWC, bridgeable vers la blockchain Solana\n- Nouveau systeme de minage avec packs d'energie\n- Staking : 35 a 60% APY selon le montant\n- Pool de liquidite : 100 a 120% APY\n- Shop e-commerce integre\n- Appels video ameliores avec programmation et rappels\n- Statuts et stories\n- Portefeuille multi-crypto : SOL, USDC, BNB\n\n--- Boosters et Stakings en cours ---\n- Tous les stakings actifs seront annules. Votre capital vous sera integralement restitue avant la migration.\n- Si vous disposez d'un booster actif, un pack d'energie equivalent vous sera attribue gratuitement sur la nouvelle plateforme.\n\n--- Actions requises ---\n- Si vous utilisez un numero de telephone pour vous connecter : vous devez imperativement ajouter une adresse email a votre compte avant le 17 avril. La nouvelle plateforme fonctionne exclusivement avec une adresse email.\n- A partir du 17 avril, connectez-vous sur newworld.chat avec votre adresse email et votre mot de passe actuel.\n- Si vous avez oublie votre mot de passe, utilisez la fonction \"Mot de passe oublie\" sur newworld.chat pour en creer un nouveau.\n- Lors de votre premiere connexion, vous devrez accepter les nouvelles conditions d'utilisation.\n- Les comptes migres qui ne se connectent pas dans les 2 mois suivant la migration seront supprimes.\n\nL'ancienne plateforme sera desactivee apres la migration.\n\nPour toute question, contactez-nous sur la plateforme.\nNEWWORLD GROUP FZE LLC - Dubai, UAE",
                'message_en' => "Dear users,\n\nNewWorldChat is evolving into a new, more powerful and complete platform.\n\nMigration date: April 17, 2026\n\n--- What stays the same ---\n- Your password remains the same\n- Your LDP balance will be fully converted to NWC, the new official token\n- Tap-to-earn mining remains available\n- Messaging and calls are maintained\n\n--- What is new ---\n- New address: newworld.chat\n- LDP token becomes NWC, bridgeable to Solana blockchain\n- New mining system with energy packs\n- Staking: 35 to 60% APY based on amount\n- Liquidity pool: 100 to 120% APY\n- Integrated e-commerce shop\n- Enhanced video calls with scheduling and reminders\n- Statuses and stories\n- Multi-crypto wallet: SOL, USDC, BNB\n\n--- Active boosters and stakings ---\n- All active stakings will be cancelled. Your capital will be fully returned before migration.\n- If you have an active booster, an equivalent energy pack will be granted to you for free on the new platform.\n\n--- Required actions ---\n- If you use a phone number to log in: you must add an email address to your account before April 17. The new platform works exclusively with email addresses.\n- Starting April 17, log in at newworld.chat with your email address and your current password.\n- If you have forgotten your password, use the \"Forgot password\" feature on newworld.chat to create a new one.\n- On your first login, you will need to accept the new terms of use.\n- Migrated accounts that do not log in within 2 months after migration will be deleted.\n\nThe old platform will be deactivated after migration.\n\nFor any questions, contact us on the platform.\nNEWWORLD GROUP FZE LLC - Dubai, UAE",
            ] : null,
        ]);
    }
}
