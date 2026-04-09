<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ambassador extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'percentage','percentage_usdt'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
