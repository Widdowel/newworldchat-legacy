<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_admin',
        'device_token',
        'telegram_id',
        'balance_ldp',
        'balance_usdt',
        'code',
        'is_premium',
        'referrer_id',
        'last_tap_at',
        'is_blocked',
        'shown_at_30',
        'shown_at_60',
        'shown_at_90',
        'tapped_out_at',
        'taps',
        'langue',
        'device_id'
    ];


    protected static function boot()
    {
        parent::boot();
        static::creating(function ($user) {
            $user->code = strtoupper(Str::random(1)) . rand(0, 9) . strtoupper(Str::random(3)) . rand(0, 9) . strtoupper(Str::random(1)) . strtoupper(Str::random(2));
        });
    }


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean'
    ];

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    public function readMessages()
    {
        return $this->belongsToMany(Message::class, 'message_user')->withTimestamps();
    }

    // 🔹 Groupes où il est membre
    public function groups()
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    // 🔹 Groupes qu'il a créés
    public function ownedGroups()
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    // 🔹 Appels
    public function calls()
    {
        return $this->belongsToMany(Call::class)->withTimestamps();
    }

    public function initiatedCalls()
    {
        return $this->hasMany(Call::class, 'initiator_id');
    }

    public function allCalls()
    {
        return Call::where('initiator_id', $this->id)
            ->orWhereHas('participants', function ($q) {
                $q->where('users.id', $this->id);
            });
    }


    public function staking()
    {
        return $this->hasMany(Staking::class);
    }

    public function statuses()
    {
        return $this->hasMany(Status::class);
    }

    public function viewedStatuses()
    {
        return $this->belongsToMany(Status::class, 'status_views')->withTimestamps();
    }

    public function bets()
    {
        return $this->hasMany(Bet::class);
    }
}
