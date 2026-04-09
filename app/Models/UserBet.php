<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBet extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'bet_id',
        'amount',
        'min_price',
        'max_price',
        'result',
        'placed_at',
        'evaluated_at',
        'value',
        'real_price'
    ];

    public function bet()
    {
        return $this->belongsTo(Bet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
