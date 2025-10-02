/**
 * Phase 8.2: Progress Tracking - Progress List Component
 * 
 * نمایش لیست تمام آپلودهای در حال انجام
 */

import React from 'react';
import {Trans} from '@ui/i18n/trans';
import {useQuery} from '@tanstack/react-query';
import {getUserProgressList} from './telegram-progress-api';
import {TelegramUploadProgress} from './telegram-upload-progress';
import {ProgressCircle} from '@ui/progress/progress-circle';
import {IllustratedMessage} from '@ui/images/illustrated-message';
import {SvgImage} from '@ui/images/svg-image';

interface TelegramUploadProgressListProps {
  activeOnly?: boolean;
  limit?: number;
  onComplete?: () => void;
}

export function TelegramUploadProgressList({
  activeOnly = true,
  limit = 10,
  onComplete,
}: TelegramUploadProgressListProps) {
  const {data, isLoading} = useQuery({
    queryKey: ['telegram-upload-progress-list', activeOnly, limit],
    queryFn: () => getUserProgressList(activeOnly, limit),
    refetchInterval: activeOnly ? 2000 : false, // Refresh every 2s for active
  });

  // Loading
  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-40">
        <ProgressCircle size="md" isIndeterminate />
      </div>
    );
  }

  // Empty
  if (!data?.progress || data.progress.length === 0) {
    return (
      <IllustratedMessage
        size="sm"
        title={<Trans message="آپلودی در حال انجام نیست" />}
        description={
          <Trans message="فایل‌های آپلود شده در اینجا نمایش داده می‌شوند" />
        }
        image={
          <SvgImage>
            <svg
              width="140"
              height="140"
              viewBox="0 0 140 140"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <circle cx="70" cy="70" r="60" fill="currentColor" opacity="0.1" />
              <path
                d="M70 40V100M40 70H100"
                stroke="currentColor"
                strokeWidth="6"
                strokeLinecap="round"
              />
            </svg>
          </SvgImage>
        }
      />
    );
  }

  // List
  return (
    <div className="space-y-16">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-medium">
          {activeOnly ? (
            <Trans message="آپلودهای در حال انجام" />
          ) : (
            <Trans message="تاریخچه آپلودها" />
          )}
        </h3>
        <span className="text-xs text-muted">
          {data.count} <Trans message="مورد" />
        </span>
      </div>

      <div className="space-y-12">
        {data.progress.map(item => (
          <TelegramUploadProgress
            key={item.session_id}
            sessionId={item.session_id}
            onComplete={onComplete}
            showDetails={true}
            compact={false}
          />
        ))}
      </div>
    </div>
  );
}
