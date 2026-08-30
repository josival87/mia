<?php

use App\Http\Controllers\Api\V1\ExternalReceiptController;
use App\Http\Controllers\Api\V1\IphoneBankEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['throttle:external-finance', 'external.integration'])
    ->group(function () {
        Route::post('/clientes/{client}/recebimentos', [ExternalReceiptController::class, 'store'])
            ->whereNumber('client')
            ->name('api.v1.clients.receipts.store');
        Route::get('/clientes/{client}/recebimentos/{externalId}', [ExternalReceiptController::class, 'show'])
            ->whereNumber('client')
            ->where('externalId', '[A-Za-z0-9][A-Za-z0-9._:-]{0,119}')
            ->name('api.v1.clients.receipts.show');
    });

Route::prefix('v1/iphone')
    ->middleware(['throttle:iphone-bank', 'iphone.integration'])
    ->group(function () {
        Route::post('/eventos-bancarios', [IphoneBankEventController::class, 'store'])
            ->name('api.v1.iphone.bank-events.store');
    });
