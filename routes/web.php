<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FindingController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\VectorController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('vectors', VectorController::class);
    Route::patch('vectors/{vector}/status', [VectorController::class, 'updateStatus'])->name('vectors.status');
    Route::post('vectors/{vector}/vote', [VoteController::class, 'vector'])
        ->middleware('throttle:60,1')
        ->name('vectors.vote');

    Route::resource('targets', TargetController::class);
    Route::patch('targets/{target}/status', [TargetController::class, 'updateStatus'])->name('targets.status');
    Route::post('targets/{target}/vote', [VoteController::class, 'target'])
        ->middleware('throttle:60,1')
        ->name('targets.vote');

    Route::resource('analyses', AnalysisController::class)->only(['index', 'store', 'show']);
    Route::resource('findings', FindingController::class)->only(['index', 'show', 'update']);
});

require __DIR__.'/settings.php';
