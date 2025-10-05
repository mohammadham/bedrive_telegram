<?php

namespace App\Http\Controllers;

use Common\Files\Telegram\TelegramChunkedUploadService;
use App\Models\TelegramUploadSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Common\Core\BaseController;
/**
 * Phase 8.3: Telegram Upload Session Controller
 * 
 * API برای مدیریت resumable upload sessions
 */
class TelegramUploadSessionController extends BaseController
{
    protected TelegramChunkedUploadService $chunkedService;

    public function __construct()
    {
        $this->middleware('auth');
        $this->chunkedService = new TelegramChunkedUploadService();
    }

    /**
     * شروع یک chunked upload جدید
     * 
     * POST /api/v1/telegram/chunked-upload
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
            'filename' => 'nullable|string|max:255',
            'chunk_size' => 'nullable|integer|min:1048576|max:10485760', // 1MB - 10MB
        ]);

        try {
            $session = $this->chunkedService->startChunkedUpload(
                $request->input('url'),
                [
                    'user_id' => auth()->id(),
                    'name' => $request->input('filename'),
                ],
                $request->input('chunk_size', 5242880) // 5MB default
            );

            return response()->json([
                'message' => 'Chunked upload started',
                'session' => $session,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to start chunked upload',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resume یک session
     * 
     * POST /api/v1/telegram/upload-session/{sessionId}/resume
     */
    public function resume(string $sessionId): JsonResponse
    {
        try {
            $result = $this->chunkedService->resumeSession($sessionId);

            return response()->json([
                'message' => $result['message'],
                'data' => $result,
            ], $result['success'] ? 200 : 400);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to resume session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pause یک session
     * 
     * POST /api/v1/telegram/upload-session/{sessionId}/pause
     */
    public function pause(string $sessionId): JsonResponse
    {
        $paused = $this->chunkedService->pauseSession($sessionId);

        if (!$paused) {
            return response()->json([
                'message' => 'Session not found or cannot be paused',
            ], 404);
        }

        return response()->json([
            'message' => 'Session paused successfully',
        ]);
    }

    /**
     * Cancel یک session
     * 
     * POST /api/v1/telegram/upload-session/{sessionId}/cancel
     */
    public function cancel(string $sessionId): JsonResponse
    {
        $cancelled = $this->chunkedService->cancelSession($sessionId);

        if (!$cancelled) {
            return response()->json([
                'message' => 'Session not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Session cancelled successfully',
        ]);
    }

    /**
     * دریافت اطلاعات یک session
     * 
     * GET /api/v1/telegram/upload-session/{sessionId}
     */
    public function show(string $sessionId): JsonResponse
    {
        $session = TelegramUploadSession::where('session_id', $sessionId)
            ->where('user_id', auth()->id())
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Session not found',
            ], 404);
        }

        return response()->json([
            'session' => $session,
        ]);
    }

    /**
     * دریافت لیست sessions برای user
     * 
     * GET /api/v1/telegram/upload-sessions
     */
    public function index(Request $request): JsonResponse
    {
        $resumableOnly = $request->boolean('resumable_only', false);

        $sessions = $this->chunkedService->getUserSessions(
            auth()->id(),
            $resumableOnly
        );

        return response()->json([
            'sessions' => $sessions,
            'count' => count($sessions),
        ]);
    }

    /**
     * دریافت آمار sessions
     * 
     * GET /api/v1/telegram/upload-sessions/statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->chunkedService->getSessionStatistics(auth()->id());

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * پاکسازی sessions قدیمی (admin)
     * 
     * POST /api/v1/admin/telegram/cleanup-sessions
     */
    public function cleanup(): JsonResponse
    {
        // فقط admin
        $this->authorize('index', \Common\Settings\Setting::class);

        $count = TelegramUploadSession::cleanupOld();

        return response()->json([
            'message' => 'Cleanup completed',
            'deleted_count' => $count,
        ]);
    }
}
