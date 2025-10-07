/**
 * Phase 8.1 & 8.2: Telegram URL Upload - Main Dialog
 * دیالوگ اصلی آپلود از URL با Progress Tracking
 */

import React, {useState} from 'react';
import {Trans} from '@ui/i18n/trans';
import {Dialog} from '@ui/overlays/dialog/dialog';
import {DialogHeader} from '@ui/overlays/dialog/dialog-header';
import {DialogBody} from '@ui/overlays/dialog/dialog-body';
import {useDialogContext} from '@ui/overlays/dialog/dialog-context';
import {Tabs} from '@ui/tabs/tabs';
import {TabList} from '@ui/tabs/tab-list';
import {Tab} from '@ui/tabs/tab';
import {TabPanel} from '@ui/tabs/tab-panels';
import {SingleUrlForm} from './single-url-form';
import {BulkUrlsForm} from './bulk-urls-form';
import {TelegramUploadProgress} from './telegram-upload-progress';
import {TelegramUploadTab} from './telegram-types';
import {uploadFromUrl, uploadBulkFromUrls} from './telegram-url-upload-api';
import {toast} from '@ui/toast/toast';
import {queryClient} from '@common/http/query-client';

export function TelegramUrlUploadDialog() {
  const {close} = useDialogContext();
  const [activeTab, setActiveTab] = useState<TelegramUploadTab>('single');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [uploadSessionId, setUploadSessionId] = useState<string | null>(null);

  const handleSingleSubmit = async (url: string, filename: string) => {
    setIsSubmitting(true);
    try {
      const response = await uploadFromUrl({
        url,
        name: filename,
      });

      // Phase 8.2: ذخیره session_id برای نمایش progress
      if (response.session_id) {
        setUploadSessionId(response.session_id);
      } else {
        // اگر session_id نداشت، یعنی بدون progress تکمیل شده
        handleUploadComplete(response.file_entry.name);
      }
    } catch (error: any) {
      toast.danger(
        error.message || 'خطا در آپلود فایل به تلگرام',
      );
      setIsSubmitting(false);
    }
  };

  const handleUploadComplete = async (filename?: string) => {
    toast.positive(
      `فایل ${filename || 'فایل'} با موفقیت به تلگرام آپلود شد`,
    );

    // Refresh drive list
    await queryClient.invalidateQueries({queryKey: ['drive']});

    // Reset state
    setIsSubmitting(false);
    setUploadSessionId(null);

    // بستن dialog بعد از 1 ثانیه
    setTimeout(() => {
      close();
    }, 1000);
  };

  const handleUploadError = (error: string) => {
    toast.danger(error);
    setIsSubmitting(false);
    setUploadSessionId(null);
  };

  const handleBulkSubmit = async (urls: string[]) => {
    setIsSubmitting(true);
    try {
      const response = await uploadBulkFromUrls({
        urls,
      });

      const {successful, failed, total} = response.summary;

      if (failed === 0) {
        toast.positive(
          `${successful} فایل با موفقیت به تلگرام آپلود شد`,
        );
      } else if (successful === 0) {
        toast.danger(
          'هیچ فایلی آپلود نشد. لطفاً URL‌ها را بررسی کنید',
        );
      } else {
        toast.danger(
          `${successful} از ${total} فایل آپلود شد. ${failed} فایل با خطا مواجه شد`,
        );
      }

      // Refresh drive list
      await queryClient.invalidateQueries({queryKey: ['drive']});

      // Show detailed results
      if (response.failed.length > 0) {
        console.log('Failed uploads:', response.failed);
      }

      close();
    } catch (error: any) {
      toast.danger(
        error.message || <Trans message="خطا در آپلود فایل‌ها به تلگرام" />,
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Dialog size="lg">
      <DialogHeader>
        <Trans message="آپلود از URL به تلگرام" />
      </DialogHeader>
      <DialogBody>
        {/* نمایش Progress اگر آپلود شروع شده */}
        {uploadSessionId ? (
          <div className="space-y-16">
            <div className="text-center text-sm text-muted mb-16">
              <Trans message="در حال آپلود..." />
            </div>
            <TelegramUploadProgress
              sessionId={uploadSessionId}
              onComplete={handleUploadComplete}
              onError={handleUploadError}
              showDetails={true}
              compact={false}
            />
          </div>
        ) : (
          <Tabs selectedTab={activeTab === 'single' ? 0 : 1} onTabChange={(index) => setActiveTab(index === 0 ? 'single' : 'bulk')}>
            <TabList className="mb-24">
              <Tab index={0}>
                <Trans message="آپلود تکی" />
              </Tab>
              <Tab index={1}>
                <Trans message="آپلود دسته‌ای" />
              </Tab>
            </TabList>

            <div className="pt-12">
              <TabPanel index={0}>
                <SingleUrlForm
                  onSubmit={handleSingleSubmit}
                  isSubmitting={isSubmitting}
                />
              </TabPanel>

              <TabPanel index={1}>
                <BulkUrlsForm
                  onSubmit={handleBulkSubmit}
                  isSubmitting={isSubmitting}
                />
              </TabPanel>
            </div>
          </Tabs>
        )}
      </DialogBody>
    </Dialog>
  );
}