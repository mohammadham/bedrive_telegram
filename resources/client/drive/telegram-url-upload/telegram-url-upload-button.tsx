/**
 * Phase 8.1: Telegram URL Upload Button
 * دکمه برای باز کردن دیالوگ آپلود از URL
 */

import {Trans} from '@ui/i18n/trans';
import {Button} from '@ui/buttons/button';
import {LinkIcon} from '@ui/icons/material/Link';
import {IconButton} from '@ui/buttons/icon-button';
import {Tooltip} from '@ui/tooltip/tooltip';
import {TelegramUrlUploadDialog} from './telegram-url-upload-dialog';

interface TelegramUrlUploadButtonProps {
  /**
   * نوع نمایش دکمه
   * button: دکمه با متن
   * icon: فقط آیکون با tooltip
   */
  variant?: 'button' | 'icon';

  /**
   * اندازه دکمه
   */
  size?: 'xs' | 'sm' | 'md' | 'lg';

  /**
   * کلاس CSS اضافی
   */
  className?: string;
}

export function TelegramUrlUploadButton({
  variant = 'button',
  size = 'sm',
  className,
}: TelegramUrlUploadButtonProps) {
  if (variant === 'icon') {
    return (
      <TelegramUrlUploadDialog
        trigger={
          <Tooltip label={<Trans message="آپلود از URL (تلگرام)" />}>
            <IconButton size={size} className={className}>
              <LinkIcon />
            </IconButton>
          </Tooltip>
        }
      />
    );
  }

  return (
    <TelegramUrlUploadDialog
      trigger={
        <Button
          variant="outline"
          size={size}
          className={className}
          startIcon={<LinkIcon />}
        >
          <Trans message="آپلود از URL" />
        </Button>
      }
    />
  );
}
