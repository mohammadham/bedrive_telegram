/**
 * Phase 8.2: Progress Tracking - API Functions
 * Phase 8.4: Added Retry functions
 */

import {apiClient} from '@common/http/query-client';
import {
  TelegramProgressResponse,
  TelegramProgressListResponse,
  TelegramRetryResponse,
  TelegramRetryStatsResponse,
} from './telegram-progress-types';

/**
 * دریافت progress یک session
 */
export function getUploadProgress(
  sessionId: string,
): Promise<TelegramProgressResponse> {
  return apiClient
    .get(`telegram/upload-progress/${sessionId}`)
    .then(response => response.data);
}

/**
 * دریافت لیست progress برای user
 */
export function getUserProgressList(
  activeOnly = false,
  limit = 10,
): Promise<TelegramProgressListResponse> {
  return apiClient
    .get('telegram/upload-progress', {
      params: {active_only: activeOnly, limit},
    })
    .then(response => response.data);
}

/**
 * لغو آپلود
 */
export function cancelUpload(sessionId: string): Promise<{message: string}> {
  return apiClient
    .post(`telegram/upload-progress/${sessionId}/cancel`)
    .then(response => response.data);
}

/**
 * Phase 8.4: Retry یک upload ناموفق
 */
export function retryUpload(
  sessionId: string,
): Promise<TelegramRetryResponse> {
  return apiClient
    .post(`telegram/retry/${sessionId}`)
    .then(response => response.data);
}

/**
 * Phase 8.4: لغو retry
 */
export function cancelRetry(sessionId: string): Promise<{message: string}> {
  return apiClient
    .post(`telegram/retry/${sessionId}/cancel`)
    .then(response => response.data);
}

/**
 * Phase 8.4: دریافت آمار retry
 */
export function getRetryStatistics(): Promise<TelegramRetryStatsResponse> {
  return apiClient
    .get('telegram/retry-stats')
    .then(response => response.data.data);
}

/**
 * فرمت کردن ETA (seconds) به رشته قابل خواندن
 */
export function formatETA(seconds: number | null): string {
  if (seconds === null || seconds <= 0) return '--:--';
  
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = Math.floor(seconds % 60);
  
  if (hours > 0) {
    return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  }
  
  return `${minutes}:${secs.toString().padStart(2, '0')}`;
}

/**
 * دریافت رنگ بر اساس status
 */
export function getStatusColor(
  status: string,
): 'primary' | 'positive' | 'danger' | 'warning' {
  switch (status) {
    case 'completed':
      return 'positive';
    case 'failed':
      return 'danger';
    case 'cancelled':
      return 'warning';
    default:
      return 'primary';
  }
}

/**
 * دریافت متن فارسی status
 */
export function getStatusText(status: string): string {
  const statusMap: {[key: string]: string} = {
    pending: 'در انتظار',
    downloading: 'در حال دانلود',
    uploading: 'در حال آپلود',
    completed: 'تکمیل شده',
    failed: 'خطا',
    cancelled: 'لغو شده',
  };
  
  return statusMap[status] || status;
}