import {useQuery} from '@tanstack/react-query';
import {apiClient, queryClient} from '@common/http/query-client';
import {BackendResponse} from '@common/http/backend-response/backend-response';
// import {toast} from '@ui/toast/toast';
// import {UseFormReturn} from 'react-hook-form';
// import {onFormQueryError} from '@common/errors/on-form-query-error';
// import {User} from '@common/auth/user';

interface Response extends BackendResponse {
  settings: {
    telegram_user_chat_id: string;
    telegram_auto_forward: boolean;
  };
}

export function useUserTelegramSettings(userId: number) {
  return useQuery({
    queryKey: ['users', userId, 'telegram-settings'],
    queryFn: () => fetchUserTelegramSettings(userId),
  });
}

function fetchUserTelegramSettings(userId: number) {
  return apiClient
    .get<Response>(`users/${userId}/telegram-settings`)
    .then(response => response.data);
}
