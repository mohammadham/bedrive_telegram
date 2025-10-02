<?php

use App\Models\FileEntry;
use Common\Files\Telegram\TelegramStorageService;
use Common\Files\Telegram\TelegramUrlGenerator;
use Illuminate\Support\Facades\Route;

/**
 * Telegram Storage Routes
 * 
 * این routeها برای download, stream و share فایل‌های تلگرام استفاده می‌شوند
 * 
 * برای فعال‌سازی، این فایل را در RouteServiceProvider include کنید:
 * require base_path('common/foundation/src/Files/Telegram/routes.php');
 */

// Download route
Route::get('/telegram/download/{file}', function ($fileId) {
    $fileEntry = FileEntry::findOrFail($fileId);
    
    // Check permissions (customize based on your auth logic)
    // if ($fileEntry->user_id !== auth()->id()) {
    //     abort(403, 'Unauthorized');
    // }

    try {
        $service = new TelegramStorageService();
        $contents = $service->getFileContents($fileEntry);

        return response($contents)
            ->header('Content-Type', $fileEntry->mime ?? 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="' . $fileEntry->name . '"')
            ->header('Content-Length', strlen($contents));

    } catch (\Exception $e) {
        abort(500, 'Failed to download file: ' . $e->getMessage());
    }
})->name('telegram.download')->middleware(['web']);

// Stream route (for video/audio)
Route::get('/telegram/stream/{file}', function ($fileId) {
    $fileEntry = FileEntry::findOrFail($fileId);
    
    // Check permissions
    // if ($fileEntry->user_id !== auth()->id()) {
    //     abort(403, 'Unauthorized');
    // }

    try {
        $service = new TelegramStorageService();
        $contents = $service->getFileContents($fileEntry);

        return response($contents)
            ->header('Content-Type', $fileEntry->mime ?? 'application/octet-stream')
            ->header('Accept-Ranges', 'bytes')
            ->header('Content-Length', strlen($contents));

    } catch (\Exception $e) {
        abort(500, 'Failed to stream file: ' . $e->getMessage());
    }
})->name('telegram.stream')->middleware(['web']);

// Thumbnail route (for images/videos)
Route::get('/telegram/thumbnail/{file}', function ($fileId) {
    $fileEntry = FileEntry::findOrFail($fileId);
    $size = request('size', 'medium');

    // Size mappings
    $dimensions = [
        'small' => 150,
        'medium' => 320,
        'large' => 640,
    ];

    $maxDimension = $dimensions[$size] ?? 320;

    try {
        $service = new TelegramStorageService();
        $contents = $service->getFileContents($fileEntry);

        // Create thumbnail (requires intervention/image or similar)
        // For now, return original image
        // In production, implement actual thumbnail generation
        
        return response($contents)
            ->header('Content-Type', $fileEntry->mime ?? 'image/jpeg')
            ->header('Cache-Control', 'public, max-age=31536000');

    } catch (\Exception $e) {
        abort(500, 'Failed to generate thumbnail: ' . $e->getMessage());
    }
})->name('telegram.thumbnail')->middleware(['web']);

// Public share link
Route::get('/telegram/share/{token}', function ($token) {
    $metadata = TelegramUrlGenerator::verifyShareToken($token);

    if (!$metadata) {
        abort(404, 'Share link not found or expired');
    }

    $fileEntry = FileEntry::find($metadata->file_entry_id);
    
    if (!$fileEntry) {
        abort(404, 'File not found');
    }

    try {
        $service = new TelegramStorageService();
        $contents = $service->getFileContents($fileEntry);

        return response($contents)
            ->header('Content-Type', $fileEntry->mime ?? 'application/octet-stream')
            ->header('Content-Disposition', 'inline; filename="' . $fileEntry->name . '"');

    } catch (\Exception $e) {
        abort(500, 'Failed to access shared file: ' . $e->getMessage());
    }
})->name('telegram.share')->middleware(['web']);

// Admin preview (requires auth:admin or similar)
Route::get('/admin/telegram/preview/{file}', function ($fileId) {
    $fileEntry = FileEntry::findOrFail($fileId);

    try {
        $service = new TelegramStorageService();
        $metadata = $fileEntry->telegramMetadata;

        return view('admin.telegram.preview', [
            'file' => $fileEntry,
            'metadata' => $metadata,
            'downloadUrl' => TelegramUrlGenerator::temporary($fileEntry, 60),
        ]);

    } catch (\Exception $e) {
        abort(500, 'Failed to preview file: ' . $e->getMessage());
    }
})->name('admin.telegram.preview')->middleware(['web']);
