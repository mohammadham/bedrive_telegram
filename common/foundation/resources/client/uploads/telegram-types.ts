/**
 * Telegram URL Upload Types
 * 
 * TypeScript interfaces and types for Telegram URL upload feature
 */

export interface UrlValidation {
  valid: boolean;
  content_type: string;
  content_length: number;
  content_length_formatted: string;
  can_upload: boolean;
  upload_method: 'bot' | 'user' | null;
  reason: string;
}

export interface UploadOptions {
  name?: string;
  caption?: string;
  uploadId?: string;
}

export interface FileResult {
  id: number;
  name: string;
  size: number;
  mime: string;
  path: string;
  url?: string;
}

export interface TelegramInfo {
  upload_method: 'bot' | 'user';
  message_id: number;
  file_id?: string;
  channel_id?: string;
}

export interface UploadResult {
  message: string;
  file: FileResult;
  telegram: TelegramInfo;
}

export interface BulkUrlResult {
  url: string;
  success: boolean;
  file_entry_id?: number;
  error?: string;
  message?: string;
}

export interface BulkUploadResult {
  message: string;
  total: number;
  successful: number;
  failed: number;
  results: BulkUrlResult[];
}

export interface BulkUploadStatus {
  status: 'processing' | 'completed' | 'failed' | 'not_found';
  total?: number;
  successful?: number;
  failed?: number;
  results?: BulkUrlResult[];
  error?: string;
}

export interface UploadProgress {
  status: 'initializing' | 'downloading' | 'uploading' | 'completed' | 'failed';
  progress: number;
  total_size: number;
  downloaded: number;
  uploaded: number;
  speed: number;
  eta: number | null;
  started_at: string;
  updated_at: string;
  completed_at?: string;
  failed_at?: string;
  error?: string;
  result?: UploadResult;
}

export type UploadMode = 'single' | 'bulk';

export interface TelegramUrlUploadDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess?: (result: UploadResult | BulkUploadResult) => void;
}

export interface SingleUrlFormProps {
  url: string;
  setUrl: (url: string) => void;
  name: string;
  setName: (name: string) => void;
  caption: string;
  setCaption: (caption: string) => void;
  validation: UrlValidation | null;
  validating: boolean;
  onValidate: () => void;
}

export interface BulkUrlsFormProps {
  urls: string[];
  setUrls: (urls: string[]) => void;
  caption: string;
  setCaption: (caption: string) => void;
}

export interface TelegramUrlUploadButtonProps {
  onSuccess?: (result: UploadResult | BulkUploadResult) => void;
}
