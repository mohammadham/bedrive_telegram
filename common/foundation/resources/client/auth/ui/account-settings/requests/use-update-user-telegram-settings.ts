import {useMutation} from '@tanstack/react-query';
import {apiClient, queryClient} from '@common/http/query-client';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {toast} from '@ui/toast/toast';
import {UseFormReturn} from 'react-hook-form';
import {onFormQueryError} from '@common/errors/on-form-query-error';
import {User} from '@ui/types/user';

interface Response extends BackendResponse {
  user: User;
}

export interface Payload {
  telegram_user_chat_id?: string;
  telegram_auto_forward?: boolean;
}

export function useUpdateUserTelegramSettings(
  form: UseFormReturn<Payload>,
  userId: number,
) {
  return useMutation({
    mutationFn: (payload: Payload) => updateUserTelegramSettings(userId, payload),
    onSuccess: () => {
      toast('Updated telegram settings');
      queryClient.invalidateQueries({queryKey: ['users', userId]});
    },
    onError: err => onFormQueryError(err, form),
  });
}

function updateUserTelegramSettings(userId: number, payload: Payload) {
  return apiClient
    .put<Response>(`users/${userId}/telegram-settings`, payload)
    .then(r => r.data);
}
