import {FileEntry} from '@common/uploads/file-entry';
import {IconButton} from '@ui/buttons/icon-button';
import {CloudUploadIcon} from '@ui/icons/material/CloudUpload';
import {ForwardIcon} from '@ui/icons/material/Forward';
import {SendIcon} from '@ui/icons/material/Send';
import {Tooltip} from '@ui/tooltip/tooltip';
import {Trans} from '@ui/i18n/trans';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {apiClient} from '@common/http/query-client';
import {toast} from '@ui/toast/toast';
import {DialogTrigger} from '@ui/overlays/dialog/dialog-trigger';
import {Dialog} from '@ui/overlays/dialog/dialog';
import {DialogHeader} from '@ui/overlays/dialog/dialog-header';
import {DialogBody} from '@ui/overlays/dialog/dialog-body';
import {DialogFooter} from '@ui/overlays/dialog/dialog-footer';
import {Button} from '@ui/buttons/button';
import {useState} from 'react';
import {FormTextField} from '@ui/forms/input-field/text-field/text-field';
import {useSettings} from '@ui/settings/use-settings';
import {Menu, MenuItem, MenuTrigger} from '@ui/menu/menu-trigger';
import {useAuth} from '@common/auth/use-auth';

interface TelegramSettings {
  auto_forward: boolean;
  forward_target: string | null;
  driver_enabled: boolean;
}

interface TelegramFileActionsProps {
  file: FileEntry;
}

export function TelegramFileActions({file}: TelegramFileActionsProps) {
  const settings = useSettings();
  const isUploadedToTelegram = !!file.telegram_metadata;
  const isTelegramDriver = settings.uploads?.disk === 'telegram';

  // Don't show if Telegram is not the active driver
  if (!isTelegramDriver) {
    return null;
  }

  return (
    <div className="flex items-center gap-4">
      {!isUploadedToTelegram && <UploadToTelegramButton file={file} />}
      {isUploadedToTelegram && <ForwardFileMenu file={file} />}
    </div>
  );
}

function UploadToTelegramButton({file}: {file: FileEntry}) {
  const queryClient = useQueryClient();

  const uploadMutation = useMutation({
    mutationFn: () => uploadToTelegram(file.id),
    onSuccess: () => {
      toast(Trans({message: 'File uploaded to Telegram successfully'}));
      queryClient.invalidateQueries({queryKey: ['file-entries']});
    },
    onError: (error: any) => {
      const message =
        error.response?.data?.message || 'Failed to upload to Telegram';
      toast.danger(message);
    },
  });

  return (
    <Tooltip label={<Trans message="Upload to Telegram" />}>
      <IconButton
        size="sm"
        onClick={() => uploadMutation.mutate()}
        disabled={uploadMutation.isPending}
      >
        <CloudUploadIcon />
      </IconButton>
    </Tooltip>
  );
}

function ForwardFileMenu({file}: {file: FileEntry}) {
  const {user} = useAuth();
  
  // Fetch user's saved Telegram settings
  const {data: telegramSettings} = useQuery<TelegramSettings>({
    queryKey: ['user-telegram-settings'],
    queryFn: () => fetchTelegramSettings(),
    enabled: !!user,
  });

  const hasSavedTarget = telegramSettings?.forward_target;

  return (
    <MenuTrigger>
      <Tooltip label={<Trans message="Forward to Telegram" />}>
        <IconButton size="sm">
          <ForwardIcon />
        </IconButton>
      </Tooltip>
      <Menu>
        {hasSavedTarget && (
          <MenuItem
            value="saved"
            startIcon={<SendIcon />}
            onSelected={() => {}}
          >
            <ForwardToSavedTarget
              file={file}
              targetId={telegramSettings.forward_target!}
            />
          </MenuItem>
        )}
        <MenuItem value="custom" startIcon={<ForwardIcon />}>
          <ForwardToCustomTarget file={file} />
        </MenuItem>
      </Menu>
    </MenuTrigger>
  );
}

function ForwardToSavedTarget({
  file,
  targetId,
}: {
  file: FileEntry;
  targetId: string;
}) {
  const forwardMutation = useMutation({
    mutationFn: () => forwardFile(file.id, targetId),
    onSuccess: () => {
      toast(Trans({message: 'File forwarded to saved target successfully'}));
    },
    onError: (error: any) => {
      const message =
        error.response?.data?.message || 'Failed to forward file';
      toast.danger(message);
    },
  });

  return (
    <button
      onClick={e => {
        e.stopPropagation();
        forwardMutation.mutate();
      }}
      className="w-full text-left"
      disabled={forwardMutation.isPending}
    >
      <Trans
        message="Forward to saved ID (:id)"
        values={{id: targetId.substring(0, 15) + '...'}}
      />
    </button>
  );
}

function ForwardToCustomTarget({file}: {file: FileEntry}) {
  const [targetId, setTargetId] = useState('');

  const forwardMutation = useMutation({
    mutationFn: (target: string) => forwardFile(file.id, target),
    onSuccess: () => {
      toast(Trans({message: 'File forwarded successfully'}));
      setTargetId('');
    },
    onError: (error: any) => {
      const message =
        error.response?.data?.message || 'Failed to forward file';
      toast.danger(message);
    },
  });

  return (
    <DialogTrigger type="modal">
      <button className="w-full text-left">
        <Trans message="Forward to custom ID..." />
      </button>
      <Dialog>
        <DialogHeader>
          <Trans message="Forward File to Telegram" />
        </DialogHeader>
        <DialogBody>
          <p className="text-sm text-muted mb-16">
            <Trans message="Enter the Telegram ID (channel, group, or user) where you want to forward this file." />
          </p>
          <FormTextField
            value={targetId}
            onChange={e => setTargetId(e.target.value)}
            label={<Trans message="Telegram ID" />}
            placeholder="-1001234567890 or @username"
            required
            autoFocus
          />
          <div className="mt-16 text-xs text-muted">
            <ul className="list-disc list-inside space-y-4">
              <li>
                <Trans message="For channels/groups: -1001234567890" />
              </li>
              <li>
                <Trans message="For users: @username or numeric ID" />
              </li>
              <li>
                <Trans message="Bot must have permission to send messages" />
              </li>
            </ul>
          </div>
        </DialogBody>
        <DialogFooter>
          <Button
            onClick={close => {
              if (targetId.trim()) {
                forwardMutation.mutate(targetId, {
                  onSuccess: () => close(),
                });
              }
            }}
            variant="flat"
            color="primary"
            disabled={!targetId.trim() || forwardMutation.isPending}
          >
            <Trans message="Forward" />
          </Button>
        </DialogFooter>
      </Dialog>
    </DialogTrigger>
  );
}

async function fetchTelegramSettings(): Promise<TelegramSettings> {
  return apiClient.get('user/telegram/settings').then(r => r.data);
}

async function uploadToTelegram(fileId: number): Promise<void> {
  return apiClient.post(`user/telegram/upload/${fileId}`).then(r => r.data);
}

async function forwardFile(fileId: number, targetId: string): Promise<void> {
  return apiClient
    .post(`user/telegram/forward/${fileId}`, {target_id: targetId})
    .then(r => r.data);
}
