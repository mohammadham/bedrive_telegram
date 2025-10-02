import {AccountSettingsPanel} from '../account-settings-panel';
import {Trans} from '@ui/i18n/trans';
import {useQuery, useMutation, useQueryClient} from '@tanstack/react-query';
import {apiClient} from '@common/http/query-client';
import {toast} from '@ui/toast/toast';
import {FormSwitch} from '@ui/forms/form-switch';
import {FormTextField} from '@ui/forms/input-field/text-field/text-field';
import {Button} from '@ui/buttons/button';
import {useForm} from 'react-hook-form';
import {Form} from '@ui/forms/form';
import {SectionHelper} from '@common/admin/settings/form/section-helper';

interface TelegramSettings {
  auto_forward: boolean;
  forward_target: string | null;
  driver_enabled: boolean;
}

interface TelegramSettingsFormData {
  auto_forward: boolean;
  forward_target: string;
}

export function TelegramSettingsPanel() {
  const queryClient = useQueryClient();

  // Fetch current settings
  const {data: settings, isLoading} = useQuery<TelegramSettings>({
    queryKey: ['user-telegram-settings'],
    queryFn: () => fetchTelegramSettings(),
  });

  const form = useForm<TelegramSettingsFormData>({
    defaultValues: {
      auto_forward: settings?.auto_forward ?? false,
      forward_target: settings?.forward_target ?? '',
    },
  });

  // Update settings mutation
  const updateSettings = useMutation({
    mutationFn: (data: TelegramSettingsFormData) => 
      updateTelegramSettings(data),
    onSuccess: () => {
      toast(Trans({message: 'Telegram settings updated successfully'}));
      queryClient.invalidateQueries({queryKey: ['user-telegram-settings']});
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Failed to update settings';
      toast.danger(message);
    },
  });

  // Don't show panel if Telegram driver is not enabled
  if (!isLoading && !settings?.driver_enabled) {
    return null;
  }

  return (
    <AccountSettingsPanel
      id="telegram-settings"
      title={<Trans message="Telegram Settings" />}
      isLoading={isLoading}
    >
      <Form
        form={form}
        onSubmit={values => {
          updateSettings.mutate(values);
        }}
      >
        <SectionHelper
          color="positive"
          description={
            <Trans message="Configure automatic forwarding of uploaded files to your Telegram channel, group, or user account." />
          }
        />

        <FormSwitch
          name="auto_forward"
          className="mb-24"
          description={
            <Trans message="Automatically forward all uploaded files to Telegram" />
          }
        >
          <Trans message="Enable Auto-Forward" />
        </FormSwitch>

        {form.watch('auto_forward') && (
          <FormTextField
            name="forward_target"
            label={<Trans message="Telegram ID" />}
            description={
              <Trans message="Enter channel ID (-1001234567890), username (@username), or user ID" />
            }
            placeholder="-1001234567890 or @channel"
            required={form.watch('auto_forward')}
            className="mb-24"
          />
        )}

        <SectionHelper
          color="warning"
          title={<Trans message="Important Notes" />}
          description={
            <div>
              <ul className="list-disc list-inside space-y-1">
                <li>
                  <Trans message="For channels/groups: Use channel/group ID (starts with -100)" />
                </li>
                <li>
                  <Trans message="For users: Use username (@username) or numeric user ID" />
                </li>
                <li>
                  <Trans message="Bot must have permission to send messages to the target" />
                </li>
                <li>
                  <Trans message="Only files uploaded to Telegram storage will be forwarded" />
                </li>
              </ul>
            </div>
          }
        />

        <Button
          type="submit"
          variant="flat"
          color="primary"
          className="mt-24"
          disabled={updateSettings.isPending}
        >
          <Trans message="Save Settings" />
        </Button>
      </Form>
    </AccountSettingsPanel>
  );
}

async function fetchTelegramSettings(): Promise<TelegramSettings> {
  return apiClient
    .get('user/telegram/settings')
    .then(r => r.data);
}

async function updateTelegramSettings(
  data: TelegramSettingsFormData
): Promise<void> {
  return apiClient.put('user/telegram/settings', data).then(r => r.data);
}
