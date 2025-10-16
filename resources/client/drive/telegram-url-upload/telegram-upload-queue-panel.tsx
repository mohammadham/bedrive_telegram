/**
 * Phase 8.2: Telegram Upload Queue Panel (بهبود یافته)
 * 
 * نمایش لیست فایل‌های در حال آپلود به تلگرام
 * با Progress bar، Speed، ETA و وضعیت دقیق
 */

import './telegram-upload-queue.css';
import {useQuery} from '@tanstack/react-query';
import {getUserProgressList} from './telegram-progress-api';
import {TelegramUploadProgress} from './telegram-upload-progress';
import {TelegramUploadProgressData} from './telegram-progress-types';
import {IconButton} from '@ui/buttons/icon-button';
import {CloseIcon} from '@ui/icons/material/Close';
import {MinimizeIcon} from '@ui/icons/material/Minimize';
import {MaximizeIcon} from '@ui/icons/material/Maximize';
import {Trans} from '@ui/i18n/trans';
import {AnimatePresence, m} from 'framer-motion';
import {useEffect, useState} from 'react';
import {driveState, useDriveStore} from '../drive-store';

export function TelegramUploadQueuePanel() {
  const isTelegramQueueOpen = useDriveStore(
    s => s.telegramUploadQueueIsOpen,
  );
  const [isMinimized, setIsMinimized] = useState(false);

  // ✅ دیباگ: لاگ state
  useEffect(() => {
    console.log('📊 TelegramUploadQueuePanel state:', {
      isTelegramQueueOpen,
      isMinimized,
      timestamp: new Date().toISOString(),
    });
  }, [isTelegramQueueOpen, isMinimized]);

  // Fetch active progress list
  const {data, isLoading, error} = useQuery({
    queryKey: ['telegram-active-uploads'],
    queryFn: () => getUserProgressList(true, 20),
    refetchInterval: 1000, // هر 1 ثانیه refresh برای smoother progress
    enabled: isTelegramQueueOpen,
  });

  // ✅ دیباگ: لاگ data
  useEffect(() => {
    if (isTelegramQueueOpen) {
      console.log('📊 Query result:', {
        isLoading,
        hasData: !!data,
        progressCount: data?.progress?.length || 0,
        error: error?.message,
      });
    }
  }, [data, isLoading, error, isTelegramQueueOpen]);

  const activeUploads: TelegramUploadProgressData[] = data?.progress || [];
  const hasActiveUploads = activeUploads.length > 0;

  // شمارش بر اساس وضعیت
  const statusCounts = activeUploads.reduce(
    (acc, upload) => {
      if (upload.status === 'completed') acc.completed++;
      else if (upload.status === 'failed') acc.failed++;
      else acc.inProgress++;
      return acc;
    },
    {inProgress: 0, completed: 0, failed: 0},
  );

  // Auto-close وقتی دیگر آپلودی نیست (بعد از 5 ثانیه)
  useEffect(() => {
    if (isTelegramQueueOpen && !isLoading && !hasActiveUploads) {
      const timer = setTimeout(() => {
        driveState().setTelegramUploadQueueIsOpen(false);
      }, 5000);
      return () => clearTimeout(timer);
    }
  }, [isTelegramQueueOpen, isLoading, hasActiveUploads]);

  if (!isTelegramQueueOpen) return null;

  return (
    <AnimatePresence>
      <m.div
        initial={{opacity: 0, y: 50, scale: 0.95}}
        animate={{opacity: 1, y: 0, scale: 1}}
        exit={{opacity: 0, y: 50, scale: 0.95}}
        transition={{type: 'spring', damping: 25, stiffness: 300}}
        className={`
          fixed bottom-4 right-4 z-[100]
          ${isMinimized ? 'w-80' : 'w-[420px]'}
          ${isMinimized ? 'max-h-[80px]' : 'max-h-[600px]'}
          bg-paper rounded-xl shadow-2xl border-2 border-divider
          overflow-hidden flex flex-col
          backdrop-blur-sm
        `}
      >
        {/* Header با Gradient */}
        <div className="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-primary/10 via-primary/5 to-transparent border-b-2 border-divider">
          <div className="flex items-center gap-3">
            {/* آیکون Telegram */}
            <div className="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center">
              <svg
                className="w-5 h-5 text-primary"
                fill="currentColor"
                viewBox="0 0 24 24"
              >
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
              </svg>
            </div>

            {/* عنوان و آمار */}
            <div>
              <div className="text-sm font-bold text-main">
                <Trans message="Telegram Uploads" />
              </div>
              {hasActiveUploads && (
                <div className="text-xs text-muted flex items-center gap-2 mt-0.5">
                  {statusCounts.inProgress > 0 && (
                    <span className="flex items-center gap-1">
                      <span className="w-2 h-2 rounded-full bg-primary animate-pulse" />
                      {statusCounts.inProgress} در حال انجام
                    </span>
                  )}
                  {statusCounts.completed > 0 && (
                    <span className="flex items-center gap-1">
                      <span className="w-2 h-2 rounded-full bg-positive" />
                      {statusCounts.completed} موفق
                    </span>
                  )}
                  {statusCounts.failed > 0 && (
                    <span className="flex items-center gap-1">
                      <span className="w-2 h-2 rounded-full bg-danger" />
                      {statusCounts.failed} خطا
                    </span>
                  )}
                </div>
              )}
            </div>

            {/* Badge تعداد */}
            {hasActiveUploads && (
              <div className="px-2.5 py-1 rounded-full bg-primary text-on-primary text-xs font-bold shadow-sm">
                {activeUploads.length}
              </div>
            )}
          </div>

          {/* دکمه‌های کنترل */}
          <div className="flex items-center gap-1">
            <IconButton
              size="sm"
              onClick={() => setIsMinimized(!isMinimized)}
              className="hover:bg-hover transition-colors"
            >
              {isMinimized ? (
                <MaximizeIcon className="text-muted" />
              ) : (
                <MinimizeIcon className="text-muted" />
              )}
            </IconButton>
            <IconButton
              size="sm"
              onClick={() => {
                driveState().setTelegramUploadQueueIsOpen(false);
              }}
              className="hover:bg-danger/10 hover:text-danger transition-colors"
            >
              <CloseIcon />
            </IconButton>
          </div>
        </div>

        {/* محتوا (فقط در حالت غیر minimize) */}
        {!isMinimized && (
          <div className="flex-1 overflow-y-auto custom-scrollbar">
            {/* Loading State */}
            {isLoading && (
              <div className="p-8 flex flex-col items-center justify-center gap-3">
                <div className="w-12 h-12 border-4 border-primary/30 border-t-primary rounded-full animate-spin" />
                <p className="text-sm text-muted">
                  <Trans message="Loading uploads..." />
                </p>
              </div>
            )}

            {/* Empty State */}
            {!isLoading && activeUploads.length === 0 && (
              <div className="p-8 flex flex-col items-center justify-center gap-3">
                <div className="w-16 h-16 rounded-full bg-positive/10 flex items-center justify-center">
                  <svg
                    className="w-8 h-8 text-positive"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M5 13l4 4L19 7"
                    />
                  </svg>
                </div>
                <p className="text-sm font-medium text-main">
                  <Trans message="All uploads completed!" />
                </p>
                <p className="text-xs text-muted">
                  <Trans message="No active uploads" />
                </p>
              </div>
            )}

            {/* Progress List */}
            {!isLoading && activeUploads.length > 0 && (
              <div className="p-3 space-y-2">
                {activeUploads.map((progress: TelegramUploadProgressData) => (
                  <TelegramUploadProgress
                    key={progress.session_id}
                    sessionId={progress.session_id}
                    compact={false}
                    onComplete={() => {
                      console.log('✅ Upload completed:', progress.session_id);
                    }}
                    onError={error => {
                      console.error('❌ Upload error:', error);
                    }}
                  />
                ))}
              </div>
            )}
          </div>
        )}

        {/* Footer (فقط در حالت minimize و وقتی آپلودی فعال است) */}
        {isMinimized && hasActiveUploads && (
          <div className="px-4 py-2 bg-alt border-t border-divider">
            <div className="flex items-center justify-between text-xs">
              <span className="text-muted">
                {statusCounts.inProgress} در حال انجام
              </span>
              <span className="text-primary font-medium">
                {activeUploads.length} فایل
              </span>
            </div>
          </div>
        )}
      </m.div>
    </AnimatePresence>
  );
}
