<?php

namespace App\Http\Controllers;

use App\Models\TapMultiplier;
use Illuminate\Http\Request;

class TapMultiplierController extends Controller
{

    public function index()
    {
        $multipliers = TapMultiplier::get();
        return response()->json($multipliers);
    }

    public function store(Request $request)
    {
        
        $request->validate([
            'coefficient'   => 'required',
            'required_taps' => 'required',
        ]);

        $multiplier = TapMultiplier::create([
            'coefficient'   => $request->coefficient,
            'required_taps' => $request->required_taps,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'required_taps' => 'required|integer|min:1'
        ]);

        $multiplier = TapMultiplier::findOrFail($id);
        $multiplier->required_taps = $request->required_taps;
        $multiplier->save();

        return redirect()->back();
    }
}
