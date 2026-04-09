<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBooster extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'booster_id',
        'activated_at',
        'expires_at'
    ];

    protected $dates = ['activated_at', 'expires_at'];

    public function booster()
    {
        return $this->belongsTo(Booster::class);
    }

    public function isActive()
    {
        return now()->lt($this->expires_at);
    }

    public function remainingDays()
    {
        return now()->diffInDays($this->expires_at, false);
    }
}
