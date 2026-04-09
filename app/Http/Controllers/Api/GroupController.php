<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function index()
    {
        $user = User::find(Auth::id());

        // 1. Groupes où l'utilisateur est déjà membre
        $memberGroups = $user->groups()
            ->with(['lastMessage', 'owner'])
            ->get()
            ->map(function ($group) use ($user) {
                $group->unread_count = $group->messages()
                    ->whereDoesntHave('readBy', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->count();
                $group->is_member = true;
                return $group;
            });

        // 2. Groupes publics où l'utilisateur n'est pas encore membre
        $publicGroups = Group::where('is_public', true)
            ->whereDoesntHave('members', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->with(['lastMessage', 'owner'])
            ->get()
            ->map(function ($group) {
                $group->unread_count = 0; // pas encore membre → pas de messages non lus
                $group->is_member = false;
                return $group;
            });

        // 3. Fusionner les deux collections
        $groups = $memberGroups->merge($publicGroups);

        return response()->json($groups);
    }


    public function show($group_id)
    {
        $group = Group::findOrFail($group_id);
        return response()->json($group);
    }

    public function store(Request $request)
    {

        file_put_contents(base_path('templ.txt'), print_r($request->all(), true));
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'string',
            'is_public' => 'nullable',
        ]);
        $data['is_public'] = $request->boolean('is_public', false);
        $data['owner_id'] = auth()->id();

        $group = Group::create($data);
        $group->members()->attach(auth()->id());

        return response()->json($group);
    }

    public function removeMember(Request $request)
    {
        $data = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $group = Group::findOrFail($data['group_id']);
        $group->members()->detach($data['user_id']);

        return response()->json(['message' => 'Membre retiré du groupe']);
    }


    public function members($groupId)
    {
        // $group = Group::with('members')->findOrFail($groupId);
        // return response()->json($group->members);

        $group = Group::findOrFail($groupId);

        $members = $group->users()
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_online' => $user->is_online ?? false,
                    'pivot' => [
                        'role' => $user->pivot->is_admin ? 'admin' : 'member',
                        'is_admin' => (bool) $user->pivot->is_admin,
                    ]
                ];
            });

        return response()->json($members);
    }


    public function addMember(Request $request)
    {
        $data = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $group = Group::findOrFail($data['group_id']);

        $group->members()->syncWithoutDetaching($data['user_ids']);

        return response()->json([
            'message' => 'Utilisateurs ajoutés avec succès au groupe.',
            'group'   => $group->load('members'),
        ]);
    }

    public function joinGroup(Request $request)
    {
        $data = $request->validate([
            'group_id' => 'required|exists:groups,id',
        ]);
        $group = Group::findOrFail($data['group_id']);
        $user = auth()->user();
        $group->members()->syncWithoutDetaching([$user->id]);
        return response()->json([
            'message' => 'Vous avez rejoint le groupe avec succès.',
            'group'   => $group->load('members'),
        ]);
    }


    public function toggleAdmin(Request $request)
    {
        $group = Group::findOrFail($request->group_id);

        $isAdmin = $group->users()
            ->where('user_id', auth()->id())
            ->wherePivot('is_admin', true)
            ->exists();

        if (!$isAdmin && $group->owner_id !== auth()->id()) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $group->users()->updateExistingPivot($request->user_id, [
            'is_admin' => $request->is_admin === 'true' ? 1 : 0,
        ]);

        return response()->json(['success' => true]);
    }
}
