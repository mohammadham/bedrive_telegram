import {useMutation} from '@tanstack/react-query';
import {UseFormReturn} from 'react-hook-form';
import {toast} from '@ui/toast/toast';
import {apiClient, queryClient} from '@common/http/query-client';
import {AdminSettings} from '@common/admin/settings/admin-settings';
import {onFormQueryError} from '@common/errors/on-form-query-error';
import {FetchAdminSettingsResponse} from '@common/admin/settings/requests/use-admin-settings';
import {message} from '@ui/i18n/message';
import {setBootstrapData} from '@ui/bootstrap-data/bootstrap-data-store';

export interface AdminSettingsWithFiles {
  files?: Record<string, File>;
  client?: Partial<AdminSettings['client']>;
  server?: Partial<AdminSettings['server']>;
}

export function useUpdateAdminSettings(
  form: UseFormReturn<AdminSettingsWithFiles>,
) {
  return useMutation({
    mutationFn: (props: AdminSettingsWithFiles) => updateAdminSettings(props),
    onSuccess: response => {
      toast(message('Settings updated'), {
        position: 'bottom-right',
      });
      console.log('✅ Settings updated:', response);
      // به‌روزرسانی bootstrap data برای دسترسی فوری در سراسر اپلیکیشن
      // response شامل client و server است، نه settings
      if (response.bootstrapData) {
        // mergeBootstrapData({
        //   settings: {
        //     client: response.client,
        //     server: response.server,
        //   },
        // });
        setBootstrapData(response.bootstrapData);
      }
      return queryClient.setQueryData(['fetchAdminSettings'], response);
    },
    onError: r => onFormQueryError(r, form),
  });
}

function updateAdminSettings({client, server, files}: AdminSettingsWithFiles) {
  const formData = new FormData();
  if (client) {
    formData.set('client', JSON.stringify(client));
  }
  if (server) {
    formData.set('server', JSON.stringify(server));
  }
  Object.entries(files || {}).forEach(([key, file]) => {
    formData.set(key, file);
  });
  return apiClient
    .post<FetchAdminSettingsResponse>('settings', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    .then(r => r.data);
}
