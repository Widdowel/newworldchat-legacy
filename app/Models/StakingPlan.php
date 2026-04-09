<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StakingPlan extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'apy', 'duration', 'min_amount'];

    public function stakings()
    {
        return $this->hasMany(Staking::class);
    }
}
