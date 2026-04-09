<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupInvite;
use App\Models\Group;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class GroupInviteController extends Controller
{
    public function createInvite($groupId)
    {
        $token = Str::random(20);
        $invite = GroupInvite::create([
            'group_id' => $groupId,
            'invite_token' => $token,
            'expires_at' => now()->addDays(7)
        ]);

        return response()->json([
            'invite_link' => url("/invite/{$token}")
        ]);
    }

    public function joinWithToken($token)
    {
        $invite = GroupInvite::where('invite_token', $token)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })->firstOrFail();

        $invite->group->members()->syncWithoutDetaching([auth()->id()]);

        return response()->json(['message' => 'Rejoint via lien']);
    }
}
