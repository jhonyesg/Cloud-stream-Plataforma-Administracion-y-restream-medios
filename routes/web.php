<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Api\Admin\FsBrowseController;
use App\Http\Controllers\Api\MediaStreamController;
use App\Http\Controllers\Api\MediaThumbnailController;
use App\Http\Controllers\Client\ChannelController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\MediaController as ClientMediaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RedirectController::class, 'home'])->name('home');

Route::get('/dashboard', [RedirectController::class, 'dashboard'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/api/media-items/{mediaItem}/stream', [MediaStreamController::class, 'show'])->name('media.stream');
    Route::get('/api/media-items/{mediaItem}/thumb', [MediaThumbnailController::class, 'show'])->name('media.thumb');

    Route::post('/media/thumbnails/generate', [MediaThumbnailController::class, 'generate'])->name('media.thumbnails.generate');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [AdminDashboardController::class, 'users'])->name('users');
    Route::get('/users/{user}', [AdminDashboardController::class, 'userShow'])->name('users.show');
    Route::post('/users', [AdminDashboardController::class, 'userStore'])->name('users.store');
    Route::put('/users/{user}', [AdminDashboardController::class, 'userUpdate'])->name('users.update');
    Route::delete('/users/{user}', [AdminDashboardController::class, 'userDestroy'])->name('users.destroy');

    Route::get('/channels', [AdminDashboardController::class, 'channels'])->name('channels');
    Route::get('/channels/{channel}', [AdminDashboardController::class, 'channelShow'])->name('channels.show');
    Route::post('/channels', [AdminDashboardController::class, 'channelStore'])->name('channels.store');
    Route::put('/channels/{channel}', [AdminDashboardController::class, 'channelUpdate'])->name('channels.update');
    Route::delete('/channels/{channel}', [AdminDashboardController::class, 'channelDestroy'])->name('channels.destroy');

    Route::get('/fs/browse', [FsBrowseController::class, 'browse'])->name('fs.browse');

    Route::get('/media', [AdminMediaController::class, 'index'])->name('media');

    Route::get('/scheduler', [ScheduleController::class, 'index'])->name('scheduler');
});

Route::middleware(['auth', 'role:client'])->prefix('client')->name('client.')->group(function () {
    Route::get('/', [ClientDashboardController::class, 'index'])->name('dashboard');
    Route::get('/channels', [ChannelController::class, 'index'])->name('channels');
    Route::put('/channels/{channel}', [ChannelController::class, 'update'])->name('channels.update');
    Route::get('/media', [ClientMediaController::class, 'index'])->name('media');
    Route::get('/scheduler', [App\Http\Controllers\Client\ScheduleController::class, 'index'])->name('scheduler');
});

require __DIR__.'/auth.php';
