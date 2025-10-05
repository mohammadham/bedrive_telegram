<?php

namespace App\Http\Controllers;

use Common\Files\Telegram\TelegramUploadProgressService;
use Common\Core\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Phase 8.2: Telegram Upload Progress Controller
 * 
 * API endpoints برای tracking progress آپلود
 */
class TelegramUploadProgressController extends BaseController
{
    protected TelegramUploadProgressService $progressService;

    public function __construct(TelegramUploadProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * دریافت progress یک session خاص
     *
     * GET /api/v1/telegram/upload-progress/{sessionId}
     */
    public function show(string $sessionId): JsonResponse
    {
        $progress = $this->progressService->getProgress($sessionId);

        if (!$progress) {
            return $this->error('Progress session not found', 404);
        }

        // Check authorization - user can only see their own progress
        if ($progress->user_id !== auth()->id()) {
            return $this->error('Unauthorized', 403);
        }

        return $this->success([
            'progress' => $progress,
        ]);
    }

    /**
     * دریافت لیست progress برای user فعلی
     *
     * GET /api/v1/telegram/upload-progress
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:50',
            'active_only' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $limit = $request->input('limit', 10);
        $activeOnly = $request->input('active_only', false);

        if ($activeOnly) {
            $progressList = $this->progressService->getActiveProgress(auth()->id());
        } else {
            $progressList = $this->progressService->getUserProgress(auth()->id(), $limit);
        }

        return $this->success([
            'progress' => $progressList,
            'count' => count($progressList),
        ]);
    }

    /**
     * لغو آپلود
     *
     * POST /api/v1/telegram/upload-progress/{sessionId}/cancel
     */
    public function cancel(string $sessionId): JsonResponse
    {
        $progress = $this->progressService->getProgress($sessionId);

        if (!$progress) {
            return $this->error('Progress session not found', 404);
        }

        // Check authorization
        if ($progress->user_id !== auth()->id()) {
            return $this->error('Unauthorized', 403);
        }

        // Check if can be cancelled
        if (!$progress->isInProgress()) {
            return $this->error('Cannot cancel - upload is not in progress', 400);
        }

        $this->progressService->cancel($sessionId);

        return $this->success([
            'message' => 'Upload cancelled successfully',
        ]);
    }

    /**
     * پاکسازی موارد قدیمی (Admin only)
     *
     * POST /api/v1/admin/telegram/upload-progress/cleanup
     */
    public function cleanup(): JsonResponse
    {
        $deletedCount = $this->progressService->cleanup();

        return $this->success([
            'message' => 'Cleanup completed',
            'deleted_count' => $deletedCount,
        ]);
    }
}
