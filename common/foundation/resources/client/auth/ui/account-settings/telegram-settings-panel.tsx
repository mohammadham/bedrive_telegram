import {Trans} from '@ui/i18n/trans';
import {useForm} from 'react-hook-form';
import {useUpdateUserTelegramSettings} from '@common/auth/ui/account-settings/requests/use-update-user-telegram-settings';
import {AccountSettingsPanel} from '@common/auth/ui/account-settings/account-settings-panel';
import {Form} from '@ui/forms/form';
import {FormTextField} from '@ui/forms/input-field/text-field/text-field';
import {FormSwitch} from '@ui/forms/toggle/switch';
import {User} from '@common/auth/user';
import {useUserTelegramSettings} from '@common/auth/ui/account-settings/requests/use-user-telegram-settings';
import {ProgressCircle} from '@ui/progress/progress-circle';

interface Props {
  user: User;
}
export function TelegramSettingsPanel({user}: Props) {
  const {data, isLoading} = useUserTelegramSettings(user.id);
  const form = useForm({
    defaultValues: {
      telegram_chat_id: '',
      auto_send_to_telegram: false,
    },
  });
  const updateUserTelegramSettings = useUpdateUserTelegramSettings(form);

  if (isLoading) {
    return <ProgressCircle isIndeterminate />;
  }

  return (
    <AccountSettingsPanel
      id="telegram-settings"
      title={<Trans message="Telegram Settings" />}
      description={
        <Trans message="Configure your personal Telegram integration settings." />
      }
    >
      <Form
        form={form}
        onSubmit={values => {
          updateUserTelegramSettings.mutate(values);
        }}
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
    </AccountSettingsPanel>
  );
}
