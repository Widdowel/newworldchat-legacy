<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Message;
use App\Models\GroupInvite;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'is_public',
        'description',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'owner_id' => 'integer'
    ];

    /**
     * Propriétaire du groupe
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Membres du groupe (relation Many-to-Many)
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withTimestamps();
    }

    /**
     * Messages du groupe
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Dernier message
     */
    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Invitations
     */
    public function invites()
    {
        return $this->hasMany(GroupInvite::class);
    }

    /**
     * Ajouter un utilisateur dans le groupe
     */
    public function addMember(User $user)
    {
        // Vérifie s'il n'est pas déjà membre
        if (!$this->members()->where('user_id', $user->id)->exists()) {
            $this->members()->attach($user->id);
        }
        return $this;
    }

    /**
     * Retirer un utilisateur du groupe
     */
    public function removeMember(User $user)
    {
        $this->members()->detach($user->id);
        return $this;
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withPivot('is_admin')
            ->withTimestamps();
    }

    public function admins()
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->wherePivot('is_admin', true)
            ->withTimestamps();
    }
}
