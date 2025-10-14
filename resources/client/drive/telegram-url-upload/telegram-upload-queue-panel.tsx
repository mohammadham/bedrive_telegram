import {useQuery} from '@tanstack/react-query';
import {getUserProgressList} from './telegram-progress-api';
import {TelegramUploadProgress} from './telegram-upload-progress';
import {TelegramUploadProgressData} from './telegram-progress-types';
import {IconButton} from '@ui/buttons/icon-button';
import {CloseIcon} from '@ui/icons/material/Close';
import {Trans} from '@ui/i18n/trans';
import {AnimatePresence, m} from 'framer-motion';
import {useEffect} from 'react';
import {driveState, useDriveStore} from '../drive-store';

/**
 * Panel نمایش لیست Telegram uploads در حال انجام
 * نمایش داده می‌شود وقتی آپلود URL فعال باشد
 */
export function TelegramUploadQueuePanel() {
  const isTelegramQueueOpen = useDriveStore(
    s => s.telegramUploadQueueIsOpen,
  );

  // Fetch active progress list
  const {data, isLoading} = useQuery({
    queryKey: ['telegram-active-uploads'],
    queryFn: () => getUserProgressList(true, 10),
    refetchInterval: 2000, // هر 2 ثانیه refresh
    enabled: isTelegramQueueOpen,
  });

  const activeUploads: TelegramUploadProgressData[] = data?.progress || [];
  const hasActiveUploads = activeUploads.length > 0;

  // Auto-close وقتی دیگر آپلودی نیست
  useEffect(() => {
    if (isTelegramQueueOpen && !isLoading && !hasActiveUploads) {
      const timer = setTimeout(() => {
        driveState().setTelegramUploadQueueIsOpen(false);
      }, 3000); // 3 ثانیه بعد از تکمیل بسته شود
      return () => clearTimeout(timer);
    }
  }, [isTelegramQueueOpen, isLoading, hasActiveUploads]);

  return (
    <AnimatePresence>
      {isTelegramQueueOpen && (
        <m.div
          initial={{opacity: 0, y: 50}}
          animate={{opacity: 1, y: 0}}
          exit={{opacity: 0, y: 50}}
          className="fixed bottom-4 right-4 z-50 w-96 max-h-[500px] bg-paper rounded-lg shadow-2xl border border-divider overflow-hidden flex flex-col"
        >
          {/* Header */}
          <div className="flex items-center justify-between p-4 border-b border-divider bg-alt">
            <div className="flex items-center gap-2">
              <div className="text-sm font-semibold">
                <Trans message="Telegram Uploads" />
              </div>
              {hasActiveUploads && (
                <div className="px-2 py-1 rounded-full bg-primary text-on-primary text-xs font-medium">
                  {activeUploads.length}
                </div>
              )}
            </div>
            <IconButton
              size="sm"
              onClick={() => {
                driveState().setTelegramUploadQueueIsOpen(false);
              }}
            >
              <CloseIcon />
            </IconButton>
          </div>

          {/* Progress List */}
          <div className="flex-1 overflow-y-auto p-2 space-y-2">
            {isLoading && (
              <div className="p-4 text-center text-muted">
                <Trans message="Loading..." />
              </div>
            )}

            {!isLoading && activeUploads.length === 0 && (
              <div className="p-8 text-center text-muted">
                <Trans message="No active uploads" />
              </div>
            )}

            {activeUploads.map((progress: TelegramUploadProgressData) => (
              <TelegramUploadProgress
                key={progress.session_id}
                sessionId={progress.session_id}
                compact={false}
                onComplete={() => {
                  // Refresh file list on complete
                  // queryClient.invalidateQueries() is called automatically by the component
                }}
              />
            ))}
          </div>
        </m.div>
      )}
    </AnimatePresence>
  );
}
