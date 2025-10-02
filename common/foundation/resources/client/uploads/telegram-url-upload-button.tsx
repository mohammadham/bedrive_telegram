import {useState} from 'react';
import {Trans} from '@common/i18n/trans';
import {Button} from '@common/ui/buttons/button';
import {Tooltip} from '@common/ui/tooltip/tooltip';
import {LinkIcon} from '@common/icons/material/Link';
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
