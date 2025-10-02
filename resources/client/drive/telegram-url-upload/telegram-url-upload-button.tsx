/**
 * Phase 8.1: Telegram URL Upload - Button Component
 * دکمه برای باز کردن دیالوگ آپلود از URL
 */

import React, {useState} from 'react';
import {Trans} from '@ui/i18n/trans';
import {Button} from '@ui/buttons/button';
import {LinkIcon} from '@ui/icons/material/Link';
import {TelegramUrlUploadDialog} from './telegram-url-upload-dialog';
import {Tooltip} from '@ui/tooltip/tooltip';
import {IconButton} from '@ui/buttons/icon-button';

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
  const [isDialogOpen, setIsDialogOpen] = useState(false);

  if (variant === 'icon') {
    return (
      <>
        <Tooltip label={<Trans message="آپلود از URL به تلگرام" />}>
          <IconButton
            size={size}
            onClick={() => setIsDialogOpen(true)}
            className="text-muted hover:text-primary"
          >
            <LinkIcon />
          </IconButton>
        </Tooltip>
        <TelegramUrlUploadDialog
          isOpen={isDialogOpen}
          onClose={() => setIsDialogOpen(false)}
        />
      </>
    );
  }

  return (
    <>
      <Button
        variant="outline"
        color="primary"
        size={size}
        startIcon={<LinkIcon />}
        onClick={() => setIsDialogOpen(true)}
      >
        <Trans message="آپلود از URL" />
      </Button>
      <TelegramUrlUploadDialog
        isOpen={isDialogOpen}
        onClose={() => setIsDialogOpen(false)}
      />
    </>
  );
}