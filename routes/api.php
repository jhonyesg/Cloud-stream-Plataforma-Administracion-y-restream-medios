<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Client\ChannelController as ClientChannelController;
use App\Http\Controllers\Api\Media\MediaUploadController;
use App\Http\Controllers\Api\MediaItemController;
use App\Http\Controllers\Api\MediaStreamController;
use App\Http\Controllers\Api\MediaThumbnailController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\ScheduleTemplateController;
use App\Http\Controllers\Api\VirtualScreenController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/channels', [ClientChannelController::class, 'index']);
    Route::get('/virtual-screens/{channelId}', [VirtualScreenController::class, 'show']);
    Route::put('/virtual-screens/{channelId}', [VirtualScreenController::class, 'update']);
    Route::post('/virtual-screens/{channelId}/test-preview', [VirtualScreenController::class, 'testPreview']);

    Route::get('/media-items', [MediaItemController::class, 'index']);
    Route::post('/media-items', [MediaItemController::class, 'store']);
    Route::get('/media-items/{mediaItem}', [MediaItemController::class, 'show']);
    Route::put('/media-items/{mediaItem}', [MediaItemController::class, 'update']);
    Route::delete('/media-items/{mediaItem}', [MediaItemController::class, 'destroy']);
    Route::post('/media-items/upload', [MediaUploadController::class, 'upload']);
    Route::post('/media-items/bulk-upload', [MediaUploadController::class, 'bulkUpload']);
    Route::post('/media-items/bulk-delete', [MediaItemController::class, 'bulkDelete']);
    Route::post('/media-items/bulk-thumbnails', [MediaItemController::class, 'bulkThumbnails']);

    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::get('/playlists/{playlist}', [PlaylistController::class, 'show']);
    Route::put('/playlists/{playlist}', [PlaylistController::class, 'update']);
    Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy']);
    Route::post('/playlists/{playlist}/items', [PlaylistController::class, 'addItem']);
    Route::put('/playlists/{playlist}/items/reorder', [PlaylistController::class, 'reorderItems']);
    Route::delete('/playlists/{playlist}/items/{item}', [PlaylistController::class, 'removeItem']);
    Route::post('/playlists/{playlist}/cues', [PlaylistController::class, 'insertCue']);
    Route::post('/playlists/{playlist}/cleanup-splits', [PlaylistController::class, 'cleanup']);
    Route::post('/playlists/{playlist}/reset-sequential', [PlaylistController::class, 'resetToSequential']);

    Route::get('/schedule-templates', [ScheduleTemplateController::class, 'index']);
    Route::post('/schedule-templates', [ScheduleTemplateController::class, 'store']);
    Route::get('/schedule-templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'show']);
    Route::put('/schedule-templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'update']);
    Route::delete('/schedule-templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'destroy']);
    Route::post('/schedule-templates/{scheduleTemplate}/clone', [ScheduleTemplateController::class, 'clone']);
    Route::post('/schedule-templates/{scheduleTemplate}/clone-preview', [ScheduleTemplateController::class, 'clonePreview']);
    Route::post('/schedule-templates/{scheduleTemplate}/replicate', [ScheduleTemplateController::class, 'replicate']);
    Route::put('/schedule-templates/{scheduleTemplate}/days/{day}', [ScheduleTemplateController::class, 'assignDay']);

    // Timeline 00:00-24:00 endpoints.
    Route::get('/schedule-templates/{scheduleTemplate}/days/{day}/timeline', [\App\Http\Controllers\Api\TimelineController::class, 'show']);
    Route::post('/schedule-templates/{scheduleTemplate}/days/{day}/timeline/insert-cue', [\App\Http\Controllers\Api\TimelineController::class, 'insertCue']);
    Route::delete('/schedule-templates/{scheduleTemplate}/days/{day}/timeline/{timelineItemId}', [\App\Http\Controllers\Api\TimelineController::class, 'destroy']);
    Route::post('/schedule-templates/{scheduleTemplate}/days/{day}/timeline/rebuild', [\App\Http\Controllers\Api\TimelineController::class, 'rebuild']);

    // Emission control endpoints.
    Route::post('/channels/{channel}/emission/start', [\App\Http\Controllers\Api\EmissionController::class, 'start']);
    Route::post('/channels/{channel}/emission/stop', [\App\Http\Controllers\Api\EmissionController::class, 'stop']);
    Route::get('/channels/{channel}/emission/status', [\App\Http\Controllers\Api\EmissionController::class, 'status']);
// Emission log endpoint (returns last N lines of daemon log).
    Route::get('/channels/{channel}/emission/log', [\App\Http\Controllers\Api\EmissionController::class, 'log']);
});

// Internal emission endpoints (localhost only, no auth required).
Route::post('/internal/channels/{channel}/emission/heartbeat', [\App\Http\Controllers\Api\EmissionPositionController::class, 'heartbeat']);
Route::get('/internal/channels/{channel}/emission/position', [\App\Http\Controllers\Api\EmissionPositionController::class, 'position']);
Route::get('/internal/channels/{channel}/emission/timeline', [\App\Http\Controllers\Api\EmissionPositionController::class, 'timeline']);
Route::get('/internal/channels/{channel}/emission/virtual-screen', [\App\Http\Controllers\Api\EmissionPositionController::class, 'virtualScreen']);
Route::get('/internal/channels/{channel}/emission/channel', [\App\Http\Controllers\Api\EmissionPositionController::class, 'channelInfo']);

// Internal restream endpoints (localhost only, no auth required).
Route::post('/internal/restream/{target}/heartbeat', [\App\Http\Controllers\Api\RestreamHeartbeatController::class, 'heartbeat']);
Route::get('/internal/restream/{target}/log', [\App\Http\Controllers\Api\RestreamHeartbeatController::class, 'log']);
