<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function participants()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    protected $casts = [
        'duration' => 'integer',
    ];
}
