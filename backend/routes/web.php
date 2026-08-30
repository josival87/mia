<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryGoalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\IphoneIntegrationController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TelegramConnectionController;
use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'landing'])->name('landing');
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->name('login.store');
    Route::get('/cadastro', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/cadastro', [AuthController::class, 'register'])->name('register.store');
    Route::get('/verificar-telegram', [AuthController::class, 'showVerification'])->name('verification.show');
    Route::post('/verificar-telegram', [AuthController::class, 'verify'])->name('verification.verify');
    Route::post('/verificar-telegram/novo-codigo', [AuthController::class, 'renewVerification'])->middleware('throttle:3,1')->name('verification.renew');
    Route::get('/criar-senha', [AuthController::class, 'showCreatePassword'])->name('password.create');
    Route::post('/criar-senha', [AuthController::class, 'createPassword'])->name('password.store');
});
Route::post('/sair', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])->name('telegram.webhook');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('financeiro', FinanceController::class)->parameters(['financeiro' => 'finance'])->except(['create', 'show'])->names('finance');
    Route::post('/financeiro/metas', [CategoryGoalController::class, 'store'])->name('finance.goals.store');
    Route::delete('/financeiro/metas/{goal}', [CategoryGoalController::class, 'destroy'])->name('finance.goals.destroy');
    Route::get('/alertas', [AlertController::class, 'index'])->name('alerts.index');
    Route::patch('/alertas/ler-todos', [AlertController::class, 'readAll'])->name('alerts.read-all');
    Route::patch('/alertas/{notification}/ler', [AlertController::class, 'read'])->name('alerts.read');
    Route::resource('atividades', TaskController::class)->parameters(['atividades' => 'task'])->except(['create', 'show'])->names('tasks');
    Route::patch('/atividades/{task}/status', [TaskController::class, 'status'])->name('tasks.status');
    Route::resource('categorias', CategoryController::class)->only(['index', 'store', 'destroy'])->names('categories');
    Route::get('/telegram', [TelegramConnectionController::class, 'show'])->name('telegram.connection');
    Route::post('/telegram/reconectar', [TelegramConnectionController::class, 'reconnect'])->name('telegram.reconnect');
    Route::delete('/telegram', [TelegramConnectionController::class, 'disconnect'])->name('telegram.disconnect');
    Route::post('/iphone/chave', [IphoneIntegrationController::class, 'store'])->name('iphone.token.store');
    Route::delete('/iphone/chave', [IphoneIntegrationController::class, 'destroy'])->name('iphone.token.destroy');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', fn () => redirect()->route('login', ['admin' => 1]))->name('login');
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/usuarios', [AdminController::class, 'users'])->name('users');
        Route::post('/usuarios', [AdminController::class, 'storeUser'])->name('users.store');
        Route::put('/usuarios/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::get('/clientes', [AdminController::class, 'clients'])->name('clients');
        Route::put('/clientes/{user}', [AdminController::class, 'updateClient'])->name('clients.update');
        Route::patch('/clientes/{user}/status', [AdminController::class, 'toggleClient'])->name('clients.status');
        Route::post('/clientes/{user}/telegram/reconectar', [AdminController::class, 'resetClientTelegram'])->name('clients.telegram.reset');
        Route::get('/categorias', [AdminController::class, 'categories'])->name('categories');
        Route::post('/categorias', [AdminController::class, 'storeCategory'])->name('categories.store');
        Route::delete('/categorias/{category}', [AdminController::class, 'destroyCategory'])->name('categories.destroy');
        Route::get('/configuracoes', [AdminController::class, 'settings'])->name('settings');
        Route::put('/configuracoes', [AdminController::class, 'updateSettings'])->name('settings.update');
    });
});
