import {Trans} from '@ui/i18n/trans';
import {FormRadioGroup} from '@ui/forms/radio-group/radio-group';
import {FormRadio} from '@ui/forms/radio-group/radio';
import {FormSwitch} from '@ui/forms/toggle/switch';
import {
  AdminSettingsForm,
  AdminSettingsLayout,
} from '@common/admin/settings/form/admin-settings-form';
import React, {useState} from 'react';
import {AdminSettings} from '@common/admin/settings/admin-settings';
import {useForm} from 'react-hook-form';
import {FormTextField} from '@ui/forms/input-field/text-field/text-field';
import {Button} from '@ui/buttons/button';
import {useMutation} from '@tanstack/react-query';
import {toast} from '@ui/toast/toast';
import {api} from '@common/http/api-client';
import {CheckCircleIcon} from '@ui/icons/material/CheckCircle';
import {ErrorIcon} from '@ui/icons/material/Error';
import {ProgressCircle} from '@ui/progress/progress-circle';

export function DriveSettings() {
  return (
    <AdminSettingsLayout
      title={<Trans message="Drive" />}
      description={
        <Trans message="Configure defaults for drive user dashboard." />
      }
    >
      {data => <Form data={data} />}
    </AdminSettingsLayout>
  );
}

interface FormProps {
  data: AdminSettings;
}
function Form({data}: FormProps) {
  const form = useForm<AdminSettings>({
    defaultValues: {
      client: {
        drive: {
          default_view: data.client.drive?.default_view ?? 'list',
          send_share_notification:
            data.client.drive?.send_share_notification ?? false,
        },
        share: {
          suggest_emails: data.client.share?.suggest_emails ?? false,
        },
      },
      server: {
        telegram_bot_token: data.server.telegram_bot_token,
        telegram_webhook_set: data.server.telegram_webhook_set,
      },
    },
  });

  const configureBot = useMutation({
    mutationFn: (token: string) =>
      api().post('telegram/configure-bot', {token}),
    onSuccess: () => {
      toast('Bot configured successfully');
      form.setValue('server.telegram_webhook_set', true);
    },
    onError: err => {
      toast.danger('Could not configure bot');
    },
  });

  return (
    <AdminSettingsForm form={form}>
      <FormRadioGroup
        required
        className="mb-30"
        size="md"
        name="client.drive.default_view"
        orientation="vertical"
        label={<Trans message="Default view mode" />}
        description={
          <Trans message="Which view mode should user drive use by default." />
        }
      >
        <FormRadio value="list">
          <Trans message="List" />
        </FormRadio>
        <FormRadio value="grid">
          <Trans message="Grid" />
        </FormRadio>
      </FormRadioGroup>
      <FormSwitch
        className="mb-30"
        name="client.drive.send_share_notification"
        description={
          <Trans message="Send a notification to user when a file or folder is shared with them." />
        }
      >
        <Trans message="Share notifications" />
      </FormSwitch>
      <FormSwitch
        name="client.share.suggest_emails"
        description={
          <Trans message="Suggest email address of existing users when sharing a file or folder." />
        }
      >
        <Trans message="Suggest emails" />
      </FormSwitch>

      <div className="mt-40 border-t pt-40">
        <h2 className="text-xl font-semibold mb-4">
          <Trans message="Telegram Bot Settings" />
        </h2>
        <div className="mb-20">
          <Trans message="Configure a Telegram bot to enable advanced features like reliable file deletion and existence checks." />
        </div>
        <FormTextField
          name="server.telegram_bot_token"
          label={<Trans message="Bot Token" />}
          description={
            <Trans message="Your Telegram bot token from @BotFather." />
          }
          className="mb-20"
        />
        <div className="flex items-center gap-10">
          <Button
            variant="flat"
            color="primary"
            onClick={() => {
              const token = form.getValues('server.telegram_bot_token');
              if (token) {
                configureBot.mutate(token);
              } else {
                toast.danger('Please enter a bot token first.');
              }
            }}
            disabled={configureBot.isPending}
          >
            <Trans message="Save Token and Set Webhook" />
          </Button>
          {configureBot.isPending && <ProgressCircle isIndeterminate size="sm" />}
          {form.watch('server.telegram_webhook_set') && !configureBot.isPending && (
            <div className="flex items-center gap-4 text-positive">
              <CheckCircleIcon size="sm" />
              <Trans message="Webhook is active" />
            </div>
          )}
          {configureBot.isError && !configureBot.isPending && (
            <div className="flex items-center gap-4 text-danger">
              <ErrorIcon size="sm" />
              <Trans message="Webhook setup failed" />
            </div>
          )}
        </div>
      </div>
    </AdminSettingsForm>
  );
}
