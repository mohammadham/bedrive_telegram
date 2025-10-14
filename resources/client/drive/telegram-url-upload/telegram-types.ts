/**
 * Phase 8.1: Telegram URL Upload - TypeScript Types
 * تعاریف تایپ‌های TypeScript برای آپلود از URL
 */

export interface TelegramUrlUploadRequest {
  url: string;
  name?: string;
  parent_id?: number | null;
}

export interface TelegramBulkUrlUploadRequest {
  urls: Array<{url: string; filename: string}>;
  parent_id?: number | null;
}

export interface TelegramUrlUploadResponse {
  success: boolean;
  message?: string;
  data?: {
    session_id: string;
    file_entry?: {
      id: number;
      name: string;
      file_size: number;
      mime: string;
      url: string;
    };
    metadata?: {
      telegram_file_id: string;
      message_id: number;
      upload_method: 'bot' | 'user';
      upload_status: 'completed' | 'failed';
    };
  };
}

export interface TelegramBulkUrlUploadResponse {
  success: boolean;
  message?: string;
  data?: {
    results: Array<{
      success: boolean;
      url: string;
      session_id?: string;
      error?: string;
    }>;
    summary: {
      total: number;
      successful: number;
      failed: number;
    };
  };
}

export interface TelegramUrlPreview {
  url: string;
  filename: string | null;
  size: number | null;
  mime_type: string | null;
  is_accessible: boolean;
  upload_method?: 'bot' | 'user';
  error?: string;
}

export interface TelegramUrlValidationResult {
  isValid: boolean;
  error?: string;
}

export type TelegramUploadTab = 'single' | 'bulk';
