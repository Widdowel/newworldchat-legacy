<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bet extends Model
{
    use HasFactory;
    protected $fillable = [
        'pair',
        'max_gap',
        'start_hour',
        'end_hour',
        'win_percent',
        'loss_percent',
        'min_price',
        'hour_result'
    ];

    public function userBets()
    {
        return $this->hasMany(UserBet::class);
    }
}
