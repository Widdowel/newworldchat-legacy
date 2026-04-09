<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TapMultiplier extends Model
{
    use HasFactory;
     protected $fillable = [
        'coefficient',
        'required_taps',
    ];
}
