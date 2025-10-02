/**
 * Phase 8.1: Telegram URL Upload - API Functions
 * توابع برای ارتباط با Backend API
 */

import {apiClient} from '@common/http/query-client';
import {
  TelegramUrlUploadRequest,
  TelegramBulkUrlUploadRequest,
  TelegramUrlUploadResponse,
  TelegramBulkUrlUploadResponse,
  TelegramUrlPreview,
} from './telegram-types';

/**
 * آپلود فایل از URL
 */
export function uploadFromUrl(
  data: TelegramUrlUploadRequest,
): Promise<TelegramUrlUploadResponse> {
  return apiClient
    .post('telegram/upload-url', data)
    .then(response => response.data);
}

/**
 * آپلود چند فایل از URL (Bulk)
 */
export function uploadBulkFromUrls(
  data: TelegramBulkUrlUploadRequest,
): Promise<TelegramBulkUrlUploadResponse> {
  return apiClient
    .post('telegram/upload-bulk-urls', data)
    .then(response => response.data);
}

/**
 * Preview اطلاعات URL قبل از آپلود
 */
export function previewUrl(url: string): Promise<TelegramUrlPreview> {
  return apiClient
    .post('telegram/validate-url', {url})
    .then(response => response.data);
}

/**
 * اعتبارسنجی URL
 */
export function validateUrl(url: string): {
  isValid: boolean;
  error?: string;
} {
  try {
    const urlObj = new URL(url);
    
    // چک پروتکل
    if (!['http:', 'https:'].includes(urlObj.protocol)) {
      return {
        isValid: false,
        error: 'فقط پروتکل HTTP و HTTPS مجاز است',
      };
    }

    // چک طول URL
    if (url.length > 2048) {
      return {
        isValid: false,
        error: 'URL خیلی طولانی است (حداکثر 2048 کاراکتر)',
      };
    }

    return {isValid: true};
  } catch (e) {
    return {
      isValid: false,
      error: 'فرمت URL معتبر نیست',
    };
  }
}

/**
 * تبدیل حجم byte به فرمت قابل خواندن
 */
export function formatFileSize(bytes: number | null): string {
  if (bytes === null) return 'نامشخص';
  if (bytes === 0) return '0 B';

  const units = ['B', 'KB', 'MB', 'GB'];
  const k = 1024;
  const i = Math.floor(Math.log(bytes) / Math.log(k));

  return `${(bytes / Math.pow(k, i)).toFixed(2)} ${units[i]}`;
}

/**
 * استخراج نام فایل از URL
 */
export function extractFilenameFromUrl(url: string): string {
  try {
    const urlObj = new URL(url);
    const pathname = urlObj.pathname;
    const segments = pathname.split('/');
    const lastSegment = segments[segments.length - 1];
    
    if (lastSegment && lastSegment.includes('.')) {
      return decodeURIComponent(lastSegment);
    }
    
    return `file_${Date.now()}`;
  } catch (e) {
    return `file_${Date.now()}`;
  }
}