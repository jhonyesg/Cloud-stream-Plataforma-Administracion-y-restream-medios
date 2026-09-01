<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Admin\RestreamQuotaController;
use App\Http\Controllers\Admin\RestreamTargetController as AdminRestreamTargetController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Api\Admin\FsBrowseController;
use App\Http\Controllers\Api\MediaStreamController;
use App\Http\Controllers\Api\MediaThumbnailController;
use App\Http\Controllers\Client\ChannelController;
use App\Http\Controllers\Client\RestreamHomeController;
use App\Http\Controllers\Client\RestreamPlatformAccountController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\MediaController as ClientMediaController;
use App\Http\Controllers\Client\RestreamTargetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSeoController;
use App\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RedirectController::class, 'home'])->name('home');

Route::get('/sitemap.xml', [PublicSeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [PublicSeoController::class, 'robots'])->name('robots');
Route::get('/llms.txt', [PublicSeoController::class, 'llms'])->name('llms');

Route::get('/streaming-para-iglesias-colombia', [PublicSeoController::class, 'landing'])->defaults('slug', 'streaming-para-iglesias-colombia')->name('public.landing.iglesias');
Route::get('/streaming-para-emisoras-de-radio-online', [PublicSeoController::class, 'landing'])->defaults('slug', 'streaming-para-emisoras-de-radio-online')->name('public.landing.emisoras');
Route::get('/streaming-para-canales-de-tv-regionales', [PublicSeoController::class, 'landing'])->defaults('slug', 'streaming-para-canales-de-tv-regionales')->name('public.landing.canales-tv');
Route::get('/streaming-para-universidades-y-educacion', [PublicSeoController::class, 'landing'])->defaults('slug', 'streaming-para-universidades-y-educacion')->name('public.landing.universidades');
Route::get('/streaming-para-productoras-de-contenido', [PublicSeoController::class, 'landing'])->defaults('slug', 'streaming-para-productoras-de-contenido')->name('public.landing.productoras');
Route::get('/restream-facebook-youtube-tiktok', [PublicSeoController::class, 'landing'])->defaults('slug', 'restream-facebook-youtube-tiktok')->name('public.landing.restream');
Route::get('/servidor-rtmp-colombia', [PublicSeoController::class, 'landing'])->defaults('slug', 'servidor-rtmp-colombia')->name('public.landing.servidor-rtmp');

Route::get('/blog', [PublicSeoController::class, 'blogIndex'])->name('public.blog.index');
Route::get('/blog/cuanto-cuesta-transmitir-tv-internet', [PublicSeoController::class, 'blog'])->defaults('slug', 'cuanto-cuesta-transmitir-tv-internet')->name('public.blog.cuanto-cuesta');
Route::get('/blog/como-transmitir-canal-tv-internet', [PublicSeoController::class, 'blog'])->defaults('slug', 'como-transmitir-canal-tv-internet')->name('public.blog.como-transmitir');
Route::get('/blog/que-es-rtmp-hls', [PublicSeoController::class, 'blog'])->defaults('slug', 'que-es-rtmp-hls')->name('public.blog.que-es-rtmp-hls');
Route::get('/blog/alternativas-a-obs-para-transmitir-24-7', [PublicSeoController::class, 'blog'])->defaults('slug', 'alternativas-a-obs-para-transmitir-24-7')->name('public.blog.obs-alternativas');
Route::get('/blog/como-elegir-servidor-de-streaming-en-colombia', [PublicSeoController::class, 'blog'])->defaults('slug', 'como-elegir-servidor-de-streaming-en-colombia')->name('public.blog.como-elegir-servidor');
Route::get('/blog/streaming-para-iglesias-como-transmitir-misas-y-eventos-en-vivo', [PublicSeoController::class, 'blog'])->defaults('slug', 'streaming-para-iglesias-como-transmitir-misas-y-eventos-en-vivo')->name('public.blog.iglesias');
Route::get('/blog/como-verificar-que-tu-pauta-publicitaria-se-emitio-en-tv', [PublicSeoController::class, 'blog'])->defaults('slug', 'como-verificar-que-tu-pauta-publicitaria-se-emitio-en-tv')->name('public.blog.verificar-pauta');
Route::get('/blog/rtmp-vs-hls-vs-srt-diferencias', [PublicSeoController::class, 'blog'])->defaults('slug', 'rtmp-vs-hls-vs-srt-diferencias')->name('public.blog.rtmp-hls-srt');
Route::get('/blog/casos-de-uso-emisoras-colombianas-24-7', [PublicSeoController::class, 'blog'])->defaults('slug', 'casos-de-uso-emisoras-colombianas-24-7')->name('public.blog.casos-uso');
Route::get('/blog/como-empezar-a-transmitir-tu-canal-en-5-minutos', [PublicSeoController::class, 'blog'])->defaults('slug', 'como-empezar-a-transmitir-tu-canal-en-5-minutos')->name('public.blog.5-minutos');

Route::get('/preguntas-frecuentes', [PublicSeoController::class, 'faq'])->name('public.faq');
Route::get('/sobre-nosotros', [PublicSeoController::class, 'about'])->name('public.about');

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

    Route::get('/channels/{channel}/restream', [RestreamQuotaController::class, 'show'])->name('channels.restream.show');
    Route::post('/channels/{channel}/restream', [RestreamQuotaController::class, 'store'])->name('channels.restream.store');
    Route::patch('/channels/{channel}/restream', [RestreamQuotaController::class, 'update'])->name('channels.restream.update');
    Route::delete('/channels/{channel}/restream', [RestreamQuotaController::class, 'destroy'])->name('channels.restream.destroy');

    Route::prefix('restream')->name('restream.')->group(function () {
        Route::get('/', [AdminRestreamTargetController::class, 'index'])->name('index');
        Route::post('/channels/{channel}/restream-targets', [AdminRestreamTargetController::class, 'store'])->name('store');
        Route::get('/channels/{channel}/restream-targets/{target}', [AdminRestreamTargetController::class, 'show'])->name('show');
        Route::patch('/channels/{channel}/restream-targets/{target}', [AdminRestreamTargetController::class, 'update'])->name('update');
        Route::delete('/channels/{channel}/restream-targets/{target}', [AdminRestreamTargetController::class, 'destroy'])->name('destroy');
        Route::post('/channels/{channel}/restream-targets/{target}/start', [AdminRestreamTargetController::class, 'start'])->name('start');
        Route::post('/channels/{channel}/restream-targets/{target}/stop', [AdminRestreamTargetController::class, 'stop'])->name('stop');
        Route::get('/channels/{channel}/restream-targets/{target}/log', [AdminRestreamTargetController::class, 'log'])->name('log');
    });

    Route::get('/channels', [AdminDashboardController::class, 'channels'])->name('channels');
    Route::get('/channels/{channel}', [AdminDashboardController::class, 'channelShow'])->name('channels.show');
    Route::post('/channels', [AdminDashboardController::class, 'channelStore'])->name('channels.store');
    Route::put('/channels/{channel}', [AdminDashboardController::class, 'channelUpdate'])->name('channels.update');
    Route::patch('/channels/{channel}/archive', [AdminDashboardController::class, 'channelArchive'])->name('channels.archive');
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

    Route::middleware('restream.enabled')->prefix('restream')->name('restream.')->group(function () {
        Route::get('/', [RestreamHomeController::class, 'index'])->name('index');
        Route::get('/accounts', [RestreamPlatformAccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/{platform}/connect', [RestreamPlatformAccountController::class, 'connect'])->name('accounts.connect');
        Route::get('/accounts/{platform}/callback', [RestreamPlatformAccountController::class, 'callback'])->name('accounts.callback');
        Route::delete('/accounts/{platform}', [RestreamPlatformAccountController::class, 'destroy'])->name('accounts.destroy');
        Route::get('/channels/{channel}/restream-targets', [RestreamTargetController::class, 'index'])->name('channels.index');
        Route::post('/channels/{channel}/restream-targets', [RestreamTargetController::class, 'store'])->name('channels.store');
        Route::get('/channels/{channel}/restream-targets/{target}', [RestreamTargetController::class, 'show'])->name('channels.show');
        Route::patch('/channels/{channel}/restream-targets/{target}', [RestreamTargetController::class, 'update'])->name('channels.update');
        Route::delete('/channels/{channel}/restream-targets/{target}', [RestreamTargetController::class, 'destroy'])->name('channels.destroy');
        Route::post('/channels/{channel}/restream-targets/{target}/start', [RestreamTargetController::class, 'start'])->name('channels.start');
        Route::post('/channels/{channel}/restream-targets/{target}/stop', [RestreamTargetController::class, 'stop'])->name('channels.stop');
        Route::get('/channels/{channel}/restream-targets/{target}/log', [RestreamTargetController::class, 'log'])->name('channels.log');
    });
});

require __DIR__.'/auth.php';
