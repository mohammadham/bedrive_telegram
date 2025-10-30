import {Trans} from '@ui/i18n/trans';
import {FormTextField} from '@ui/forms/input-field/text-field/text-field';
import {Fragment} from 'react';
import {SectionHelper} from '@common/ui/other/section-helper';
import {FormSwitch} from '@ui/forms/toggle/switch';
import {SettingsIcon} from '@ui/icons/material/Settings';

export interface TelegramAdvancedSettingsProps {
  isTelegramActive: boolean;
}

export function TelegramAdvancedSettings({
  isTelegramActive,
}: TelegramAdvancedSettingsProps) {
  if (!isTelegramActive) {
    return null;
  }

  return (
    <Fragment>
      <SectionHelper
        className="mb-30 mt-30"
        color="neutral"
        title={
          <div className="flex items-center gap-8">
            <SettingsIcon className="text-muted" size="sm" />
            <Trans message="Advanced Telegram Settings" />
          </div>
        }
        description={
          <Trans message="Configure additional features and options for Telegram storage driver." />
        }
      />

      {/* Enable/Disable URL Upload */}
      <FormSwitch
        className="mb-30"
        name="server.telegram_enable_url_upload"
        description={
          <Trans message="Allow users to upload files directly from URLs to Telegram. When disabled, the URL upload button will be hidden." />
        }
      >
        <Trans message="Enable URL Upload Feature" />
      </FormSwitch>

      {/* Forward Caption Template */}
      <FormTextField
        className="mb-30"
        name="server.telegram_forward_caption_template"
        label={<Trans message="Forward Caption Template" />}
        placeholder="📁 File: {filename}
📊 Size: {size}
📅 Date: {date}"
        inputElementType="textarea"
        rows={4}
        description={
          <div>
            <p className="mb-8">
              <Trans message="Custom caption template for forwarded files. This text will be attached to files when they are auto-forwarded or manually forwarded to other Telegram chats." />
            </p>
            <p className="text-xs">
              <Trans message="Available variables: {filename}, {size}, {type}, {date}, {original_name}" />
            </p>
          </div>
        }
      />

      <SectionHelper
        className="mb-30"
        color="positive"
        description={
          <div>
            <p className="mb-10 font-semibold">
              <Trans message="Caption Template Examples:" />
            </p>
            <div className="space-y-8 text-sm font-mono bg-alt rounded p-12">
              <div>
                <div className="text-muted mb-4">
                  <Trans message="Example 1 (Simple):" />
                </div>
                <div>File: {'{filename}'}</div>
                <div>Size: {'{size}'}</div>
              </div>
              <div className="mt-12">
                <div className="text-muted mb-4">
                  <Trans message="Example 2 (Detailed):" />
                </div>
                <div>📁 {'{filename}'}</div>
                <div>📊 {'{size}'}</div>
                <div>🗂️ {'{type}'}</div>
                <div>📅 {'{date}'}</div>
                <div>━━━━━━━━━━━━━━</div>
                <div>Original: {'{original_name}'}</div>
              </div>
              <div className="mt-12">
                <div className="text-muted mb-4">
                  <Trans message="Example 3 (With Branding):" />
                </div>
                <div>🚀 Powered by YourBrand</div>
                <div>━━━━━━━━━━━━━━</div>
                <div>📄 File: {'{filename}'}</div>
                <div>💾 Size: {'{size}'}</div>
                <div>🕐 {'{date}'}</div>
              </div>
            </div>
          </div>
        }
      />
    </Fragment>
  );
}
