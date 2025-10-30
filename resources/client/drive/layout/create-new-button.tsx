import {driveState, useDriveStore} from '../drive-store';
import {useDriveUploadQueue} from '../uploading/use-drive-upload-queue';
import {Button} from '@ui/buttons/button';
import {FileUploadIcon} from '@ui/icons/material/FileUpload';
import {Trans} from '@ui/i18n/trans';
import {CreateNewFolderIcon} from '@ui/icons/material/CreateNewFolder';
import {UploadFileIcon} from '@ui/icons/material/UploadFile';
import {DriveFolderUploadIcon} from '@ui/icons/material/DriveFolderUpload';
import React from 'react';
import {IconButton} from '@ui/buttons/icon-button';
import {AddIcon} from '@ui/icons/material/Add';
import {Menu, MenuItem, MenuTrigger} from '@ui/menu/menu-trigger';
import {openUploadWindow} from '@ui/utils/files/open-upload-window';
import {TelegramUrlUploadButton} from '../telegram-url-upload';
import {invalidateEntryQueries} from '../drive-query-keys';
import {useSettings} from '@ui/settings/use-settings';

interface CreateNewButtonProps {
  isCompact?: boolean;
  className?: string;
}
export function CreateNewButton({isCompact, className}: CreateNewButtonProps) {
  const activePage = useDriveStore(s => s.activePage);
  const {uploadFiles} = useDriveUploadQueue();
  const {uploads} = useSettings();
  
  // بررسی فعال بودن URL Upload از settings
   const isUrlUploadEnabled = uploads?.telegram_enable_url_upload !== false;

  const button = isCompact ? (
    <IconButton size="md" disabled={!activePage?.canUpload}>
      <AddIcon />
    </IconButton>
  ) : (
    <Button
      className={isUrlUploadEnabled ? "min-w-160" : "flex-1 min-w-160"}
      color="primary"
      variant="flat"
      size="sm"
      startIcon={<FileUploadIcon />}
      disabled={!activePage?.canUpload}
    >
      <Trans message="Upload" />
    </Button>
  );

  return (
    <div className={className}>
      <div className="flex gap-8 w-full">
        <div className={isUrlUploadEnabled ? "" : "flex-1"}>
        <MenuTrigger
          onItemSelected={async value => {
            if (value === 'uploadFiles') {
              uploadFiles(await openUploadWindow({multiple: true}));
            } else if (value === 'uploadFolder') {
              uploadFiles(await openUploadWindow({directory: true}));
            } else if (value === 'newFolder') {
              const activeFolder = driveState().activePage?.folder;
              driveState().setActiveActionDialog(
                'newFolder',
                activeFolder ? [activeFolder] : [],
              );
            }
          }}
        >
          {button}
          <Menu>
            <MenuItem value="uploadFiles" startIcon={<UploadFileIcon />}>
              <Trans message="Upload files" />
            </MenuItem>
            <MenuItem value="uploadFolder" startIcon={<DriveFolderUploadIcon />}>
              <Trans message="Upload folder" />
            </MenuItem>
            <MenuItem value="newFolder" startIcon={<CreateNewFolderIcon />}>
              <Trans message="Create folder" />
            </MenuItem>
          </Menu>
        </MenuTrigger>
         </div>
         
        {/* Telegram URL Upload Button */}
        {!isCompact && isUrlUploadEnabled && <TelegramUrlUploadButton variant="icon" size="md" />}
      </div>
    </div>
  );
}
