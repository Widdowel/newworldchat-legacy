<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tap extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'tap_count', 'earned_ldp'];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
