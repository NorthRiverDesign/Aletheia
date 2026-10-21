<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\BannedEmailDomainController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FindingController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReferenceSourceController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\VectorController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('map', MapController::class)->name('map');

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

    Route::resource('reference-sources', ReferenceSourceController::class)->only(['index', 'store', 'destroy']);
    Route::patch('reference-sources/{reference_source}/verify', [ReferenceSourceController::class, 'verify'])->name('reference-sources.verify');

    Route::get('members', [MemberController::class, 'index'])->name('members.index');
    Route::put('members/{user}/ban', [MemberController::class, 'ban'])->name('members.ban');
    Route::delete('members/{user}/ban', [MemberController::class, 'unban'])->name('members.unban');
    Route::resource('banned-domains', BannedEmailDomainController::class)->only(['store', 'destroy']);
});

require __DIR__.'/settings.php';
