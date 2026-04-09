<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;


class StatusController extends Controller
{

    public function index(Request $request)
    {
        $userId = Auth::id();

        $statuses = Status::with('admin')
            ->active()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($status) use ($userId) {
                return [
                    'id' => $status->id,
                    'content' => $status->content,
                    'image_url' => $status->full_image_url,
                    'views_count' => intval($status->views_count),
                    'is_viewed' => $status->isViewedBy($userId),
                    'created_at' => $status->created_at->toISOString(),
                    'admin' => [
                        'id' => $status->admin->id,
                        'name' => $status->admin->name,
                    ],
                ];
            });

        return response()->json($statuses);
    }

    /**
     * Créer un nouveau statut (admin uniquement)
     */
    public function store(Request $request)
    {
        // Vérifier que c'est un admin
        if (Auth::user()->is_admin == false) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'attachment' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'expires_in_hours' => 'nullable|integer|min:1|max:168',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $imagePath = null;

        if ($request->hasFile('attachment')) {
            $originalExtension = $request->file('attachment')->getClientOriginalExtension();
            $filename = time() . '_' . uniqid() . '.' . $originalExtension;
            $imagePath = $request->file('attachment')->storeAs('attachments', $filename, 'public');
        }

        $expiresAt = null;
        if ($request->has('expires_in_hours')) {
            $expiresAt = now()->addHours($request->expires_in_hours);
        } else {
            $expiresAt = now()->addDays(2);
        }

        $status = Status::create([
            'admin_id' => Auth::user()->id,
            'content' => $request->content,
            'image_url' => $imagePath,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statut créé avec succès',
            'status' => [
                'id' => $status->id,
                'content' => $status->content,
                'image_url' => $status->full_image_url,
                'views_count' => 0,
                'is_viewed' => false,
                'created_at' => $status->created_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * Marquer un statut comme vu
     */
    public function markAsViewed(Request $request, $statusId)
    {
        $userId = Auth::id();
        $status = Status::find($statusId);

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'Statut introuvable',
            ], 404);
        }

        if (!$status->isViewedBy($userId)) {
            $status->views()->attach($userId, [
                'viewed_at' => now(),
            ]);
            $status->increment('views_count');
        }

        return response()->json([
            'success' => true,
            'message' => 'Statut marqué comme vu',
        ]);
    }

    /**
     * Supprimer un statut (admin uniquement)
     */
    public function destroy($statusId)
    {
        if (Auth::user()->is_admin == false) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $status = Status::find($statusId);

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'Statut introuvable',
            ], 404);
        }

        // Supprimer l'image si elle existe
        if ($status->image_url && Storage::disk('public')->exists($status->image_url)) {
            Storage::disk('public')->delete($status->image_url);
        }

        $status->delete();

        return response()->json([
            'success' => true,
            'message' => 'Statut supprimé',
        ]);
    }

    /**
     * Obtenir les statistiques d'un statut (admin uniquement)
     */
    public function statistics($statusId)
    {
        if (Auth::user()->is_admin == false) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $status = Status::with('views.user')->find($statusId);

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'Statut introuvable',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'statistics' => [
                'views_count' => $status->views_count,
                'viewers' => $status->views->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'viewed_at' => $user->pivot->viewed_at,
                    ];
                }),
            ],
        ]);
    }
}
