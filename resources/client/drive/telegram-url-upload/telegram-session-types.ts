/**
 * Phase 8.3: Resume Upload - TypeScript Types
 */

export interface TelegramUploadSessionData {
  session_id: string;
  url: string;
  filename: string;
  total_size: number | null;
  temp_path: string | null;
  
  // Chunking
  chunk_size: number;
  total_chunks: number;
  completed_chunks: number;
  chunks_map: boolean[];
  
  // Progress
  downloaded_bytes: number;
  uploaded_bytes: number;
  download_percentage: number;
  upload_percentage: number;
  
  // Status
  status: 'initialized' | 'downloading' | 'paused' | 'downloaded' | 'uploading' | 'completed' | 'failed' | 'cancelled';
  error_message: string | null;
  is_resumable: boolean;
  
  // Telegram
  telegram_file_id: string | null;
  
  // Timestamps
  last_activity_at: string | null;
  paused_at: string | null;
  resumed_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface TelegramSessionResponse {
  session: TelegramUploadSessionData;
}

export interface TelegramSessionListResponse {
  sessions: TelegramUploadSessionData[];
  count: number;
}

export interface TelegramSessionStatsResponse {
  total_sessions: number;
  resumable: number;
  completed: number;
  paused: number;
  failed: number;
}

export interface StartChunkedUploadRequest {
  url: string;
  filename?: string;
  chunk_size?: number; // 1MB - 10MB
}

export interface ResumeSessionResponse {
  success: boolean;
  message: string;
  session?: TelegramUploadSessionData;
}
