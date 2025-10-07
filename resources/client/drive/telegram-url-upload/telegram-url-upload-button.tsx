/**
 * Phase 8.1: Telegram URL Upload - Button Component
 * دکمه برای باز کردن دیالوگ آپلود از URL
 */

import React from 'react';
import {Trans} from '@ui/i18n/trans';
import {Button} from '@ui/buttons/button';
import {LinkIcon} from '@ui/icons/material/Link';
import {TelegramUrlUploadDialog} from './telegram-url-upload-dialog';
import {Tooltip} from '@ui/tooltip/tooltip';
import {IconButton} from '@ui/buttons/icon-button';
import {DialogTrigger} from '@ui/overlays/dialog/dialog-trigger';

interface TelegramUrlUploadButtonProps {
  variant?: 'button' | 'icon';
  size?: 'xs' | 'sm' | 'md';
}

/**
 * دکمه آپلود از URL
 * می‌تواند به صورت دکمه عادی یا آیکون نمایش داده شود
 */
export function TelegramUrlUploadButton({
  variant = 'button',
  size = 'sm',
}: TelegramUrlUploadButtonProps) {
  if (variant === 'icon') {
    return (
      <DialogTrigger type="modal">
        <Tooltip label={<Trans message="آپلود از URL به تلگرام" />}>
          <IconButton
            size={size}
            className="text-muted hover:text-primary"
          >
            <LinkIcon />
          </IconButton>
        </Tooltip>
        <TelegramUrlUploadDialog />
      </DialogTrigger>
    );
  }

  return (
    <DialogTrigger type="modal">
      <Button
        variant="outline"
        color="primary"
        size={size}
        startIcon={<LinkIcon />}
      >
        <Trans message="آپلود از URL" />
      </Button>
      <TelegramUrlUploadDialog />
    </DialogTrigger>
  );
}