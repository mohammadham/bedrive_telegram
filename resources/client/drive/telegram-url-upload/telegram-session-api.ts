/**
 * Phase 8.3: Resume Upload - API Functions
 */

import {apiClient} from '@common/http/query-client';
import {
  TelegramSessionResponse,
  TelegramSessionListResponse,
  TelegramSessionStatsResponse,
  StartChunkedUploadRequest,
  ResumeSessionResponse,
} from './telegram-session-types';

/**
 * شروع chunked upload جدید
 */
export function startChunkedUpload(
  data: StartChunkedUploadRequest,
): Promise<TelegramSessionResponse> {
  return apiClient
    .post('telegram/chunked-upload', data)
    .then(response => response.data);
}

/**
 * Resume یک session
 */
export function resumeSession(
  sessionId: string,
): Promise<ResumeSessionResponse> {
  return apiClient
    .post(`telegram/upload-session/${sessionId}/resume`)
    .then(response => response.data);
}

/**
 * Pause یک session
 */
export function pauseSession(
  sessionId: string,
): Promise<{message: string}> {
  return apiClient
    .post(`telegram/upload-session/${sessionId}/pause`)
    .then(response => response.data);
}

/**
 * Cancel یک session
 */
export function cancelSession(
  sessionId: string,
): Promise<{message: string}> {
  return apiClient
    .post(`telegram/upload-session/${sessionId}/cancel`)
    .then(response => response.data);
}

/**
 * دریافت اطلاعات یک session
 */
export function getSession(
  sessionId: string,
): Promise<TelegramSessionResponse> {
  return apiClient
    .get(`telegram/upload-session/${sessionId}`)
    .then(response => response.data);
}

/**
 * دریافت لیست sessions
 */
export function getUserSessions(
  resumableOnly = false,
): Promise<TelegramSessionListResponse> {
  return apiClient
    .get('telegram/upload-sessions', {
      params: {resumable_only: resumableOnly},
    })
    .then(response => response.data);
}

/**
 * دریافت آمار sessions
 */
export function getSessionStatistics(): Promise<TelegramSessionStatsResponse> {
  return apiClient
    .get('telegram/upload-sessions/statistics')
    .then(response => response.data.data);
}

/**
 * دریافت رنگ بر اساس session status
 */
export function getSessionStatusColor(
  status: string,
): 'primary' | 'positive' | 'danger' | 'warning' | 'chip' {
  switch (status) {
    case 'completed':
      return 'positive';
    case 'failed':
    case 'cancelled':
      return 'danger';
    case 'paused':
      return 'warning';
    case 'downloading':
    case 'uploading':
      return 'primary';
    default:
      return 'chip';
  }
}

/**
 * دریافت متن فارسی session status
 */
export function getSessionStatusText(status: string): string {
  const statusMap: {[key: string]: string} = {
    initialized: 'آماده‌سازی',
    downloading: 'در حال دانلود',
    paused: 'متوقف شده',
    downloaded: 'دانلود شده',
    uploading: 'در حال آپلود',
    completed: 'تکمیل شده',
    failed: 'خطا',
    cancelled: 'لغو شده',
  };
  
  return statusMap[status] || status;
}

/**
 * محاسبه زمان تخمینی باقیمانده برای download
 */
export function calculateETA(
  totalSize: number,
  downloadedBytes: number,
  speed: number, // bytes per second
): number | null {
  if (speed <= 0 || totalSize <= downloadedBytes) {
    return null;
  }
  
  const remainingBytes = totalSize - downloadedBytes;
  return Math.ceil(remainingBytes / speed);
}

/**
 * فرمت کردن chunk progress
 */
export function formatChunkProgress(
  completedChunks: number,
  totalChunks: number,
): string {
  return `${completedChunks} / ${totalChunks} chunks`;
}
