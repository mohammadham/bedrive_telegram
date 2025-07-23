import {Trans} from '@ui/i18n/trans';
import {useForm} from 'react-hook-form';
import {Button} from '@ui/buttons/button';
import {AccountSettingsPanel} from '@common/auth/ui/account-settings/account-settings-panel';
import {Form} from '@ui/forms/form';
import {FormTextField} from '@ui/forms/input-field/text-field/text-field';
import {FormSwitch} from '@ui/forms/toggle/switch';
import {User} from '@ui/types/user';
import {useUserTelegramSettings} from '@common/auth/ui/account-settings/requests/use-user-telegram-settings';
import {ProgressCircle} from '@ui/progress/progress-circle';
import {useEffect,useId} from 'react';
import {AccountSettingsId} from '@common/auth/ui/account-settings/account-settings-sidenav';
import {
  Payload as TelegramSettingsPayload,
  useUpdateUserTelegramSettings,
} from '@common/auth/ui/account-settings/requests/use-update-user-telegram-settings';
interface Props {
  user: User;
}
export function TelegramSettingsPanel({user}: Props) {
  const {data, isLoading} = useUserTelegramSettings(user.id);
  const form = useForm<Partial<TelegramSettingsPayload>>({
    defaultValues: {
      telegram_chat_id: data?.settings.telegram_chat_id || '',
      auto_send_to_telegram: data?.settings.auto_send_to_telegram || false,
    },
  });
  const updateUserTelegramSettings = useUpdateUserTelegramSettings(form, user.id);
  const formId = useId();
  useEffect(() => {
    if (data?.settings) {
      form.reset(data.settings);
    }
  }, [data, form]);

  // if (isLoading) {
  //   return <ProgressCircle isIndeterminate />;
  // }

  return (
    <AccountSettingsPanel
    id={AccountSettingsId.Telegram}
      title={<Trans message="Telegram Settings" />}
      actions={
        [
        <Button
        type="submit"
        variant="flat"
        color="primary"
        form={formId}
        disabled={isLoading || !form.formState.isValid}
      >
        <Trans message="Save" />
      </Button>
    ]
      }
    >
      <Trans message="Configure your personal Telegram integration settings." />
        { isLoading ? (
                    <div className="min-h-60">
                    <ProgressCircle isIndeterminate />
                  </div>
          ) : (
          <Form
            form={form}
            onSubmit={values => {
              updateUserTelegramSettings.mutate(values);
            }}
            id={formId}
          >
        <FormTextField
          name="telegram_chat_id"
          label={<Trans message="Telegram Chat ID" />}
          description={
            <Trans message="Your personal chat/channel ID for file uploads (e.g., @mychannel or a numeric ID)." />
          }
          className="mb-20"
        />
        <FormSwitch name="auto_send_to_telegram">
          <Trans message="Automatically send uploads to Telegram" />
        </FormSwitch>
      </Form>
        )}
    </AccountSettingsPanel>
  );
}
