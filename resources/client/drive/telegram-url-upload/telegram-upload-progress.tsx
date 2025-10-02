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
  const {progress, isLoading, isCompleted, isFailed} = useUploadProgress({
    sessionId,
  });

  // Callbacks
  React.useEffect(() => {
    if (isCompleted && onComplete) {
      onComplete();
    }
  }, [isCompleted, onComplete]);

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

  // Full mode
  return (
    <div className="rounded border border-divider bg-alt p-16 space-y-12">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div className="flex-1 min-w-0">
          <div className="truncate font-medium text-sm">{progress.filename}</div>
          <div className="mt-4 flex items-center gap-8 text-xs text-muted">
            <StatusBadge status={progress.status} />
            {progress.formatted_size !== 'N/A' && (
              <span>{progress.formatted_size}</span>
            )}
          </div>
        </div>

        {/* Cancel button */}
        {['pending', 'downloading', 'uploading'].includes(progress.status) && (
          <Button
            size="xs"
            variant="outline"
            color="danger"
            startIcon={<CancelIcon />}
            onClick={handleCancel}
          >
            <Trans message="لغو" />
          </Button>
        )}
      </div>

      {/* Progress Bar */}
      <div>
        <ProgressBar
          value={progress.overall_percentage}
          size="md"
          showValueLabel
        />
      </div>

      {/* Details */}
      {showDetails && (
        <div className="space-y-8 text-xs">
          {/* Download Phase */}
          {(progress.status === 'downloading' ||
            progress.download_percentage > 0) && (
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-8">
                <CloudDownloadIcon size="sm" className="text-primary" />
                <span>
                  <Trans message="دانلود" />:{' '}
                  {Math.round(progress.download_percentage)}%
                </span>
              </div>
              <div className="flex items-center gap-12 text-muted">
                {progress.formatted_download_speed !== 'N/A' && (
                  <span>{progress.formatted_download_speed}</span>
                )}
                {progress.download_eta && (
                  <span>
                    ETA: {formatETA(progress.download_eta)}
                  </span>
                )}
              </div>
            </div>
          )}

          {/* Upload Phase */}
          {(progress.status === 'uploading' ||
            progress.upload_percentage > 0) && (
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-8">
                <CloudUploadIcon size="sm" className="text-primary" />
                <span>
                  <Trans message="آپلود به تلگرام" />:{' '}
                  {Math.round(progress.upload_percentage)}%
                </span>
              </div>
              <div className="flex items-center gap-12 text-muted">
                {progress.formatted_upload_speed !== 'N/A' && (
                  <span>{progress.formatted_upload_speed}</span>
                )}
                {progress.upload_eta && (
                  <span>
                    ETA: {formatETA(progress.upload_eta)}
                  </span>
                )}
              </div>
            </div>
          )}

          {/* Error Message */}
          {progress.error_message && (
            <div className="rounded bg-danger/10 p-8 text-danger flex items-start gap-8">
              <ErrorIcon size="sm" className="mt-2 flex-shrink-0" />
              <div className="flex-1">
                <span>{progress.error_message}</span>
                
                {/* Phase 8.4: Retry info and button */}
                {progress.retry_count > 0 && (
                  <div className="mt-4 text-xs text-muted">
                    {progress.retry_info}
                  </div>
                )}
                {progress.is_retryable && progress.status === 'failed' && (
                  <div className="mt-8">
                    <Button
                      size="xs"
                      variant="outline"
                      color="primary"
                      startIcon={<RefreshIcon />}
                      onClick={handleRetry}
                      disabled={progress.retry_count >= progress.max_retries}
                    >
                      <Trans message="تلاش مجدد" />
                      {progress.retry_count > 0 && ` (${progress.retry_count}/${progress.max_retries})`}
                    </Button>
                  </div>
                )}
              </div>
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