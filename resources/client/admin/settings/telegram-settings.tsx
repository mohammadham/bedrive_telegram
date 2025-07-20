import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@common/http/api-client';
import { Trans } from '@ui/i18n/trans';
import { FormTextField } from '@ui/forms/input-field/text-field/text-field';
import { Button } from '@ui/buttons/button';
import { useFormContext } from 'react-hook-form';
import { AdminSettings } from '@common/admin/settings/admin-settings';
import { toast } from '@ui/toast/toast';
import { FormSwitch } from '@ui/forms/toggle/switch';

export function TelegramSettings() {
  const { setValue } = useFormContext<AdminSettings>();
  const { data, isLoading } = useQuery({
    queryKey: ['telegram-status'],
    queryFn: () => apiClient.get('telegram/status').then(res => res.data),
  });

  const handleInstall = () => {
    apiClient.post('telegram/install').then(() => {
      toast('Telegram-upload installation started. This may take a moment.');
    });
  };

  const handleConfigure = () => {
    apiClient.post('telegram/configure-session').then(() => {
      toast('Telegram session configuration started. Check your Telegram for a code.');
    });
  };

  return (
    <div>
      <h2 className="mb-20 border-b pb-10 text-lg font-semibold">
        <Trans message="Telegram Settings" />
      </h2>
      {isLoading ? (
        <p>Loading Telegram status...</p>
      ) : (
        <div>
          <p className="mb-10">
            <Trans message="Status:" />{' '}
            {data?.configured ? (
              <span className="text-positive">
                <Trans message="Configured" />
              </span>
            ) : (
              <span className="text-danger">
                <Trans message="Not Configured" />
              </span>
            )}
          </p>
          {!data?.status.telegram_upload_installed && (
            <Button variant="flat" color="primary" onClick={handleInstall}>
              <Trans message="Install telegram-upload" />
            </Button>
          )}
          {data?.status.telegram_upload_installed && !data?.status.session_exists && (
            <Button variant="flat" color="primary" onClick={handleConfigure}>
              <Trans message="Configure Session" />
            </Button>
          )}
        </div>
      )}
      <FormTextField
        name="client.telegram.api_id"
        label={<Trans message="API ID" />}
        className="mb-20"
      />
      <FormTextField
        name="client.telegram.api_hash"
        label={<Trans message="API Hash" />}
        className="mb-20"
      />
      <FormTextField
        name="client.telegram.phone"
        label={<Trans message="Phone Number" />}
        className="mb-20"
      />
      <FormTextField
        name="client.telegram.chat_id"
        label={<Trans message="Default Chat ID" />}
        className="mb-20"
      />
      <FormSwitch
        name="client.telegram.enabled"
        description={<Trans message="Enable Telegram as a storage method." />}
      >
        <Trans message="Enable Telegram Storage" />
      </FormSwitch>
    </div>
  );
}
