/**
 * Phase 8.2: Progress Tracking - React Hook
 */

import {useQuery} from '@tanstack/react-query';
import {getUploadProgress} from './telegram-progress-api';
import {TelegramUploadProgressData} from './telegram-progress-types';

interface UseUploadProgressOptions {
  sessionId: string;
  enabled?: boolean;
  refetchInterval?: number;
}

/**
 * Hook برای tracking progress یک آپلود
 * 
 * با polling خودکار هر 1 ثانیه
 */
export function useUploadProgress({
  sessionId,
  enabled = true,
  refetchInterval = 1000, // 1 second
}: UseUploadProgressOptions) {
  const query = useQuery({
    queryKey: ['telegram-upload-progress', sessionId],
    queryFn: () => getUploadProgress(sessionId),
    enabled: enabled && !!sessionId,
    refetchInterval: (data) => {
      // Stop polling if completed, failed, or cancelled
      const progress = data?.progress;
      if (!progress) return false;
      
      const isFinished = ['completed', 'failed', 'cancelled'].includes(
        progress.status,
      );
      
      return isFinished ? false : refetchInterval;
    },
    staleTime: 0, // Always fresh
    cacheTime: 5 * 60 * 1000, // 5 minutes
  });

  const progress: TelegramUploadProgressData | undefined = query.data?.progress;

  return {
    progress,
    isLoading: query.isLoading,
    isError: query.isError,
    error: query.error,
    refetch: query.refetch,
    
    // Helper properties
    isInProgress: progress
      ? ['pending', 'downloading', 'uploading'].includes(progress.status)
      : false,
    isCompleted: progress?.status === 'completed',
    isFailed: progress?.status === 'failed',
    isCancelled: progress?.status === 'cancelled',
  };
}