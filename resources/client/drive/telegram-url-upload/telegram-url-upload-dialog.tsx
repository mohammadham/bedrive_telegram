import {DialogTrigger} from '@ui/overlays/dialog/dialog-trigger';
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
import {toast} from '@ui/toast/toast';
import {queryClient} from '@common/http/query-client';
import {DriveQueryKeys, invalidateEntryQueries} from '../drive-query-keys';
import {driveState} from '../drive-store';
import {useState} from 'react';
import {uploadFromUrl, uploadBulkFromUrls} from './telegram-url-upload-api';

interface TelegramUrlUploadDialogProps {
  trigger?: React.ReactElement;
}

export function TelegramUrlUploadDialog({
  trigger,
}: TelegramUrlUploadDialogProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSingleSubmit = async (url: string, filename: string) => {
    setIsSubmitting(true);
    try {
      const response = await uploadFromUrl({url, name: filename});
        // ✅ دیباگ: لاگ session ID
        console.log('✅ Upload started:', response);
      if (response.success && response.data?.session_id) {
        // ✅ دیباگ: لاگ session ID
        console.log('✅ Upload started:', response.data.session_id);
        toast.positive(
          `تلگرام: آپلود در پس‌زمینه شروع شد (${response.data.session_id.substring(0, 8)}...)`,
        );

        // Open Telegram upload queue to show progress
        // ✅ فوراً queue panel را باز کن
        console.log('📂 Opening Telegram upload queue panel');
        driveState().setTelegramUploadQueueIsOpen(true);

        // Invalidate queries to refresh file list after completion
        // (این در TelegramUploadProgress خودکار انجام می‌شود)
        // ✅ چک کردن state بعد از set
        setTimeout(() => {
          console.log('📊 Queue panel state:', driveState().telegramUploadQueueIsOpen);
        }, 100);
        // Close dialog
        setIsOpen(false);
        // Refresh file list after delay
      setTimeout(() => {
        invalidateEntryQueries();
      }, 2000);
      } else {
        toast.danger(response.message || 'خطا در شروع آپلود');
      }
    } catch (error: any) {
      toast.danger(error.message || 'خطا در ارسال درخواست');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleBulkSubmit = async (urls: {url: string; filename: string}[]) => {
    setIsSubmitting(true);
    try {
      const response = await uploadBulkFromUrls({urls});

      if (response.success && response.data?.results) {
        const successCount = response.data.results.filter(
          (r: any) => r.success,
        ).length;
        // ✅ دیباگ
        console.log('✅ Bulk upload started:', successCount, 'files');
        toast.positive(
          `${successCount} فایل در پس‌زمینه در حال آپلود هستند`,
        );

        // Open Telegram upload queue
        // ✅ فوراً queue panel را باز کن
        console.log('📂 Opening Telegram upload queue panel');
        driveState().setTelegramUploadQueueIsOpen(true);

        // Close dialog
        setIsOpen(false);
      } else {
        toast.danger(response.message || 'خطا در شروع آپلود');
      }
    } catch (error: any) {
      toast.danger(error.message || 'خطا در ارسال درخواست');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <DialogTrigger
      type="modal"
      isOpen={isOpen}
      onOpenChange={setIsOpen}
      triggerOnContextMenu={false}
    >
      {trigger}
      <Dialog size="lg">
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
                <SingleUrlForm
                  onSubmit={handleSingleSubmit}
                  isSubmitting={isSubmitting}
                />
              </TabPanel>
              <TabPanel>
                <BulkUrlsForm
                  onSubmit={handleBulkSubmit}
                  isSubmitting={isSubmitting}
                />
              </TabPanel>
            </TabPanels>
          </Tabs>
        </DialogBody>
      </Dialog>
    </DialogTrigger>
  );
}
