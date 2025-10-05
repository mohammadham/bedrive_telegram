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
use App\Http\Controllers\Admin\TelegramStatsController;
use App\Http\Controllers\Admin\TelegramTestController;
use App\Http\Controllers\UserTelegramSettingsController;
use App\Http\Controllers\TelegramUrlUploadController;
use App\Http\Controllers\TelegramUploadProgressController;
use App\Http\Controllers\TelegramRetryController;
use App\Http\Controllers\TelegramUploadSessionController;
use App\Http\Controllers\TelegramWebhookController;
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

    // TELEGRAM ADMIN
    Route::get('admin/telegram/stats', [TelegramStatsController::class, 'index']);
    
    // TELEGRAM TESTING
    Route::post('admin/telegram/test-bot', [TelegramTestController::class, 'testBot']);
    Route::post('admin/telegram/test-user', [TelegramTestController::class, 'testUser']);
    Route::post('admin/telegram/login-user', [TelegramTestController::class, 'loginUser']);
    Route::post('admin/telegram/verify-code', [TelegramTestController::class, 'verifyCode']);
    Route::post('admin/telegram/complete-2fa', [TelegramTestController::class, 'complete2FA']);
    Route::post('admin/telegram/logout-user', [TelegramTestController::class, 'logoutUser']);
    Route::get('admin/telegram/download-session', [TelegramTestController::class, 'downloadSession']);
    
    // TELEGRAM WEBHOOK MANAGEMENT
    Route::post('admin/telegram/webhook/info', [TelegramTestController::class, 'getWebhookInfo']);
    Route::post('admin/telegram/webhook/set', [TelegramTestController::class, 'setWebhook']);
    Route::post('admin/telegram/webhook/delete', [TelegramTestController::class, 'deleteWebhook']);

// TELEGRAM WEBHOOK HANDLER (Public - for Telegram to call)
Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle']);

    // USER TELEGRAM SETTINGS
    Route::get('user/telegram/settings', [UserTelegramSettingsController::class, 'index']);
    Route::put('user/telegram/settings', [UserTelegramSettingsController::class, 'update']);
    Route::post('user/telegram/upload/{fileId}', [UserTelegramSettingsController::class, 'uploadToTelegram']);
    Route::post('user/telegram/forward/{fileId}', [UserTelegramSettingsController::class, 'forwardFile']);
    
    // TELEGRAM URL UPLOAD
    Route::post('telegram/upload-url', [TelegramUrlUploadController::class, 'uploadSingle']);
    Route::post('telegram/upload-bulk-urls', [TelegramUrlUploadController::class, 'uploadBulk']);
    Route::post('telegram/validate-url', [TelegramUrlUploadController::class, 'validateUrl']);
    Route::get('telegram/bulk-upload-status/{jobId}', function ($jobId) {
        $results = cache()->get("telegram_bulk_upload:{$jobId}");
        return response()->json($results ?? ['status' => 'not_found']);
    });
    
    // TELEGRAM UPLOAD PROGRESS (Phase 8.2)
    Route::get('telegram/upload-progress', [TelegramUploadProgressController::class, 'index']);
    Route::get('telegram/upload-progress/{sessionId}', [TelegramUploadProgressController::class, 'show']);
    Route::post('telegram/upload-progress/{sessionId}/cancel', [TelegramUploadProgressController::class, 'cancel']);
    
    // TELEGRAM AUTO-RETRY (Phase 8.4)
    Route::post('telegram/retry/{sessionId}', [TelegramRetryController::class, 'retry']);
    Route::get('telegram/retry-stats', [TelegramRetryController::class, 'statistics']);
    Route::post('telegram/retry/{sessionId}/cancel', [TelegramRetryController::class, 'cancelRetry']);
    
    // TELEGRAM RESUMABLE UPLOAD (Phase 8.3)
    Route::post('telegram/chunked-upload', [TelegramUploadSessionController::class, 'start']);
    Route::get('telegram/upload-sessions', [TelegramUploadSessionController::class, 'index']);
    Route::get('telegram/upload-sessions/statistics', [TelegramUploadSessionController::class, 'statistics']);
    Route::get('telegram/upload-session/{sessionId}', [TelegramUploadSessionController::class, 'show']);
    Route::post('telegram/upload-session/{sessionId}/resume', [TelegramUploadSessionController::class, 'resume']);
    Route::post('telegram/upload-session/{sessionId}/pause', [TelegramUploadSessionController::class, 'pause']);
    Route::post('telegram/upload-session/{sessionId}/cancel', [TelegramUploadSessionController::class, 'cancel']);
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
});
