<?php

use App\Http\Controllers\Api\AdminController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\GroupInviteController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\BetController;
use App\Http\Controllers\BoosterController;
use App\Http\Controllers\StakingController;
use App\Http\Controllers\TapController;
use App\Http\Controllers\TapMultiplierController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserBoosterController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Auth;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées par authentification
Route::middleware('auth:sanctum')->group(function () {

    // 🔹 Auth
    Route::post('/logout', [AuthController::class, 'logout']);

    // 🔹 Conversations privées
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversationId}/messages', [MessageController::class, 'getConversationMessages']);

    // 🔹 Messages
    Route::post('/messages', [MessageController::class, 'store']);
    Route::get('/groups/{groupId}/messages', [MessageController::class, 'getGroupMessages']);
    Route::delete('/messages/{id}', [MessageController::class, 'destroy']);
    // 🔹 Groupes
    Route::get('/groups', [GroupController::class, 'index']); // créer groupe
    Route::get('/groups/{groupId}', [GroupController::class, 'show']); // créer groupe
    Route::post('/groups', [GroupController::class, 'store']); // créer groupe
    Route::post('/groups/{groupId}/join', [GroupController::class, 'join']); // rejoindre groupe
    Route::get('/groups/{groupId}/members', [GroupController::class, 'members']); // liste membres

    // 🔹 Invitations
    Route::post('/groups/{groupId}/invite', [GroupInviteController::class, 'createInvite']); // créer lien
    Route::post('/invite/{token}', [GroupInviteController::class, 'joinWithToken']); // rejoindre via lien

    // 🔹 Appels
    Route::get('/calls', [CallController::class, 'index']);
    Route::post('/calls/start', [CallController::class, 'start']);
    Route::post('/calls/{id}/end', [CallController::class, 'end']);





    Route::get('/statuses', [StatusController::class, 'index']); // tous les statuts actifs
    Route::get('/statuses/user/{userId}', [StatusController::class, 'userStatuses']); // statuts d’un user
    Route::post('/statuses', [StatusController::class, 'store']); // créer un statut
    Route::delete('/statuses/{id}', [StatusController::class, 'destroy']); // supprimer un statut
    Route::post('/statuses/{id}/view', [StatusController::class, 'markAsViewed']); // marquer comme vu
    Route::get('/statuses/{id}/viewers', [StatusController::class, 'viewers']); // voir les gens qui ont vu

    Route::post('/groups/add-members', [GroupController::class, 'addMember'])
        ->name('groups.addMembers');
    Route::post('/groups/join', [GroupController::class, 'joinGroup'])
        ->name('groups.join');


    Route::post('/groups/toggle-admin', [GroupController::class, 'toggleAdmin']);

    Route::post('/groups/remove-member', [GroupController::class, 'removeMember'])
        ->name('groups.removeMember');



    Route::get('/users', action: [AdminController::class, 'index'])->name('users.index');


    Route::post('/save-device-token', [AuthController::class, 'saveDeviceToken']);


    Route::get('/boosters', [BoosterController::class, 'index']);
    Route::post('/tap', [TapController::class, 'tap']);
    Route::get('/tap_multipliers', [TapMultiplierController::class, 'index']);
    Route::get('/user/active-booster', [UserBoosterController::class, 'getActiveBooster']);

    Route::post('/activate-booster', [BoosterController::class, 'activateBooster']);
    Route::get('/user/boosters', [BoosterController::class, 'getUserBoosters']);


    Route::get('/user/profile', [UsersController::class, 'getProfile']);
    Route::put('/user/profile', [UsersController::class, 'updateProfile']);
    Route::get('/user/statistics', [UsersController::class, 'getStatistics']);
    Route::get('/user/transactions', [UsersController::class, 'getTransactions']);

    // Dépôts
    Route::post('/deposit/usdt', [TransactionController::class, 'depositUsdt']);
    Route::post('/deposit/ldp', [TransactionController::class, 'depositLdp']);

    // Conversion
    Route::post('/conversion/process', [TransactionController::class, 'processConversion']);
    Route::get('/conversion/rates', [TransactionController::class, 'getConversionRates']);

    // Retrait
    Route::post('/withdrawal/process', [TransactionController::class, 'withdrawTokens']);
    Route::get('/withdrawal/settings', [TransactionController::class, 'getWithdrawalSettings']);

    // Réseaux et adresses
    Route::get('/networks', [TransactionController::class, 'getNetworks']);

    Route::get('/leaderboard/weekly', [TapController::class, 'getWeeklyLeaderboard']);

    Route::get('/me', [UsersController::class, 'me']);

    // ============================================================
    // ROUTES CAPTCHA DÉSACTIVÉES - Plus de vérification humaine
    // Les utilisateurs peuvent taper jusqu'à leur limite journalière
    // ============================================================
    // Route::post('/tap/failed-captcha', [TapController::class, 'handleFailedCaptcha']);
    // Route::post('/tap/success-captcha', [TapController::class, 'handleCaptchaSuccess']);
    // Route::get('/tap/check-unblock', [TapController::class, 'checkUnblockStatus']);

    // Route de test pour debug - SANS authentification
    Route::get('/debug-auth', function (Request $request) {
        return response()->json([
            'headers' => $request->headers->all(),
            'auth_check' => Auth::check(),
            'auth_id' => Auth::id(),
            'bearer_token' => $request->bearerToken() ? 'Present' : 'Missing',
            'timestamp' => now()->toISOString()
        ]);
    })->middleware('auth:sanctum');


    Route::get('/staking', [StakingController::class, 'index']);
    Route::post('/staking/subscribe', [StakingController::class, 'store']);
    Route::post('/staking/{id}/unstake', [StakingController::class, 'unstake']);



    Route::get('/bets', [BetController::class, 'index']);
    // Détails d'une paire spécifique
    Route::get('/bets/{id}', [BetController::class, 'show']);
    // Mes paris avec filtres optionnels
    // Paramètres: ?result=pending|win|lose|all&bet_id=1&start_date=2025-01-01&end_date=2025-12-31&per_page=20
    Route::get('/bet/my-bets', [BetController::class, 'myBets']);
    // Top 10 des meilleurs gains
    Route::get('/bet/leaderboard', [BetController::class, 'leaderboard']);
    // Mes statistiques
    Route::get('/bet/my-statistics', [BetController::class, 'myStatistics']);
    // Placer un pari
    Route::post('/bet/place', [BetController::class, 'place']);
    // Annuler un pari (dans les 5 min)
    Route::post('/bet/{id}/cancel', [BetController::class, 'cancel']);
});


Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('verify-reset-code', [AuthController::class, 'verifyResetCode']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
