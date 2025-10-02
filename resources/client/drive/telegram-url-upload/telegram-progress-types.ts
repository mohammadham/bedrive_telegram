/**
 * Phase 8.2: Progress Tracking - TypeScript Types
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