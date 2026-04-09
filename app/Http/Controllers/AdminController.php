<?php

namespace App\Http\Controllers;

use App\Models\Tap;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;


class AdminController extends Controller
{
    public function dashboard()
    {
        $totalUsers = User::count();
        $totalLdpBalance = 0;
        $recentTransactions = Transaction::latest()->take(5)->get();
        $recentUsers = User::latest()->take(5)->get();
        $totalTaps = 0;
        $totalEarnedTokens = 0;
        $totalTransactions = 0;
        
        return view('admin.dashboard', compact(
            'totalUsers',
            'totalLdpBalance',
            'recentTransactions',
            'recentUsers',
            'totalTaps',
            'totalEarnedTokens',
            'totalTransactions'
        ));
    }

    public function generateCode()
    {
        $users = User::whereNull('code')->get();
        foreach ($users as $user) {
            $user->code = strtoupper(Str::random(1)) . rand(0, 9) . strtoupper(Str::random(3)) . rand(0, 9) . strtoupper(Str::random(1)) . strtoupper(Str::random(2));
            $user->save();
        }
        return 'Codes générés pour les utilisateurs sans code.';
    }
}
