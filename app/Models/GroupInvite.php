<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupInvite extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $dates = ['expires_at'];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
