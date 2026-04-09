<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['tag', 'value', 'description', 'is_file'];

    protected $casts = [
        'is_file' => 'boolean',
    ];

    // Vérifie si le fichier est une image
    public function isImage()
    {
        if (!$this->is_file) return false;

        return preg_match('/\.(jpg|jpeg|png|gif)$/i', $this->value);
    }

    protected static function booted()
    {
        static::deleting(function ($setting) {
            if ($setting->is_file && Storage::exists($setting->value)) {
                Storage::delete($setting->value);
            }
        });
    }
}
