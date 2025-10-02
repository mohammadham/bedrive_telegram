/**
 * Phase 8.2: Progress Tracking - TypeScript Types
 * Phase 8.4: Added Retry fields
 */

export interface TelegramUploadProgressData {
  session_id: string;
  url: string;
  filename: string;
  total_size: number | null;
  formatted_size: string;
  
  // Download
  downloaded_bytes: number;
  download_speed: number | null;
  download_eta: number | null;
  download_percentage: number;
  formatted_download_speed: string;
  
  // Upload
  uploaded_bytes: number;
  upload_speed: number | null;
  upload_eta: number | null;
  upload_percentage: number;
  formatted_upload_speed: string;
  
  // Overall
  overall_percentage: number;
  status: 'pending' | 'downloading' | 'uploading' | 'completed' | 'failed' | 'cancelled';
  error_message: string | null;
  
  // Phase 8.4: Retry fields
  retry_count: number;
  max_retries: number;
  is_retryable: boolean;
  retry_info: string;
  next_retry_at: string | null;
  
  // Timestamps
  started_at: string | null;
  completed_at: string | null;
  created_at: string;
}

export interface TelegramProgressResponse {
  progress: TelegramUploadProgressData;
}

export interface TelegramProgressListResponse {
  progress: TelegramUploadProgressData[];
  count: number;
}

// Phase 8.4: Retry Types
export interface TelegramRetryResponse {
  success: boolean;
  message: string;
  next_retry_at?: string;
  retry_count?: number;
}

export interface TelegramRetryStatsResponse {
  pending_retries: number;
  total_retries: number;
  non_retryable: number;
  max_retries_reached: number;
}