import {Trans} from '@ui/i18n/trans';
import {useQuery} from '@tanstack/react-query';
import {apiClient} from '@common/http/query-client';
import {SectionHelper} from '@common/ui/other/section-helper';
import {Skeleton} from '@ui/skeleton/skeleton';
import {Fragment} from 'react';

interface TelegramStats {
  total_uploads: number;
  bot_uploads: number;
  user_uploads: number;
  completed_uploads: number;
  failed_uploads: number;
  total_size: number;
  total_size_formatted: string;
  success_rate: string;
}

export function TelegramStats() {
  const {data, isLoading, error} = useQuery({
    queryKey: ['telegram-stats'],
    queryFn: () => fetchTelegramStats(),
    // Refresh every 30 seconds
    refetchInterval: 30000,
  });

  if (isLoading) {
    return (
      <div className="space-y-3">
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-4 w-1/2" />
      </div>
    );
  }

  if (error || !data) {
    return null;
  }

  return (
    <SectionHelper
      className="mt-30"
      color="positive"
      title={<Trans message="Telegram Storage Statistics" />}
      description={
        <div className="space-y-2">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <div className="text-sm font-semibold">
                <Trans message="Total Uploads" />
              </div>
              <div className="text-lg">{data.total_uploads.toLocaleString()}</div>
            </div>
            <div>
              <div className="text-sm font-semibold">
                <Trans message="Total Size" />
              </div>
              <div className="text-lg">{data.total_size_formatted}</div>
            </div>
            <div>
              <div className="text-sm font-semibold">
                <Trans message="Bot Uploads" />
              </div>
              <div className="text-lg">
                {data.bot_uploads.toLocaleString()}
                <span className="text-sm text-muted ml-2">(&lt; 50MB)</span>
              </div>
            </div>
            <div>
              <div className="text-sm font-semibold">
                <Trans message="User Uploads" />
              </div>
              <div className="text-lg">
                {data.user_uploads.toLocaleString()}
                <span className="text-sm text-muted ml-2">(50MB-2GB)</span>
              </div>
            </div>
            <div>
              <div className="text-sm font-semibold">
                <Trans message="Success Rate" />
              </div>
              <div className="text-lg">{data.success_rate}</div>
            </div>
            <div>
              <div className="text-sm font-semibold">
                <Trans message="Failed Uploads" />
              </div>
              <div className="text-lg">{data.failed_uploads.toLocaleString()}</div>
            </div>
          </div>
        </div>
      }
    />
  );
}

async function fetchTelegramStats(): Promise<TelegramStats> {
  const response = await apiClient.get('admin/telegram/stats');
  return response.data;
}
