<?php

namespace App\Http\Controllers;

use App\Models\Staking;
use App\Models\StakingPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StakingController extends Controller
{
    /**
     * Récupérer tous les plans de staking disponibles et les stakings de l'utilisateur
     */
    public function index(Request $request)
    {
        $user = User::find(Auth::id());
        
        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Récupérer tous les plans disponibles
        $plans = StakingPlan::latest()->get()->map(function ($plan) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description ?? '',
                'min_amount' => (float) $plan->min_amount,
                'apy' => (float) $plan->apy,
                'duration' => (int) $plan->duration,
                'created_at' => $plan->created_at,
            ];
        });

        // Récupérer les stakings de l'utilisateur
        $stakes = $user->staking()->with('plan')->latest()->get()->map(function ($staking) {
            $isMature = $staking->isMature();
            $nbDays = $staking->nbDays();
            $gainParJour = $staking->amountGainParJour();
            $gainTotal = $isMature ? $this->calculateReward($staking) : ($gainParJour * $nbDays);

            return [
                'id' => $staking->id,
                'plan' => [
                    'id' => $staking->plan->id,
                    'name' => $staking->plan->name,
                    'apy' => (float) $staking->plan->apy,
                    'duration' => (int) $staking->plan->duration,
                ],
                'amount' => (float) $staking->amount,
                'staked_at' => $staking->staked_at,
                'unstaked_at' => $staking->unstaked_at,
                'reward_earned' => (float) ($staking->reward_earned ?? 0),
                'cancelled' => (bool) $staking->cancelled,
                'is_mature' => $isMature,
                'days_elapsed' => $nbDays,
                'gain_par_jour' => (float) $gainParJour,
                'gain_total_actuel' => (float) $gainTotal,
                'is_active' => $staking->unstaked_at === null,
            ];
        });

        return response()->json([
            'success' => true,
            'plans' => $plans,
            'stakes' => $stakes,
            'user_balance_ldp' => (float) $user->balance_ldp,
        ]);
    }

    /**
     * Souscrire à un plan de staking
     */
    public function store(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:staking_plans,id',
            'amount' => 'required|numeric|min:0.0001',
        ]);

        $user = User::find(Auth::id());
        
        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $plan = StakingPlan::findOrFail($request->plan_id);

        // Vérifier le montant minimum
        if ($request->amount < $plan->min_amount) {
            return response()->json([
                'success' => false,
                'message' => "Montant inférieur au minimum requis ({$plan->min_amount} LDP)."
            ], 400);
        }

        // Vérifier le solde
        if ($user->balance_ldp < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Solde insuffisant.'
            ], 400);
        }

        // Débiter le compte
        $user->balance_ldp -= $request->amount;
        $user->save();
        $user = $user->fresh();

        // Créer le staking
        $staking = $user->staking()->create([
            'staking_plan_id' => $plan->id,
            'amount' => $request->amount,
            'staked_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staking lancé avec succès !',
            'staking' => [
                'id' => $staking->id,
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'apy' => (float) $plan->apy,
                    'duration' => (int) $plan->duration,
                ],
                'amount' => (float) $staking->amount,
                'staked_at' => $staking->staked_at,
                'is_mature' => false,
                'days_elapsed' => 0,
            ],
            'new_balance' => (float) $user->balance_ldp,
        ]);
    }

    /**
     * Débloquer un staking
     */
    public function unstake(Request $request, $id)
    {
        $user = User::find(Auth::id());
        
        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $staking = Staking::find($id);

        if (!$staking) {
            return response()->json([
                'success' => false,
                'message' => 'Staking non trouvé.'
            ], 404);
        }

        // Vérifier que le staking appartient à l'utilisateur
        if ($staking->user_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas le droit d\'effectuer cette action.'
            ], 403);
        }

        // Vérifier que le staking n'est pas déjà débloqué
        if ($staking->unstaked_at) {
            return response()->json([
                'success' => false,
                'message' => 'Ce staking est déjà terminé.'
            ], 400);
        }

        $amount = $staking->amount;
        $staking->unstaked_at = now();
        $reward = 0;
        $message = '';

        if ($staking->isMature()) {
            // Staking mature : récupérer capital + gains
            $reward = $this->calculateReward($staking);
            $staking->reward_earned = $reward;
            $user->balance_ldp += $amount + $reward;
            $message = 'Staking terminé avec succès ! Gains ajoutés à votre solde.';
        } else {
            // Staking prématuré : récupérer uniquement le capital
            $staking->cancelled = true;
            $user->balance_ldp += $amount;
            $message = 'Staking annulé. Capital récupéré, mais aucun gain n\'a été accordé.';
        }

        $user->save();
        $user = $user->fresh();
        $staking->save();

        return response()->json([
            'success' => true,
            'message' => $message,
            'staking' => [
                'id' => $staking->id,
                'amount' => (float) $staking->amount,
                'reward_earned' => (float) $staking->reward_earned,
                'cancelled' => (bool) $staking->cancelled,
                'unstaked_at' => $staking->unstaked_at,
            ],
            'new_balance' => (float) $user->balance_ldp,
        ]);
    }

    /**
     * Calculer les gains d'un staking mature
     */
    protected function calculateReward(Staking $staking)
    {
        $days = $staking->plan->duration;
        $apy = $staking->plan->apy;
        $amount = $staking->amount;
        
        return round(($amount * $apy / 100), 8);
    }
}