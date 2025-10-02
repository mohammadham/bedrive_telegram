import {useState} from 'react';
import {Trans} from '@ui/i18n/trans';
import {Button} from '@ui/buttons/button';
import {Tooltip} from '@ui/tooltip/tooltip';
import {LinkIcon} from '@ui/icons/material/Link';
import {TelegramUrlUploadDialog} from './telegram-url-upload-dialog/telegram-url-upload-dialog';
import type {TelegramUrlUploadButtonProps} from './telegram-types';

export function TelegramUrlUploadButton({
  onSuccess,
}: TelegramUrlUploadButtonProps) {
  const [isDialogOpen, setIsDialogOpen] = useState(false);

  const handleSuccess = (result: any) => {
    if (onSuccess) {
      onSuccess(result);
    }
  };

  return (
    <>
      <Tooltip label={<Trans message="Upload from URL" />}>
        <Button
          variant="outline"
          size="sm"
          startIcon={<LinkIcon />}
          onClick={() => setIsDialogOpen(true)}
        >
          <Trans message="URL Upload" />
        </Button>
      </Tooltip>

      <TelegramUrlUploadDialog
        isOpen={isDialogOpen}
        onClose={() => setIsDialogOpen(false)}
        onSuccess={handleSuccess}
      />
    </>
  );
}
