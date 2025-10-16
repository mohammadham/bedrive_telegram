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
import {TelegramUploadProgress} from './telegram-upload-progress';
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
  const [activeSessionId, setActiveSessionId] = useState<string | null>(null);
  const [showProgress, setShowProgress] = useState(false);

  const handleProgressComplete = () => {
    setShowProgress(false);
    setActiveSessionId(null);
    // Refresh file list
    invalidateEntryQueries();
  };

  const handleSingleSubmit = async (url: string, filename: string) => {
    setIsSubmitting(true);
    try {
      const response = await uploadFromUrl({url, name: filename});
        // ✅ دیباگ: لاگ session ID
        console.log('✅ Upload started:', response);
      if (response.success && response.data?.session_id) {
        // ✅ ذخیره session_id برای نمایش progress
        setActiveSessionId(response.data.session_id);
        setShowProgress(true);
        
        // ✅ بستن دیالوگ فوری
        setIsOpen(false);
        
        // Open Telegram upload queue to show progress
        driveState().setTelegramUploadQueueIsOpen(true);
        
        // Show success message
        toast.positive(
          'File upload started in background. You\'ll see it in the list when complete.'
        );
        
        // Invalidate queries to refresh file list
        invalidateEntryQueries();
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
        
        // ✅ بستن دیالوگ فوری
        setIsOpen(false);
        
        // Open Telegram upload queue
        driveState().setTelegramUploadQueueIsOpen(true);
        
        // Show success message
        toast.positive(
          `${successCount} files upload started in background. You'll see them in the list when complete.`
        );
        
        // Invalidate queries to refresh file list
        invalidateEntryQueries();
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
    <>
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
      
      {showProgress && activeSessionId && (
        <TelegramUploadProgress
          sessionId={activeSessionId}
          onComplete={handleProgressComplete}
        />
      )}
    </>
  );
}
