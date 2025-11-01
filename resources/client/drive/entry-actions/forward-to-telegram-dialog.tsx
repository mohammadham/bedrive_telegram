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

interface ForwardToTelegramDialogProps {
  entry: DriveEntry;
}

interface ForwardFormData {
  targetId: string;
}

export function ForwardToTelegramDialog({
  entry,
}: ForwardToTelegramDialogProps) {
  const {close} = useDialogContext();
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
      // toast.positive(
      //   <Trans
      //     message="File forwarded to Telegram successfully"
      //     values={{name: entry.name}}
      //   />,
      // );
      toast.positive('File forwarded to Telegram successfully');
      close();
    },
    onError: err => {
      showHttpErrorToast(err);
    },
  });

  return (
    <Dialog size="sm">
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
