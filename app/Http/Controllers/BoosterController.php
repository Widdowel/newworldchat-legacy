<?php

namespace App\Http\Controllers;

use App\Models\Ambassador;
use App\Models\Booster;
use App\Models\Setting;
use App\Models\TapMultiplier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserBooster;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class BoosterController extends Controller
{
    public function index()
    {
        $boosters = Booster::latest()->paginate(10);
        // $multipliers = TapMultiplier::get();
        return response()->json($boosters);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_months' => 'required|integer|min:1',
            'coefficient' => 'required|numeric|min:1',
        ]);

        Booster::create($request->all());

        return redirect()->route('admin.boosters.index')->with('success', 'Booster ajouté avec succès.');
    }

    public function update(Request $request, Booster $booster)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_months' => 'required|integer|min:1',
            'coefficient' => 'required|numeric|min:1',
        ]);

        $booster->update($request->all());


        $activeBoosters = UserBooster::where('booster_id', $booster->id)
            ->where('expires_at', '>', now())
            ->get();

        foreach ($activeBoosters as $userBooster) {
            $newExpiresAt = Carbon::parse($userBooster->activated_at)->addMonths($request->duration_months);

            $userBooster->expires_at = $newExpiresAt;
            $userBooster->save();
        }


        return redirect()->route('admin.boosters.index')->with('success', 'Booster mis à jour avec succès.');
    }

    public function destroy(Booster $booster)
    {
        $booster->delete();

        return redirect()->route('admin.boosters.index')->with('success', 'Booster supprimé.');
    }


    public function activateBooster(Request $request)
    {
        $request->validate([
            'booster_id' => 'required|integer|exists:boosters,id'
        ]);

        $user = User::find(Auth::id());
        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $booster = Booster::find($request->booster_id);
        if (!$booster) {
            return response()->json([
                'success' => false,
                'message' => 'Booster introuvable'
            ], 404);
        }


        $valeurConversionUsdtVersLdp = Setting::where('tag', 'VALEUR_CONVERSION_USDT_VERS_LDP')->first();
        $valeurConversionUsdtVersLdp = $valeurConversionUsdtVersLdp->value ?? '44.822949350067';
        $boosterPriceInLdp = $booster->price * $valeurConversionUsdtVersLdp;


        // Vérifier si l'utilisateur a assez de fonds
        if ($user->balance_ldp < $boosterPriceInLdp) {
            return response()->json([
                'success' => false,
                'message' => 'Solde LDP insuffisant',
                'required' => $booster->price,
                'current_balance' => $user->balance_usdt
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Désactiver les boosters actifs précédents (optionnel)
            UserBooster::where('user_id', $user->id)
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            // Calculer la date d'expiration
            $expiresAt = now()->addMonths($booster->duration_months);

            // Créer le nouveau booster utilisateur
            $userBooster = UserBooster::create([
                'user_id' => $user->id,
                'booster_id' => $booster->id,
                'activated_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            $this->rewardReferrer($user->id, $boosterPriceInLdp, $booster->price);


            // Débiter l'utilisateur
            $user->balance_ldp -= $boosterPriceInLdp;
            $user->save();

            // Réinitialiser le cache des taps
            Cache::forget("tap_days_count_{$user->id}");

            // Réinitialiser les taps du jour
            $user->taps = 0;
            $user->tapped_out_at = null;
            // $user->shown_at_30 = false; // Plus nécessaire - captcha désactivé
            // $user->shown_at_60 = false; // Plus nécessaire - captcha désactivé
            // $user->shown_at_90 = false; // Plus nécessaire - captcha désactivé
            $user->save();

            // Créer une transaction
            Transaction::create([
                'user_id' => $user->id,
                'type' => 'purchase',
                'amount' => $boosterPriceInLdp,
                'currency' => 'LDP',
                'status' => 'completed',
                'reference' => 'BOOST-' . strtoupper(Str::random(10)),
                'source' => 'internal',
                'method' => 'balance',
                'description' => "Achat du booster {$booster->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'metadata' => json_encode([
                    'booster_id' => $booster->id,
                    'booster_name' => $booster->name,
                    'coefficient' => $booster->coefficient,
                    'duration_months' => $booster->duration_months,
                    'expires_at' => $expiresAt->toDateTimeString(),
                ]),
            ]);

            DB::commit();

            $tapLimit = TapMultiplier::where('coefficient', $booster->coefficient)
                ->value('required_taps') ?? 500;

            return response()->json([
                'success' => true,
                'message' => 'Booster activé avec succès',
                'booster' => [
                    'id' => $booster->id,
                    'name' => $booster->name,
                    'coefficient' => $booster->coefficient,
                    'expires_at' => $expiresAt,
                ],
                'new_balance_ldp' => $user->balance_ldp,
                'tap_limit' => $tapLimit,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'activation du booster',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getUserBoosters(Request $request)
    {
        $user = User::find(Auth::id());
        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $boosters = UserBooster::with('booster')
            ->where('user_id', $user->id)
            ->orderBy('activated_at', 'desc')
            ->get()
            ->map(function ($userBooster) {
                return [
                    'id' => $userBooster->id,
                    'booster' => [
                        'id' => $userBooster->booster->id,
                        'name' => $userBooster->booster->name,
                        'coefficient' => $userBooster->booster->coefficient,
                    ],
                    'activated_at' => $userBooster->activated_at,
                    'expires_at' => $userBooster->expires_at,
                    'is_active' => $userBooster->expires_at > now(),
                    'is_expired' => $userBooster->expires_at <= now(),
                ];
            });

        return response()->json([
            'success' => true,
            'boosters' => $boosters
        ]);
    }

    public function rewardReferrer($userId, $packPrice, $packPrice_usdt)
    {
        return DB::transaction(function () use ($userId, $packPrice, $packPrice_usdt) {
            $user = User::findOrFail($userId);

            if (!$user->referrer_id) {
                return false;
            }

            $referrer = User::find($user->referrer_id);
            if (!$referrer) {
                return false;
            }

            $ambassador = Ambassador::where('user_id', $referrer->id)->first();
            if (!$ambassador) {
                return false;
            }

            $percentage = $ambassador->percentage ?? 0;
            $percentage_usdt = $ambassador->percentage_usdt ?? 0;
            if ($percentage < 0) {
                return false;
            }

            if ($percentage_usdt < 0) {
                return false;
            }

            $bonus = ($packPrice * $percentage) / 100;
            $bonus_usdt = ($packPrice_usdt * $percentage_usdt) / 100;

            $referrer->balance_ldp += $bonus;
            $referrer->balance_usdt += $bonus_usdt;
            $referrer->save();

            Transaction::create([
                'user_id' => $referrer->id,
                'type' => 'reward',
                'amount' => $bonus,
                'currency' => 'LDP',
                'status' => 'completed',
                'reference' => strtoupper(Str::random(12)),
                'source' => 'ambassador_bonus',
                'method' => 'internal',
                'description' => 'Gain de parrainage ambassador en LDP',
                'ip_address' => "intern",
                'user_agent' => "intern",
                'processed_at' => now(),
                'metadata' => json_encode([
                    'original_pack_price' => $packPrice,
                    'percentage' => $percentage,
                    'user_id' => $user->id,
                ])
            ]);

            Transaction::create([
                'user_id' => $referrer->id,
                'type' => 'reward',
                'amount' => $bonus_usdt,
                'currency' => 'USDT',
                'status' => 'completed',
                'reference' => strtoupper(Str::random(12)),
                'source' => 'ambassador_bonus',
                'method' => 'internal',
                'description' => 'Gain de parrainage ambassador en USDT',
                'ip_address' => "intern",
                'user_agent' => "intern",
                'processed_at' => now(),
                'metadata' => json_encode([
                    'original_pack_price' => $packPrice,
                    'percentage' => $percentage,
                    'user_id' => $user->id,
                ])
            ]);

            return true;
        });
    }
}
