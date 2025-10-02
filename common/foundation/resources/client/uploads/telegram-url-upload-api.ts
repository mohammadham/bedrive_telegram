/**
 * Telegram URL Upload API Functions
 * 
 * API client functions for Telegram URL upload feature
 */

import {apiClient} from '@common/http/query-client';
import type {
  UrlValidation,
  UploadOptions,
  UploadResult,
  BulkUploadResult,
  BulkUploadStatus,
} from './telegram-types';

/**
 * Validate URL before upload
 */
export async function validateUrl(url: string): Promise<UrlValidation> {
  const response = await apiClient.post('telegram/validate-url', {url});
  return response.data;
}

/**
 * Upload single file from URL
 */
export async function uploadFromUrl(
  url: string,
  options?: UploadOptions
): Promise<UploadResult> {
  const response = await apiClient.post('telegram/upload-url', {
    url,
    name: options?.name,
    caption: options?.caption,
  });
  return response.data;
}

/**
 * Upload multiple files from URLs
 */
export async function uploadBulkUrls(
  urls: string[],
  options?: UploadOptions
): Promise<BulkUploadResult> {
  const response = await apiClient.post('telegram/upload-bulk-urls', {
    urls,
    caption: options?.caption,
  });
  return response.data;
}

/**
 * Check bulk upload status
 */
export async function checkBulkUploadStatus(
  jobId: string
): Promise<BulkUploadStatus> {
  const response = await apiClient.get(`telegram/bulk-upload-status/${jobId}`);
  return response.data;
}

/**
 * Parse URLs from text (one URL per line)
 */
export function parseUrlsFromText(text: string): string[] {
  return text
    .split('\n')
    .map(line => line.trim())
    .filter(line => line.length > 0)
    .filter(line => {
      try {
        new URL(line);
        return true;
      } catch {
        return false;
      }
    });
}

/**
 * Format file size to human readable
 */
export function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B';
  
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
}

/**
 * Validate URL format
 */
export function isValidUrl(url: string): boolean {
  try {
    new URL(url);
    return true;
  } catch {
    return false;
  }
}
