import {Dialog} from '@ui/overlays/dialog/dialog';
import {DialogHeader} from '@ui/overlays/dialog/dialog-header';
import {Trans} from '@ui/i18n/trans';
import {DialogBody} from '@ui/overlays/dialog/dialog-body';
import {useForm} from 'react-hook-form';
import {Form} from '@ui/forms/form';
import {FormTextField} from '@ui/forms/input-field/text-field/text-field';
import {DialogFooter} from '@ui/overlays/dialog/dialog-footer';
import {Button} from '@ui/buttons/button';
import {useDialogContext} from '@ui/overlays/dialog/dialog-context';
import {DriveEntry} from '../files/drive-entry';
import {useMutation} from '@tanstack/react-query';
import {apiClient} from '@common/http/query-client';
import {toast} from '@ui/toast/toast';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useState} from 'react';
import {Accordion, AccordionItem} from '@ui/accordion/accordion';
import {KeyboardArrowDownIcon} from '@ui/icons/material/KeyboardArrowDown';

interface ForwardToTelegramDialogProps {
  entry: DriveEntry;
}

interface ForwardFormData {
  targetId: string;
}

interface TelegramError {
  message: string;
  error_code?: string;
}

export function ForwardToTelegramDialog({
  entry,
}: ForwardToTelegramDialogProps) {
  const {close} = useDialogContext();
  const [errorInfo, setErrorInfo] = useState<TelegramError | null>(null);
  
  const form = useForm<ForwardFormData>({
    defaultValues: {
      targetId: '',
    },
  });

  const forwardMutation = useMutation({
    mutationFn: (data: ForwardFormData) =>
      apiClient.post(`user/telegram/forward/${entry.id}`, {
        target_id: data.targetId,
      }),
    onSuccess: () => {
      toast.positive('File forwarded to Telegram successfully');
      setErrorInfo(null);
      close();
    },
    onError: (err: any) => {
      const errorData = err.response?.data;
      const errorMessage = errorData?.message || 'Failed to forward file';
      const errorCode = errorData?.error_code;
      
      setErrorInfo({
        message: errorMessage,
        error_code: errorCode,
      });
      
      // Show toast with simple message
      toast.danger(errorMessage);
    },
  });

  return (
    <Dialog size="md">
      <DialogHeader>
        <Trans message="Forward to Telegram" />
      </DialogHeader>
      <DialogBody>
        <Form
          form={form}
          onSubmit={values => {
            forwardMutation.mutate(values);
          }}
        >
          <div className="mb-16 text-sm text-muted">
            <Trans
              message='Forwarding: ":name"'
              values={{name: entry.name}}
            />
          </div>

          <FormTextField
            name="targetId"
            label={<Trans message="Telegram ID or Username" />}
            placeholder="-1001234567890 or @channelname"
            description={
              <Trans message="Enter the Telegram channel ID, username, group ID, or user ID where you want to forward this file." />
            }
            required
            autoFocus
          />

          <div className="mt-16 text-xs text-muted space-y-4">
            <div>
              <strong>
                <Trans message="Supported Formats:" />
              </strong>
            </div>
            <div>• <strong>Channel ID:</strong> -1001234567890</div>
            <div>• <strong>Channel Username:</strong> @channelname or channelname</div>
            <div>• <strong>Group ID:</strong> -1009876543210</div>
            <div>• <strong>User ID:</strong> 123456789</div>
            <div>• <strong>User Username:</strong> @username or username</div>
          </div>

          {/* Error Help Section */}
          {errorInfo && (
            <div className="mt-24">
              <ErrorHelpSection errorCode={errorInfo.error_code} />
            </div>
          )}
        </Form>
      </DialogBody>
      <DialogFooter>
        <Button onClick={close} variant="text">
          <Trans message="Cancel" />
        </Button>
        <Button
          variant="flat"
          color="primary"
          onClick={form.handleSubmit(values => {
            forwardMutation.mutate(values);
          })}
          disabled={forwardMutation.isPending}
        >
          <Trans message="Forward" />
        </Button>
      </DialogFooter>
    </Dialog>
  );
}

// Error Help Component with Collapsible Solutions
function ErrorHelpSection({errorCode}: {errorCode?: string}) {
  if (!errorCode) return null;

  const errorHelp = getErrorHelp(errorCode);
  if (!errorHelp) return null;

  return (
    <div className="bg-danger-lighter/10 border border-danger-lighter rounded-md p-16">
      <div className="text-sm font-semibold text-danger mb-8 flex items-center gap-8">
        <span className="text-lg">⚠️</span>
        <Trans message="Having trouble?" />
      </div>
      
      <Accordion variant="outline">
        <AccordionItem
          label={<Trans message="How to fix this issue" />}
          chevronPosition="end"
          startIcon={<KeyboardArrowDownIcon />}
        >
          <div className="text-sm space-y-12 pt-12">
            {errorHelp.solutions.map((solution, index) => (
              <div key={index} className="space-y-6">
                <div className="font-medium text-main">{solution.title}</div>
                <div className="text-muted pl-16">{solution.description}</div>
              </div>
            ))}
          </div>
        </AccordionItem>
      </Accordion>
    </div>
  );
}

// Error Help Data
function getErrorHelp(errorCode: string) {
  const helpMap: Record<
    string,
    {solutions: Array<{title: string; description: string}>}
  > = {
    TARGET_NOT_FOUND: {
      solutions: [
        {
          title: 'For Private Channels/Groups:',
          description:
            'You must use the numeric Channel ID (starts with -100). Add your bot as an admin to the channel first, then get the ID using @userinfobot or similar tools.',
        },
        {
          title: 'For Public Channels:',
          description:
            'Make sure the username is correct and the channel is public. You can use either @channelname or the numeric ID.',
        },
        {
          title: 'For Users:',
          description:
            'The user must have started a chat with your bot first. They also need to enable "Allow forwarding" in their privacy settings.',
        },
      ],
    },
    NO_PERMISSION: {
      solutions: [
        {
          title: 'Add Bot as Admin:',
          description:
            'Go to your channel/group settings and add the bot as an administrator with "Post Messages" permission.',
        },
        {
          title: 'Check Bot Permissions:',
          description:
            'Ensure the bot has permission to send messages and forward content in the target channel or group.',
        },
      ],
    },
    USER_BLOCKED_BOT: {
      solutions: [
        {
          title: 'User Must Start Chat:',
          description:
            'The user needs to open a chat with your bot and press the "Start" button or send any message first.',
        },
        {
          title: 'Check Privacy Settings:',
          description:
            'Ask the user to check their Telegram privacy settings and ensure they allow messages from bots.',
        },
      ],
    },
    USERNAME_NOT_FOUND: {
      solutions: [
        {
          title: 'Verify Username:',
          description:
            'Check that the username is spelled correctly. Usernames are case-insensitive but must match exactly.',
        },
        {
          title: 'Use Numeric ID Instead:',
          description:
            'If the username doesn\'t work, try using the numeric Channel ID or User ID instead.',
        },
      ],
    },
    BOT_NOT_MEMBER: {
      solutions: [
        {
          title: 'Add Bot to Channel:',
          description:
            'Open your channel/group settings and add the bot as a member or administrator.',
        },
        {
          title: 'Grant Posting Rights:',
          description:
            'After adding the bot, make sure it has permission to post messages in the channel.',
        },
      ],
    },
    INVALID_FORMAT: {
      solutions: [
        {
          title: 'Check ID Format:',
          description:
            'Channel IDs should start with -100 followed by numbers. User IDs are positive numbers. Usernames should be 5-32 characters.',
        },
        {
          title: 'Examples:',
          description:
            'Channel: -1001234567890 or @mychannel | User: 123456789 or @username',
        },
      ],
    },
    FILE_NOT_ON_TELEGRAM: {
      solutions: [
        {
          title: 'File Not Uploaded:',
          description:
            'This file is not stored on Telegram yet. Only files uploaded to Telegram storage can be forwarded.',
        },
        {
          title: 'Upload to Telegram:',
          description:
            'Please use the "Upload to Telegram" feature first, then try forwarding again.',
        },
      ],
    },
  };

  return helpMap[errorCode];
}
