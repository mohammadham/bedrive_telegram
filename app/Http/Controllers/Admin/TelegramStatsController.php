<?php

namespace App\Http\Controllers\Admin;

use Common\Core\BaseController;
use Common\Files\Telegram\TelegramMetadataHelper;
use Common\Settings\Setting;
use Illuminate\Http\JsonResponse;

class TelegramStatsController extends BaseController
{
    /**
     * Get Telegram storage statistics
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $this->authorize('index', Setting::class);

        try {
            $stats = TelegramMetadataHelper::getUploadStatistics();

            return $this->success($stats);
        } catch (\Exception $e) {
            return $this->error('Failed to fetch Telegram statistics: ' . $e->getMessage(), 500);
        }
    }
}
