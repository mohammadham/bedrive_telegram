/**
 * Phase 8.2: Progress Tracking - Progress Component
 * Phase 8.4: Added Retry UI
 * 
 * نمایش progress bar و اطلاعات آپلود
 */

import React from 'react';
import {Trans} from '@ui/i18n/trans';
import {ProgressBar} from '@ui/progress/progress-bar';
import {useUploadProgress} from './use-upload-progress';
import {
  formatETA,
  getStatusColor,
  getStatusText,
  cancelUpload,
  retryUpload,
} from './telegram-progress-api';
import {Button} from '@ui/buttons/button';
import {CancelIcon} from '@ui/icons/material/Cancel';
import {CheckCircleIcon} from '@ui/icons/material/CheckCircle';
import {ErrorIcon} from '@ui/icons/material/Error';
import {CloudDownloadIcon} from '@ui/icons/material/CloudDownload';
import {CloudUploadIcon} from '@ui/icons/material/CloudUpload';
import {RefreshIcon} from '@ui/icons/material/Refresh';
import {Skeleton} from '@ui/skeleton/skeleton';
import {toast} from '@ui/toast/toast';
import {useQueryClient} from '@tanstack/react-query';
import {DriveQueryKeys, invalidateEntryQueries} from '../drive-query-keys';

interface TelegramUploadProgressProps {
  sessionId: string;
  onComplete?: () => void;
  onError?: (error: string) => void;
  showDetails?: boolean;
  compact?: boolean;
}

export function TelegramUploadProgress({
  sessionId,
  onComplete,
  onError,
  showDetails = true,
  compact = false,
}: TelegramUploadProgressProps) {
  const queryClient = useQueryClient();
  const {progress, isLoading, isCompleted, isFailed} = useUploadProgress({
    sessionId,
  });

  // Callbacks
  React.useEffect(() => {
    if (isCompleted) {
      // Refresh file list
      invalidateEntryQueries();
      queryClient.invalidateQueries({
        queryKey: DriveQueryKeys.fetchStorageSummary,
      });
      
      if (onComplete) {
        onComplete();
      }
    }
  }, [isCompleted, onComplete, queryClient]);

  React.useEffect(() => {
    if (isFailed && progress?.error_message && onError) {
      onError(progress.error_message);
    }
  }, [isFailed, progress?.error_message, onError]);

  // Loading state
  if (isLoading || !progress) {
    return (
      <div className="space-y-8">
        <Skeleton variant="rect" size="h-32" />
        {showDetails && (
          <>
            <Skeleton variant="text" />
            <Skeleton variant="text" />
          </>
        )}
      </div>
    );
  }

  const handleCancel = async () => {
    try {
      await cancelUpload(sessionId);
      toast.positive('آپلود لغو شد');
    } catch (error: any) {
      toast.danger(error.message || 'خطا در لغو آپلود');
    }
  };

  // Phase 8.4: Retry handler
  const handleRetry = async () => {
    try {
      const result = await retryUpload(sessionId);
      if (result.success) {
        toast.positive('تلاش مجدد با موفقیت آغاز شد');
      } else {
        toast.danger(result.message);
      }
    } catch (error: any) {
      toast.danger(error.message || 'خطا در تلاش مجدد');
    }
  };

  // Compact mode - فقط progress bar
  if (compact) {
    return (
      <div className="flex items-center gap-12">
        <div className="flex-1">
          <ProgressBar
            value={progress.overall_percentage}
            size="sm"
          />
        </div>
        <span className="text-xs text-muted">
          {Math.round(progress.overall_percentage)}%
        </span>
        {progress.status === 'downloading' && (
          <CloudDownloadIcon size="sm" className="text-primary" />
        )}
        {progress.status === 'uploading' && (
          <CloudUploadIcon size="sm" className="text-primary" />
        )}
      </div>
    );
  }

  // Full mode - بهبود یافته با UI زیباتر
  return (
    <div className="rounded-lg border-2 border-divider bg-gradient-to-br from-paper via-alt to-paper p-4 space-y-3 shadow-md hover:shadow-lg transition-shadow">
      {/* Header با آیکون وضعیت */}
      <div className="flex items-start justify-between gap-3">
        <div className="flex-1 min-w-0 flex items-start gap-3">
          {/* آیکون وضعیت بزرگ */}
          <div className={`
            w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0
            ${progress.status === 'completed' ? 'bg-positive/20' : ''}
            ${progress.status === 'failed' ? 'bg-danger/20' : ''}
            ${['downloading', 'uploading', 'pending'].includes(progress.status) ? 'bg-primary/20 animate-pulse' : ''}
          `}>
            {progress.status === 'completed' && (
              <CheckCircleIcon className="text-positive" size="md" />
            )}
            {progress.status === 'failed' && (
              <ErrorIcon className="text-danger" size="md" />
            )}
            {progress.status === 'downloading' && (
              <CloudDownloadIcon className="text-primary" size="md" />
            )}
            {progress.status === 'uploading' && (
              <CloudUploadIcon className="text-primary" size="md" />
            )}
            {progress.status === 'pending' && (
              <div className="w-5 h-5 border-3 border-primary/30 border-t-primary rounded-full animate-spin" />
            )}
          </div>

          {/* اطلاعات فایل */}
          <div className="flex-1 min-w-0">
            <div className="truncate font-semibold text-sm text-main">
              {progress.filename}
            </div>
            <div className="mt-1 flex items-center gap-2 text-xs">
              <StatusBadge status={progress.status} />
              {progress.formatted_size !== 'N/A' && (
                <span className="text-muted">• {progress.formatted_size}</span>
              )}
            </div>
          </div>
        </div>

        {/* دکمه لغو/تلاش مجدد */}
        <div className="flex-shrink-0">
          {['pending', 'downloading', 'uploading'].includes(progress.status) && (
            <Button
              size="xs"
              variant="flat"
              color="danger"
              startIcon={<CancelIcon />}
              onClick={handleCancel}
              className="hover:bg-danger hover:text-on-primary transition-colors"
            >
              لغو
            </Button>
          )}
          {progress.is_retryable && progress.status === 'failed' && (
            <Button
              size="xs"
              variant="flat"
              color="primary"
              startIcon={<RefreshIcon />}
              onClick={handleRetry}
              disabled={progress.retry_count >= progress.max_retries}
              className="hover:bg-primary hover:text-on-primary transition-colors"
            >
              تلاش ({progress.retry_count || 0}/{progress.max_retries || 3})
            </Button>
          )}
        </div>
      </div>

      {/* Progress Bar بزرگتر و واضح‌تر */}
      <div className="space-y-1.5">
        <div className="flex items-center justify-between text-xs">
          <span className="font-medium text-muted">
            {progress.status === 'downloading' && 'در حال دانلود...'}
            {progress.status === 'uploading' && 'در حال آپلود به تلگرام...'}
            {progress.status === 'pending' && 'در انتظار...'}
            {progress.status === 'completed' && 'تکمیل شد ✓'}
            {progress.status === 'failed' && 'خطا در آپلود'}
          </span>
          <span className="font-bold text-sm text-primary">
            {Math.round(progress.overall_percentage)}%
          </span>
        </div>
        <ProgressBar
          value={progress.overall_percentage}
          size="md"
          className="h-2"
        />
      </div>

      {/* Details - فقط برای وضعیت‌های فعال */}
      {showDetails && ['downloading', 'uploading'].includes(progress.status) && (
        <div className="space-y-2 text-xs bg-paper/50 rounded-md p-3 border border-divider/50">
          {/* Download Phase */}
          {(progress.status === 'downloading' || progress.download_percentage > 0) && (
            <div className="flex items-center justify-between py-1">
              <div className="flex items-center gap-2">
                <CloudDownloadIcon size="sm" className="text-primary" />
                <span className="font-medium">
                  دانلود: {Math.round(progress.download_percentage)}%
                </span>
              </div>
              <div className="flex items-center gap-3 text-muted">
                {progress.formatted_download_speed !== 'N/A' && (
                  <span className="font-mono font-bold text-primary">
                    {progress.formatted_download_speed}
                  </span>
                )}
                {progress.download_eta && (
                  <span className="flex items-center gap-1">
                    ⏱ {formatETA(progress.download_eta)}
                  </span>
                )}
              </div>
            </div>
          )}

          {/* Upload Phase */}
          {(progress.status === 'uploading' || progress.upload_percentage > 0) && (
            <div className="flex items-center justify-between py-1">
              <div className="flex items-center gap-2">
                <CloudUploadIcon size="sm" className="text-primary" />
                <span className="font-medium">
                  آپلود: {Math.round(progress.upload_percentage)}%
                </span>
              </div>
              <div className="flex items-center gap-3 text-muted">
                {progress.formatted_upload_speed !== 'N/A' && (
                  <span className="font-mono font-bold text-primary">
                    {progress.formatted_upload_speed}
                  </span>
                )}
                {progress.upload_eta && (
                  <span className="flex items-center gap-1">
                    ⏱ {formatETA(progress.upload_eta)}
                  </span>
                )}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Error Message با UI بهتر */}
      {progress.error_message && (
        <div className="rounded-lg bg-danger/10 border border-danger/30 p-3 text-danger flex items-start gap-2">
          <ErrorIcon size="sm" className="mt-0.5 flex-shrink-0" />
          <div className="flex-1 min-w-0">
            <div className="text-xs font-medium break-words">
              {progress.error_message}
            </div>
            
            {/* Phase 8.4: Retry info */}
            {progress.retry_count > 0 && (
              <div className="mt-2 text-xs text-danger/70 flex items-center gap-1">
                <RefreshIcon size="xs" />
                {progress.retry_info}
              </div>
            )}
            
            {/* Next retry time */}
            {progress.next_retry_at && (
              <div className="mt-2 text-xs text-muted">
                ⏱ تلاش مجدد در: {new Date(progress.next_retry_at).toLocaleTimeString('fa-IR')}
              </div>
            )}
          </div>
        </div>
      )}
            </div>
          )}

          {/* Phase 8.4: Next retry time */}
          {progress.next_retry_at && progress.status === 'pending' && (
            <div className="rounded bg-warning/10 p-8 text-warning flex items-center gap-8 text-xs">
              <RefreshIcon size="sm" />
              <span>
                <Trans message="تلاش مجدد در" />: {new Date(progress.next_retry_at).toLocaleTimeString('fa-IR')}
              </span>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

/**
 * Badge برای نمایش status
 */
function StatusBadge({status}: {status: string}) {
  const color = getStatusColor(status);
  const text = getStatusText(status);

  const Icon =
    status === 'completed'
      ? CheckCircleIcon
      : status === 'failed'
        ? ErrorIcon
        : status === 'downloading'
          ? CloudDownloadIcon
          : status === 'uploading'
            ? CloudUploadIcon
            : null;

  return (
    <span
      className={`inline-flex items-center gap-4 rounded px-8 py-2 text-xs font-medium ${
        color === 'positive'
          ? 'bg-positive/10 text-positive'
          : color === 'danger'
            ? 'bg-danger/10 text-danger'
            : color === 'warning'
              ? 'bg-warning/10 text-warning'
              : 'bg-primary/10 text-primary'
      }`}
    >
      {Icon && <Icon size="xs" />}
      {text}
    </span>
  );
}