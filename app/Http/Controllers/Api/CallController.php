<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CallController extends Controller
{
    public function index()
    {
        $user = User::find(Auth::id());
        return $user->allCalls()
            ->with(['initiator', 'participants'])
            ->orderBy('created_at', 'desc')
            ->get();

    }
    public function start(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:voice,video',
            'participants' => 'required|array'
        ]);

        $call = Call::create([
            'type' => $data['type'],
            'initiator_id' => auth()->id(),
            'status' => 'ongoing'
        ]);

        $call->participants()->attach($data['participants']);
        $call->participants()->attach(auth()->id());

        return response()->json($call);
    }

    public function end($id)
    {
        $call = Call::findOrFail($id);
        $call->update(['status' => 'ended']);

        return response()->json(['message' => 'Appel terminé']);
    }
}
