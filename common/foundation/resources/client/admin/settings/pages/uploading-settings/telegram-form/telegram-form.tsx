import {Trans} from '@ui/i18n/trans';
import {FormTextField} from '@ui/forms/input-field/text-field/text-field';
import {Fragment} from 'react';
import {SectionHelper} from '@common/ui/other/section-helper';
import {Link} from 'react-router-dom';
import {TelegramStats} from './telegram-stats';
import {useSettings} from '@ui/settings/use-settings';
import {CheckCircleIcon} from '@ui/icons/material/CheckCircle';

export interface TelegramFormProps {
  isInvalid: boolean;
}

export function TelegramForm({isInvalid}: TelegramFormProps) {
  const settings = useSettings();
  const isTelegramActive = settings.uploads?.disk === 'telegram';

  return (
    <Fragment>
      {isTelegramActive && (
        <SectionHelper
          className="mb-30"
          color="positive"
          title={
            <div className="flex items-center gap-8">
              <CheckCircleIcon className="text-positive" size="sm" />
              <Trans message="Telegram Storage is Active" />
            </div>
          }
          description={
            <Trans message="Your files are currently being stored in Telegram. All new uploads will be automatically saved to your configured Telegram channel." />
          }
        />
      )}

      <SectionHelper
        className="mb-30"
        color="positive"
        description={
          <div>
            <p className="mb-10">
              <Trans message="Telegram storage provides unlimited free storage for files up to 2GB. Perfect for personal projects or startups." />
            </p>
            <p>
              <Trans 
                message="To get started: 1) Create a bot via <a>@BotFather</a>, 2) Create a private channel, 3) Add bot as admin with post/delete permissions." 
                values={{
                  a: chunks => (
                    <a 
                      href="https://t.me/BotFather" 
                      target="_blank" 
                      rel="noreferrer" 
                      className="text-primary underline"
                    >
                      {chunks}
                    </a>
                  ),
                }}
              />
            </p>
          </div>
        }
      />
      
      <FormTextField
        invalid={isInvalid}
        className="mb-30"
        name="server.storage_telegram_bot_token"
        label={<Trans message="Bot Token" />}
        placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
        description={
          <Trans message="Get this from @BotFather in Telegram. Required for all uploads." />
        }
        required
      />
      
      <FormTextField
        invalid={isInvalid}
        className="mb-30"
        name="server.storage_telegram_channel_id"
        label={<Trans message="Channel ID" />}
        placeholder="-1001234567890"
        description={
          <Trans message="Private channel ID where files will be stored. Must start with -100." />
        }
        required
      />

      <SectionHelper
        className="mb-30"
        color="neutral"
        title={<Trans message="Optional: Large File Support (50MB - 2GB)" />}
        description={
          <div>
            <Trans message="For files larger than 50MB, you need to configure User Account credentials. Get API credentials from my.telegram.org" />
          </div>
        }
      />
      
      <FormTextField
        invalid={isInvalid}
        className="mb-30"
        name="server.storage_telegram_api_id"
        label={<Trans message="API ID (Optional)" />}
        placeholder="12345678"
        description={
          <Trans message="Only needed for files > 50MB. Get from my.telegram.org" />
        }
        type="number"
      />
      
      <FormTextField
        invalid={isInvalid}
        className="mb-30"
        name="server.storage_telegram_api_hash"
        label={<Trans message="API Hash (Optional)" />}
        placeholder="0123456789abcdef0123456789abcdef"
        description={
          <Trans message="Only needed for files > 50MB. Get from my.telegram.org" />
        }
      />
      
      <FormTextField
        invalid={isInvalid}
        name="server.storage_telegram_phone"
        label={<Trans message="Phone Number (Optional)" />}
        placeholder="+989123456789"
        description={
          <Trans message="Only needed for files > 50MB. Include country code." />
        }
        type="tel"
      />

      <SectionHelper
        className="mt-30"
        color="warning"
        description={
          <div>
            <p className="mb-10 font-semibold">
              <Trans message="Important Notes:" />
            </p>
            <ul className="list-disc list-inside space-y-2">
              <li>
                <Trans message="Maximum file size: 2GB per file" />
              </li>
              <li>
                <Trans message="Channel must be private (not public)" />
              </li>
              <li>
                <Trans message="Bot must be admin with 'Post Messages' and 'Delete Messages' permissions" />
              </li>
              <li>
                <Trans message="Files < 50MB: Only Bot Token required" />
              </li>
              <li>
                <Trans message="Files 50MB-2GB: User Account credentials required" />
              </li>
            </ul>
          </div>
        }
      />

      <TelegramStats />
    </Fragment>
  );
}
