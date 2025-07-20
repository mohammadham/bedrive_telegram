import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../../common/http/api-client';
import { toast } from '../../../common/ui/toast/toast';
import { Dialog } from '../../../common/ui/dialog/dialog';
import { DialogHeader } from '../../../common/ui/dialog/dialog-header';
import { DialogBody } from '../../../common/ui/dialog/dialog-body';
import { DialogFooter } from '../../../common/ui/dialog/dialog-footer';
import { Button } from '../../../common/ui/buttons/button';
import { Form } from '../../../common/ui/forms/form';
import { FormTextField } from '../../../common/ui/forms/input-field/text-field/text-field';
import { FormSwitch } from '../../../common/ui/forms/toggle/switch';

interface TelegramSettings {
  telegram_chat_id: string;
  auto_send_to_telegram: boolean;
}

export function TelegramSettingsDialog({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
  const queryClient = useQueryClient();
  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<TelegramSettings>();

  const { data: settings, isLoading } = useQuery({
    queryKey: ['telegram-settings'],
    queryFn: () => apiClient.get('user/telegram-settings').then(res => res.data),
    enabled: isOpen,
  });

  useEffect(() => {
    if (settings) {
      setValue('telegram_chat_id', settings.telegram_chat_id);
      setValue('auto_send_to_telegram', settings.auto_send_to_telegram);
    }
  }, [settings, setValue]);

  const mutation = useMutation({
    mutationFn: (data: TelegramSettings) => apiClient.put('user/telegram-settings', data),
    onSuccess: () => {
      toast('Telegram settings updated successfully');
      queryClient.invalidateQueries({ queryKey: ['telegram-settings'] });
      onClose();
    },
    onError: () => {
      toast.danger('Failed to update Telegram settings');
    },
  });

  const onSubmit = (data: TelegramSettings) => {
    mutation.mutate(data);
  };

  return (
    <Dialog isOpen={isOpen} onClose={onClose}>
      <DialogHeader>Telegram Settings</DialogHeader>
      <DialogBody>
        {isLoading ? (
          <p>Loading...</p>
        ) : (
          <Form onSubmit={handleSubmit(onSubmit)}>
            <FormTextField
              label="Telegram Chat ID"
              {...register('telegram_chat_id')}
              error={errors.telegram_chat_id?.message}
              description="Enter the ID of the chat or channel where files should be sent."
            />
            <FormSwitch
              {...register('auto_send_to_telegram')}
              label="Automatically send to Telegram"
              description="Automatically send a copy of all uploaded files to Telegram."
            />
          </Form>
        )}
      </DialogBody>
      <DialogFooter>
        <Button onClick={onClose}>Cancel</Button>
        <Button
          variant="flat"
          color="primary"
          onClick={handleSubmit(onSubmit)}
          disabled={mutation.isPending}
        >
          Save
        </Button>
      </DialogFooter>
    </Dialog>
  );
}
