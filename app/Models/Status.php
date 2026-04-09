<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'content',
        'image_url',
        'views_count',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $appends = ['full_image_url'];

    // Relations
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function views()
    {
        return $this->belongsToMany(User::class, 'status_views')
            ->withTimestamps()
            ->withPivot('viewed_at');
    }

    // Vérifier si un utilisateur a vu ce statut
    public function isViewedBy($userId)
    {
        return $this->views()->where('user_id', $userId)->exists();
    }

    // URL complète de l'image
    public function getFullImageUrlAttribute()
    {
        if (!$this->image_url) {
            return null;
        }

        // Si c'est déjà une URL complète
        if (filter_var($this->image_url, FILTER_VALIDATE_URL)) {
            return $this->image_url;
        }

        // Sinon, construire l'URL
        return url( $this->image_url);
    }

    // Scope pour les statuts non expirés
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    // Scope pour les statuts des dernières 24h (style Stories)
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDay());
    }
}