<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staking extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'staking_plan_id', 'amount', 'staked_at', 'unstaked_at', 'reward_earned', 'cancelled'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(StakingPlan::class, 'staking_plan_id');
    }

    public function isMature()
    {
        return now()->diffInDays($this->staked_at) >= $this->plan->duration;
    }

    public function nbDays()
    {
        return now()->diffInDays($this->staked_at);
    }

    public function PourcentGainParJour()
    {
        return $this->plan->apy / $this->plan->duration;
    }

    public function amountGainParJour()
    {
        return ($this->PourcentGainParJour() * $this->amount) / 100;
    }
}
