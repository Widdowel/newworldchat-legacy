<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Notifications\AdminNewDepositNotification;
use App\Notifications\AdminNewWithdrawalNotification;
use App\Notifications\NewWithdrawalRequest;
use App\Notifications\UserDepositConfirmationNotification;
use App\Notifications\UserWithdrawalConfirmationNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class TransactionController extends Controller
{
    public function getNetworks()
    {
        $networks_usdt = Setting::where('tag', 'LIKE', 'ADRESSE_DEPOT_USDT_%')
            ->whereNotIn('tag', ['ADRESSE_DEPOT_USDT_ERC20'])
            ->get()
            ->mapWithKeys(function ($item) {
                $network = str_replace('ADRESSE_DEPOT_USDT_', '', $item->tag);
                return [$network => $item->value];
            })
            ->toArray();

        $networks_ldp = Setting::where('tag', 'LIKE', 'ADRESSE_DEPOT_LDP_%')
            ->get()
            ->mapWithKeys(function ($item) {
                $network = str_replace('ADRESSE_DEPOT_LDP_', '', $item->tag);
                return [$network => $item->value];
            })
            ->toArray();

        return response()->json([
            'success' => true,
            'networks' => [
                'usdt' => $networks_usdt,
                'ldp' => $networks_ldp,
            ]
        ]);
    }

    /**
     * Dépôt USDT
     */
    public function depositUsdt(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.0001',
            'network' => 'required|string',
            'address' => 'required|string',
            'trx_number' => 'required|string',
            'email' => 'nullable|email',
        ]);

        $user = User::where('id', Auth::user()->id)->first();

        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $request->amount,
            'currency' => 'USDT',
            'status' => 'pending',
            'reference' => $request->trx_number,
            'source' => 'mobile_app',
            'method' => $request->address . ' (' . $request->network . ')',
            'description' => 'Dépôt de USDT',
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'metadata' => json_encode([
                'network' => $request->network,
                'address' => $request->address,
                'trx_hash' => $request->trx_number,
            ]),
            'status_detail' => $request->email ?? $user->email,
        ]);


        // Notifier les admins
        try {
            $admins = User::where("is_admin", true)->get();
            foreach ($admins as $admin) {
                if ($admin->email) {
                    Notification::route('mail', $admin->email)
                        ->notifyNow(new AdminNewDepositNotification($user, $request->amount, 'USDT', $request->trx_number));
                }
            }
        } catch (\Exception $e) {
            Log::warning("Erreur notification admin dépôt: " . $e->getMessage());
        }

        try {
            if ($user->email || $request->email) {
                $user->notifyNow(new UserDepositConfirmationNotification($request->amount, $request->type, 'USDT'));
            }
        } catch (\Exception $e) {
            Log::warning("Erreur notification user dépôt: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande de dépôt USDT envoyée avec succès',
            'transaction' => [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'status' => $transaction->status,
            ]
        ]);
    }

    /**
     * Dépôt LDP
     */
    public function depositLdp(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.0001',
            'network' => 'required|string',
            'address' => 'required|string',
            'trx_number' => 'required|string',
            'email' => 'nullable|email',
        ]);

        $user = User::where('id', Auth::user()->id)->first();


        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $request->amount,
            'currency' => 'LDP',
            'status' => 'pending',
            'reference' => $request->trx_number,
            'source' => 'mobile_app',
            'method' => $request->address . ' (' . $request->network . ')',
            'description' => 'Dépôt de LDP',
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'metadata' => json_encode([
                'network' => $request->network,
                'address' => $request->address,
                'trx_hash' => $request->trx_number,
            ]),
            'status_detail' => $request->email ?? $user->email,
        ]);


        // Notifier les admins
        try {
            $admins = User::where("is_admin", true)->get();
            foreach ($admins as $admin) {
                if ($admin->email) {
                    Notification::route('mail', $admin->email)
                        ->notifyNow(new AdminNewDepositNotification($user, $request->amount, 'LDP', $request->trx_number));
                }
            }
        } catch (\Exception $e) {
            Log::warning("Erreur notification admin dépôt: " . $e->getMessage());
        }

        // Notifier l'utilisateur
        try {
            if ($user->email || $request->email) {
                $user->notifyNow(new UserDepositConfirmationNotification($request->amount, $request->type, 'LDP'));
            }
        } catch (\Exception $e) {
            Log::warning("Erreur notification user dépôt: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande de dépôt LDP envoyée avec succès',
            'transaction' => [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'status' => $transaction->status,
            ]
        ]);
    }

    /**
     * Récupérer les taux de conversion
     */
    public function getConversionRates()
    {
        $conversion_ldp_usdt = Setting::where("tag", "VALEUR_CONVERSION_LDP_VERS_USDT")->value('value') ?? 2;
        $conversion_usdt_ldp = Setting::where("tag", "VALEUR_CONVERSION_USDT_VERS_LDP")->value('value') ?? 1;

        return response()->json([
            'success' => true,
            'rates' => [
                'ldp_to_usdt' => (float) $conversion_ldp_usdt,
                'usdt_to_ldp' => (float) $conversion_usdt_ldp,
            ],
            'penalty' => [
                'active' => now()->lt(Carbon::create(2026, 9, 27)),
                'rate' => 0.95,
                'deadline' => '2026-09-27',
            ]
        ]);
    }

    /**
     * Traiter une conversion
     */
    public function processConversion(Request $request)
    {
        $request->validate([
            'direction' => 'required|in:usdt_to_ldp,ldp_to_usdt',
            'amount' => 'required|numeric|min:0.0001',
        ]);

        $user = User::where('id', Auth::user()->id)->first();


        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $amount = $request->amount;
        $direction = $request->direction;

        $conversion_ldp_usdt = Setting::where("tag", "VALEUR_CONVERSION_LDP_VERS_USDT")->value('value') ?? 2;
        $conversion_usdt_ldp = Setting::where("tag", "VALEUR_CONVERSION_USDT_VERS_LDP")->value('value') ?? 1;

        $rate = [
            'usdt_to_ldp' => (float) $conversion_usdt_ldp,
            'ldp_to_usdt' => (float) $conversion_ldp_usdt,
        ];

        $usdt = $user->balance_usdt;
        $ldp = $user->balance_ldp;
        $convertedAmount = 0;
        $penaltyApplied = false;

        if ($direction === 'usdt_to_ldp') {
            if ($usdt < $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solde USDT insuffisant',
                ], 400);
            }

            $convertedAmount = $amount * $rate['usdt_to_ldp'];
            $usdt -= $amount;
            $ldp += $convertedAmount;
        } elseif ($direction === 'ldp_to_usdt') {
            if ($ldp < $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solde LDP insuffisant',
                ], 400);
            }

            $amountToConvert = $amount;
            $penaltyDate = Carbon::create(2026, 9, 27);

            if (now()->lt($penaltyDate)) {
                $amountToConvert = $amount * 0.05; // 95% brûlé
                $penaltyApplied = true;
            }

            $convertedAmount = $amountToConvert * $rate['ldp_to_usdt'];

            $ldp -= $amount;
            $usdt += $convertedAmount;
        }

        $user->balance_usdt = $usdt;
        $user->balance_ldp = $ldp;
        $user->save();
        $user = $user->fresh();

        Transaction::create([
            'user_id' => $user->id,
            'type' => 'conversion',
            'amount' => $amount,
            'currency' => $direction === 'usdt_to_ldp' ? 'USDT' : 'LDP',
            'status' => 'completed',
            'reference' => strtoupper(Str::random(12)),
            'source' => $direction,
            'method' => 'internal',
            'description' => 'Conversion de tokens',
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'processed_at' => now(),
            'metadata' => json_encode([
                'converted_amount' => $convertedAmount,
                'target_currency' => $direction === 'usdt_to_ldp' ? 'LDP' : 'USDT',
                'rate_used' => $rate[$direction],
                'penalty_applied' => $penaltyApplied,
                'penalty_percent' => $penaltyApplied ? 95 : 0,
            ])
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conversion effectuée avec succès',
            'result' => [
                'amount_converted' => $convertedAmount,
                'new_balance_usdt' => $user->balance_usdt,
                'new_balance_ldp' => $user->balance_ldp,
                'penalty_applied' => $penaltyApplied,
            ]
        ]);
    }

    /**
     * Paramètres de retrait
     */
    public function getWithdrawalSettings()
    {
        $settings = [
            'ldp' => [
                'enabled' => Setting::where('tag', 'ACTIVER_FRAIS_RETRAIT_LDP')->value('value') === 'OUI',
                'fee' => (float) Setting::where('tag', 'FRAIS_RETRAIT_LDP')->value('value') ?? 0,
                'min_amount' => (float) Setting::where('tag', 'MIN_RETRAIT_LDP')->value('value') ?? 1,
            ],
            'usdt' => [
                'enabled' => Setting::where('tag', 'ACTIVER_FRAIS_RETRAIT_USDT')->value('value') === 'OUI',
                'fee' => (float) Setting::where('tag', 'FRAIS_RETRAIT_USDT')->value('value') ?? 0,
                'min_amount' => (float) Setting::where('tag', 'MIN_RETRAIT_USDT')->value('value') ?? 1,
            ],
        ];

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    /**
     * Traiter un retrait
     */
    public function withdrawTokens(Request $request)
    {
        $request->validate([
            'token_type' => 'required|in:LDP,USDT',
            'network' => 'required|in:solana,bep20',
            'amount' => 'required|numeric|min:0.0001',
            'receiver_address' => 'required|string',
            'notification_email' => 'nullable|email',
        ]);

        $user = User::where('id', Auth::user()->id)->first();

        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $ACTIVER_FRAIS_RETRAIT_LDP = Setting::where('tag', 'ACTIVER_FRAIS_RETRAIT_LDP')->value('value') === 'OUI';
        $ACTIVER_FRAIS_RETRAIT_USDT = Setting::where('tag', 'ACTIVER_FRAIS_RETRAIT_USDT')->value('value') === 'OUI';
        $FRAIS_RETRAIT_LDP = (float) Setting::where('tag', 'FRAIS_RETRAIT_LDP')->value('value') ?? 0;
        $FRAIS_RETRAIT_USDT = (float) Setting::where('tag', 'FRAIS_RETRAIT_USDT')->value('value') ?? 0;

        $wallet = $request->token_type === 'LDP' ? $user->balance_ldp : $user->balance_usdt;

        if ($wallet < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Solde insuffisant',
            ], 400);
        }

        $prix_final = $request->amount;

        if ($request->token_type === 'LDP') {
            $user->balance_ldp -= $request->amount;
            if ($ACTIVER_FRAIS_RETRAIT_LDP) {
                $prix_final = $request->amount - $FRAIS_RETRAIT_LDP;
            }
        } else {
            $user->balance_usdt -= $request->amount;
            if ($ACTIVER_FRAIS_RETRAIT_USDT) {
                $prix_final = $request->amount - $FRAIS_RETRAIT_USDT;
            }
        }

        $user->save();
        $user = $user->fresh();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => 'withdrawal',
            'amount' => $prix_final,
            'currency' => $request->token_type,
            'status' => 'pending',
            'reference' => strtoupper(Str::random(12)),
            'source' => 'mobile_app',
            'method' => $request->receiver_address . ' (' . $request->network . ')',
            'description' => "Retrait de {$prix_final} {$request->token_type} sur réseau {$request->network}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'metadata' => json_encode([
                'receiver_address' => $request->receiver_address,
                'notification_email' => $request->notification_email,
                'network' => $request->network,
            ]),
            'status_detail' => $request->notification_email ?? $user->email,
        ]);

        try {
            $admins = User::where("is_admin", true)->get();
            foreach ($admins as $admin) {
                if ($admin->email) {
                    Notification::route('mail', $admin->email)
                        ->notifyNow(new AdminNewWithdrawalNotification($user, $prix_final, $request->token_type, $request->network, $request->receiver_address));
                }
            }
        } catch (\Exception $e) {
            Log::warning("Erreur notification admin retrait: " . $e->getMessage());
        }

        try {
            if ($user->email || $request->notification_email) {
                $user->notifyNow(new UserWithdrawalConfirmationNotification($prix_final, $request->token_type, $request->network, $request->receiver_address));
            }
        } catch (\Exception $e) {
            Log::warning("Erreur notification user retrait: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande de retrait enregistrée avec succès',
            'transaction' => [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'amount' => $prix_final,
                'status' => $transaction->status,
            ],
            'new_balance_ldp' => $user->balance_ldp,
            'new_balance_usdt' => $user->balance_usdt,
        ]);
    }
}
