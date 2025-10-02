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
  urls: string[];
  parent_id?: number | null;
}

export interface TelegramUrlUploadResponse {
  session_id?: string; // Phase 8.2: session_id برای progress tracking
  file_entry: {
    id: number;
    name: string;
    file_size: number;
    mime: string;
    url: string;
  };
  metadata: {
    telegram_file_id: string;
    message_id: number;
    upload_method: 'bot' | 'user';
    upload_status: 'completed' | 'failed';
  };
}

export interface TelegramBulkUrlUploadResponse {
  success: Array<{
    url: string;
    file_entry: TelegramUrlUploadResponse['file_entry'];
  }>;
  failed: Array<{
    url: string;
    error: string;
  }>;
  summary: {
    total: number;
    successful: number;
    failed: number;
  };
}

export interface TelegramUrlPreview {
  url: string;
  filename: string | null;
  size: number | null;
  mime_type: string | null;
  is_accessible: boolean;
  error?: string;
}

export interface TelegramUrlValidationResult {
  isValid: boolean;
  error?: string;
}

export type TelegramUploadTab = 'single' | 'bulk';