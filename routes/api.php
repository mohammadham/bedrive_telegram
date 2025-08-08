<?php

use App\Http\Controllers\DriveEntriesController;
use App\Http\Controllers\DuplicateEntriesController;
use App\Http\Controllers\EntrySyncInfoController;
use App\Http\Controllers\FcmTokenController;
use App\Http\Controllers\FolderPathController;
use App\Http\Controllers\FoldersController;
use App\Http\Controllers\MoveFileEntriesController;
use App\Http\Controllers\ShareableLinkPasswordController;
use App\Http\Controllers\ShareableLinksController;
use App\Http\Controllers\SharesController;
use App\Http\Controllers\SpaceUsageController;
use App\Http\Controllers\StarredEntriesController;
use App\Http\Controllers\UserFoldersController;
use App\Http\Controllers\Api\TelegramController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\FileApiController;
use App\Http\Controllers\UrlUploadController;
use App\Http\Controllers\ShortLinkController;
use Illuminate\Support\Facades\Route;

// prettier-ignore
Route::group(['prefix' => 'v1'], function() {
  Route::group(['middleware' => ['optionalAuth:sanctum', 'verified', 'verifyApiAccess']], function () {
    // SHARING
    Route::post('file-entries/{fileEntry}/share', [
      SharesController::class,
      'addUsers',
    ]);
    Route::post('file-entries/{id}/unshare', [
      SharesController::class,
      'removeUser',
    ]);
    Route::put('file-entries/{fileEntry}/change-permissions', [
      SharesController::class,
      'changePermissions',
    ]);

    // SHAREABLE LINK
    Route::get('file-entries/{id}/shareable-link', [
      ShareableLinksController::class,
      'show',
    ]);
    Route::post('file-entries/{id}/shareable-link', [
      ShareableLinksController::class,
      'store',
    ]);
    Route::put('file-entries/{id}/shareable-link', [
      ShareableLinksController::class,
      'update',
    ]);
    Route::delete('file-entries/{id}/shareable-link', [
      ShareableLinksController::class,
      'destroy',
    ]);
    Route::post('shareable-links/{linkId}/import', [
      SharesController::class,
      'addCurrentUser',
    ]);

    // ENTRIES
    Route::get('drive/file-entries/{fileEntry}/model', [
      DriveEntriesController::class,
      'showModel',
    ]);
    Route::get('drive/file-entries', [
      DriveEntriesController::class,
      'index',
    ]);
    Route::post('file-entries/sync-info', [
      EntrySyncInfoController::class,
      'index',
    ]);
    Route::post('file-entries/move', [
      MoveFileEntriesController::class,
      'move',
    ]);
    Route::post('file-entries/duplicate', [
      DuplicateEntriesController::class,
      'duplicate',
    ]);

    // FOLDERS
    Route::post('folders', [FoldersController::class, 'store']);
    Route::get('users/{userId}/folders', [
      UserFoldersController::class,
      'index',
    ]);
    Route::get('folders/{hash}/path', [
      FolderPathController::class,
      'show',
    ]);

    // Labels
    Route::post('file-entries/star', [
      StarredEntriesController::class,
      'add',
    ]);
    Route::post('file-entries/unstar', [
      StarredEntriesController::class,
      'remove',
    ]);

    //SPACE USAGE
    Route::get('user/space-usage', [SpaceUsageController::class, 'index']);

    // FCM TOKENS
    Route::post('fcm-token', [FcmTokenController::class, 'store']);

    // TELEGRAM MANAGEMENT
    Route::get('telegram/status', [TelegramController::class, 'status']);
    Route::post('telegram/install', [TelegramController::class, 'install']);
    Route::post('telegram/configure-session', [TelegramController::class, 'configureSession']);
    Route::post('telegram/test-upload', [TelegramController::class, 'testUpload']);
    Route::post('telegram/configure-bot', [TelegramController::class, 'configureBot']);

    // USER API MANAGEMENT
    Route::post('user/generate-api-token', [UserApiController::class, 'generateApiToken']);
    Route::delete('user/revoke-api-token', [UserApiController::class, 'revokeApiToken']);
    Route::get('user/api-token-status', [UserApiController::class, 'getApiTokenStatus']);
    Route::get('user/telegram-settings', [UserApiController::class, 'getTelegramSettings']);
    Route::put('user/telegram-settings', [UserApiController::class, 'updateTelegramSettings']);
    Route::get('users/{user}/telegram-settings', [UserApiController::class, 'userGetTelegramSettings']);
    Route::put('users/{user}/telegram-settings', [UserApiController::class, 'userUpdateTelegramSettings']);

    // URL UPLOAD
    Route::post('upload-from-url', [UrlUploadController::class, 'upload']);
    Route::get('upload-from-url/supported-patterns', [UrlUploadController::class, 'getSupportedPatterns']);

    // SHORT LINKS MANAGEMENT
    Route::post('short-links', [ShortLinkController::class, 'create']);
    Route::get('short-links', [ShortLinkController::class, 'index']);
    Route::get('short-links/{id}', [ShortLinkController::class, 'show']);
    Route::put('short-links/{id}', [ShortLinkController::class, 'update']);
    Route::delete('short-links/{id}', [ShortLinkController::class, 'delete']);
    Route::get('short-links/{id}/stats', [ShortLinkController::class, 'stats']);
  });

  // API TOKEN AUTHENTICATED ROUTES
  Route::group(['prefix' => 'v1', 'middleware' => ['api.token']], function() {
    Route::post('files/upload', [FileApiController::class, 'upload'])->name('api.v1.files.upload');
    Route::post('files/upload-from-url', [FileApiController::class, 'uploadFromUrl'])->name('api.v1.files.upload-from-url');
    Route::get('files', [FileApiController::class, 'list'])->name('api.v1.files.list');
    Route::get('files/{id}', [FileApiController::class, 'show'])->name('api.v1.files.show');
    Route::get('files/{id}/download', [FileApiController::class, 'download'])->name('api.v1.files.download');
    Route::delete('files/{id}', [FileApiController::class, 'delete'])->name('api.v1.files.delete');
  });

  //SHAREABLE LINKS PREVIEW (NO AUTH NEEDED)
  Route::get('shareable-links/{hash}', [
    ShareableLinksController::class,
    'show',
  ]);
  Route::post('shareable-links/{linkHash}/check-password', [
    ShareableLinkPasswordController::class,
    'check',
  ]);

  // SHORT LINKS ACCESS (NO AUTH NEEDED)
  Route::get('s/{shortCode}', [ShortLinkController::class, 'access']);
  Route::post('s/{shortCode}/download', [ShortLinkController::class, 'download']);
});
