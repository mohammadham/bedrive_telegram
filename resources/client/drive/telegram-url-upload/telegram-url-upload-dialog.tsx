import {Dialog} from '@ui/overlays/dialog/dialog';
import {DialogHeader} from '@ui/overlays/dialog/dialog-header';
import {DialogBody} from '@ui/overlays/dialog/dialog-body';
import {Trans} from '@ui/i18n/trans';
import {Tabs} from '@ui/tabs/tabs';
import {TabList} from '@ui/tabs/tab-list';
import {Tab} from '@ui/tabs/tab';
import {TabPanel, TabPanels} from '@ui/tabs/tab-panels';
import {SingleUrlForm} from './single-url-form';
import {BulkUrlsForm} from './bulk-urls-form';
import {DialogProps} from '@ui/overlays/dialog/dialog';
import {toast} from '@ui/toast/toast';
import {message} from '@ui/i18n/message';
import {queryClient} from '@common/http/query-client';
import {DriveQueryKeys} from '../drive-query-keys';
import {driveState} from '../drive-store';

export function TelegramUrlUploadDialog({...dialogProps}: DialogProps) {
  const handleSuccess = (sessionId: string) => {
    // Show background upload notification
    toast(
      message('تلگرام: آپلود در پس‌زمینه شروع شد (Session: {sessionId})'),
      {values: {sessionId: sessionId.substring(0, 8)}},
    );

    // Open upload queue to show progress
    driveState().setUploadQueueIsOpen(true);

    // Invalidate queries to refresh file list
    queryClient.invalidateQueries({queryKey: DriveQueryKeys.fetchFolder});
    queryClient.invalidateQueries({queryKey: DriveQueryKeys.fetchStorageSummary});

    // Close dialog immediately
    dialogProps.onClose?.();
  };

  return (
    <Dialog size="lg" {...dialogProps}>
      <DialogHeader>
        <Trans message="آپلود از URL به تلگرام" />
      </DialogHeader>
      <DialogBody>
        <Tabs>
          <TabList>
            <Tab>
              <Trans message="آپلود تکی" />
            </Tab>
            <Tab>
              <Trans message="آپلود دسته‌ای" />
            </Tab>
          </TabList>
          <TabPanels className="pt-20">
            <TabPanel>
              <SingleUrlForm onSuccess={handleSuccess} />
            </TabPanel>
            <TabPanel>
              <BulkUrlsForm onSuccess={handleSuccess} />
            </TabPanel>
          </TabPanels>
        </Tabs>
      </DialogBody>
    </Dialog>
  );
}
