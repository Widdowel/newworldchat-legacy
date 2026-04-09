<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'codeParent' => 'required|string',
            'email' => 'nullable|email|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $data['password'] = Hash::make($data['password']);

        $parent = User::where('code', $data['codeParent'])->first();
        if ($parent) {
            $data['referrer_id'] = $parent->id;
        }
        unset($data['codeParent']);
        $user = User::create($data);
        Conversation::create(['user_id' => $user->id]);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('authToken')->plainTextToken
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required',
            'password' => 'required'
        ]);

        $user = User::where('email', $data['email'] ?? null)
            ->orWhere('phone', $data['email'] ?? null)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('authToken')->plainTextToken
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Déconnecté avec succès']);
    }

    public function saveDeviceToken(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
        ]);

        // auth()->user()->update([
        //     'device_token' => $request->device_token,
        // ]);

        return response()->json(['message' => 'Device token saved']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $code = rand(100000, 999999);

        DB::table('mot_de_passe_resets')->where('email', $request->email)->delete();

        // Enregistrer le code
        DB::table('mot_de_passe_resets')->insert([
            'email' => $request->email,
            'code' => $code,
            'created_at' => now()
        ]);

        // Envoi du mail
        Mail::raw("Votre code de réinitialisation est : $code", function ($message) use ($request) {
            $message->to($request->email)
                ->subject('Code de réinitialisation');
        });

        return response()->json([
            'message' => 'Code envoyé par email'
        ]);
    }


    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string'
        ]);

        $record = DB::table('mot_de_passe_resets')
            ->where('email', $request->email)
            ->where('code', $request->code)
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'Code invalide ou expiré'
            ], 400);
        }

        return response()->json([
            'message' => 'Code validé'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string',
            'password' => 'required|string|min:6|confirmed'
        ]);

        $record = DB::table('mot_de_passe_resets')
            ->where('email', $request->email)
            ->where('code', $request->code)
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'Code invalide ou expiré'
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Supprimer le code après utilisation
        DB::table('mot_de_passe_resets')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Mot de passe changé avec succès'
        ]);
    }
}
