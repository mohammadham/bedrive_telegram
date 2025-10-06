<?php

namespace App\Http\Controllers;
use Common\Core\BaseController;
use Common\Files\Telegram\TelegramRetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 8.4: Telegram Retry Controller
 * 
 * API برای مدیریت retry آپلودهای ناموفق
 */
class TelegramRetryController extends BaseController
{
    protected TelegramRetryService $retryService;

    public function __construct()
    {
        $this->middleware('auth');
        $this->retryService = new TelegramRetryService();
    }

    /**
     * Retry یک upload ناموفق
     * 
     * POST /api/v1/telegram/retry/{sessionId}
     */
    public function retry(string $sessionId): JsonResponse
    {
        try {
            $result = $this->retryService->retryUpload($sessionId);

            return response()->json([
                'message' => $result['message'],
                'data' => $result,
            ], $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Retry failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * پردازش retry queue
     * 
     * POST /api/v1/admin/telegram/process-retry-queue
     */
    public function processQueue(Request $request): JsonResponse
    {
        // فقط admin
        $this->authorize('index', \Common\Settings\Setting::class);

        $limit = $request->input('limit', 10);

        $results = $this->retryService->processRetryQueue($limit);

        return response()->json([
            'message' => 'Retry queue processed',
            'data' => $results,
        ]);
    }

    /**
     * دریافت آمار retry
     * 
     * GET /api/v1/telegram/retry-stats
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->retryService->getRetryStatistics();

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * لغو retry
     * 
     * POST /api/v1/telegram/retry/{sessionId}/cancel
     */
    public function cancelRetry(string $sessionId): JsonResponse
    {
        $cancelled = $this->retryService->cancelRetry($sessionId);

        if (!$cancelled) {
            return response()->json([
                'message' => 'Session not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Retry cancelled successfully',
        ]);
    }
}
