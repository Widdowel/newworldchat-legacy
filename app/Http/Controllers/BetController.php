<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\User;
use App\Models\UserBet;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BetController extends Controller
{
    /**
     * Liste de toutes les paires de paris disponibles
     * GET /api/bets
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $now = Carbon::now('GMT');

            $bets = Bet::all()->map(function ($bet) use ($now) {
                $start = Carbon::parse($bet->start_hour, 'GMT');
                $end = Carbon::parse($bet->end_hour, 'GMT');
                
                $isOpen = $now->gte($start) && $now->lt($end);
                
                return [
                    'id' => $bet->id,
                    'pair' => $bet->pair,
                    'max_gap' => (float) $bet->max_gap,
                    'start_hour' => $bet->start_hour,
                    'end_hour' => $bet->end_hour,
                    'win_percent' => (float) $bet->win_percent,
                    'loss_percent' => (float) $bet->loss_percent,
                    'min_price' => (float) $bet->min_price,
                    'hour_result' => $bet->hour_result,
                    'is_open' => $isOpen,
                    'status' => $isOpen ? 'open' : 'closed',
                ];
            });

            return response()->json([
                'success' => true,
                'bets' => $bets,
                'current_time_gmt' => $now->format('H:i:s'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des paris',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Détails d'une paire de pari spécifique
     * GET /api/bets/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $bet = Bet::findOrFail($id);
            $now = Carbon::now('GMT');
            
            $start = Carbon::parse($bet->start_hour, 'GMT');
            $end = Carbon::parse($bet->end_hour, 'GMT');
            
            $isOpen = $now->gte($start) && $now->lt($end);

            return response()->json([
                'success' => true,
                'bet' => [
                    'id' => $bet->id,
                    'pair' => $bet->pair,
                    'max_gap' => (float) $bet->max_gap,
                    'start_hour' => $bet->start_hour,
                    'end_hour' => $bet->end_hour,
                    'win_percent' => (float) $bet->win_percent,
                    'loss_percent' => (float) $bet->loss_percent,
                    'min_price' => (float) $bet->min_price,
                    'hour_result' => $bet->hour_result,
                    'is_open' => $isOpen,
                    'status' => $isOpen ? 'open' : 'closed',
                ],
                'current_time_gmt' => $now->format('H:i:s'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pari non trouvé',
            ], 404);
        }
    }

    /**
     * Mes paris (avec filtres optionnels)
     * GET /api/bets/my-bets
     */
    public function myBets(Request $request)
    {
        try {
            $user = $request->user();
            
            $query = UserBet::where('user_id', $user->id)
                ->with('bet');

            // Filtres optionnels
            if ($request->has('result') && $request->result !== 'all') {
                $query->where('result', $request->result);
            }

            if ($request->has('bet_id')) {
                $query->where('bet_id', $request->bet_id);
            }

            if ($request->has('start_date')) {
                $query->whereDate('placed_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('placed_at', '<=', $request->end_date);
            }

            $perPage = $request->input('per_page', 20);
            $myBets = $query->orderBy('placed_at', 'desc')->paginate($perPage);

            $formattedBets = $myBets->map(function ($userBet) {
                return [
                    'id' => $userBet->id,
                    'bet_id' => $userBet->bet_id,
                    'pair' => $userBet->bet->pair,
                    'amount' => (float) $userBet->amount,
                    'min_price' => (float) $userBet->min_price,
                    'max_price' => (float) $userBet->max_price,
                    'result' => $userBet->result,
                    'value' => (float) ($userBet->value ?? 0),
                    'real_price' => $userBet->real_price ? (float) $userBet->real_price : null,
                    'placed_at' => $userBet->placed_at,
                    'evaluated_at' => $userBet->evaluated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'bets' => $formattedBets,
                'pagination' => [
                    'current_page' => $myBets->currentPage(),
                    'last_page' => $myBets->lastPage(),
                    'per_page' => $myBets->perPage(),
                    'total' => $myBets->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de vos paris',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Top 5 des meilleurs gains
     * GET /api/bets/leaderboard
     */
    public function leaderboard(Request $request)
    {
        try {
            $topBets = UserBet::where('result', 'win')
                ->orderByDesc('value')
                ->with(['user', 'bet'])
                ->limit(10)
                ->get()
                ->map(function ($userBet) {
                    return [
                        'id' => $userBet->id,
                        'user_name' => $userBet->user->name,
                        'pair' => $userBet->bet->pair,
                        'amount' => (float) $userBet->amount,
                        'value' => (float) $userBet->value,
                        'win_rate' => round(($userBet->value / $userBet->amount) * 100, 2),
                        'placed_at' => $userBet->placed_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'leaderboard' => $topBets,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du classement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques de mes paris
     * GET /api/bets/my-statistics
     */
    public function myStatistics(Request $request)
    {
        try {
            $user = $request->user();

            $totalBets = UserBet::where('user_id', $user->id)->count();
            $totalAmount = UserBet::where('user_id', $user->id)->sum('amount');
            
            $pendingBets = UserBet::where('user_id', $user->id)
                ->where('result', 'pending')
                ->count();
            
            $winBets = UserBet::where('user_id', $user->id)
                ->where('result', 'win')
                ->count();
            
            $loseBets = UserBet::where('user_id', $user->id)
                ->where('result', 'lose')
                ->count();
            
            $totalValue = UserBet::where('user_id', $user->id)
                ->whereIn('result', ['win', 'lose'])
                ->sum('value');

            $winRate = $totalBets > 0 ? round(($winBets / $totalBets) * 100, 2) : 0;
            $netProfit = $totalValue;

            return response()->json([
                'success' => true,
                'statistics' => [
                    'total_bets' => $totalBets,
                    'total_amount' => (float) $totalAmount,
                    'pending_bets' => $pendingBets,
                    'win_bets' => $winBets,
                    'lose_bets' => $loseBets,
                    'net_profit' => (float) $netProfit,
                    'win_rate' => $winRate,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Placer un pari
     * POST /api/bets/place
     */
    public function place(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bet_id' => 'required|exists:bets,id',
            'amount' => 'required|numeric|min:1',
            'min_price' => 'required|numeric',
            'max_price' => 'required|numeric|gt:min_price',
        ], [
            'bet_id.required' => 'La paire de pari est requise',
            'bet_id.exists' => 'Cette paire de pari n\'existe pas',
            'amount.required' => 'Le montant est requis',
            'amount.numeric' => 'Le montant doit être un nombre',
            'amount.min' => 'Le montant minimum est 1 LDP',
            'min_price.required' => 'Le prix minimum est requis',
            'max_price.required' => 'Le prix maximum est requis',
            'max_price.gt' => 'Le prix maximum doit être supérieur au prix minimum',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $bet = Bet::findOrFail($request->bet_id);
            $now = Carbon::now('GMT');

            // Vérifier les horaires
            $start = Carbon::parse($bet->start_hour, 'GMT');
            $end = Carbon::parse($bet->end_hour, 'GMT');

            if ($now->lt($start) || $now->gte($end)) {
                return response()->json([
                    'success' => false,
                    'message' => "Les paris pour {$bet->pair} sont ouverts entre {$start->format('H:i')} et {$end->format('H:i')} GMT.",
                ], 400);
            }

            // Vérifier le montant minimum
            if ($request->amount < $bet->min_price) {
                return response()->json([
                    'success' => false,
                    'message' => "Le montant minimum pour ce pari est de {$bet->min_price} LDP",
                ], 400);
            }

            // Vérifier le solde
            if ($user->balance_ldp < $request->amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solde insuffisant',
                ], 400);
            }

            // Vérifier l'écart de prix
            $gap = $request->max_price - $request->min_price;
            if ($gap > $bet->max_gap) {
                return response()->json([
                    'success' => false,
                    'message' => "L'écart maximum autorisé est de {$bet->max_gap} $",
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Créer le pari
                $userBet = UserBet::create([
                    'user_id' => $user->id,
                    'bet_id' => $bet->id,
                    'amount' => $request->amount,
                    'min_price' => $request->min_price,
                    'max_price' => $request->max_price,
                    'result' => 'pending',
                    'placed_at' => now(),
                ]);

                // Débiter le solde
                $user->balance_ldp -= $request->amount;
                $user->save();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Pari enregistré avec succès',
                    'bet' => [
                        'id' => $userBet->id,
                        'pair' => $bet->pair,
                        'amount' => (float) $userBet->amount,
                        'min_price' => (float) $userBet->min_price,
                        'max_price' => (float) $userBet->max_price,
                        'result' => $userBet->result,
                        'placed_at' => $userBet->placed_at,
                    ],
                    'new_balance' => (float) $user->balance_ldp,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du placement du pari',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Annuler un pari (seulement si pending et dans les 5 min)
     * POST /api/bets/{id}/cancel
     */
    public function cancel(Request $request, $id)
    {
        try {
            $user = $request->user();
            $userBet = UserBet::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            if ($userBet->result !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce pari ne peut plus être annulé',
                ], 400);
            }

            // Vérifier si le pari a été placé il y a moins de 5 minutes
            $placedAt = Carbon::parse($userBet->placed_at);
            if ($placedAt->diffInMinutes(now()) > 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le délai d\'annulation (5 minutes) est dépassé',
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Rembourser le montant
                $user->balance_ldp += $userBet->amount;
                $user->save();

                // Supprimer le pari
                $userBet->delete();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Pari annulé et montant remboursé',
                    'new_balance' => (float) $user->balance_ldp,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation du pari',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}