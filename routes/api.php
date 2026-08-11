<?php

use App\Http\Controllers\Api\IntegrationExchangeIngestionController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

Route::post('/v1/integration-contracts/{contract}/exchanges', IntegrationExchangeIngestionController::class)
    ->middleware([EnsureClientIsResourceOwner::using('integrations:ingest'), 'throttle:integration-ingest'])
    ->name('api.integration-exchanges.store');
