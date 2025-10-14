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
import {Button} from '@ui/buttons/button';
import {ButtonGroup} from '@ui/buttons/button-group';
import {SingleUrlForm} from './single-url-form';
import {BulkUrlsForm} from './bulk-urls-form';
import {TelegramUploadProgress} from './telegram-upload-progress';
import {TelegramUploadTab} from './telegram-types';
import {uploadFromUrl, uploadBulkFromUrls} from './telegram-url-upload-api';
import {toast} from '@ui/toast/toast';
import {invalidateEntryQueries} from '../drive-query-keys';

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

      // Phase 8.2: دریافت session_id برای tracking
      if (response.session_id) {
        // نمایش toast موفقیت
        toast.positive(
          `آپلود فایل "${filename}" در حال انجام است و در پس‌زمینه ادامه می‌یابد`,
        );
        
        // Refresh drive list
        await invalidateEntryQueries();
        
        // بستن فوری dialog
        close();
      } else if (response.file_entry) {
        // اگر بدون session_id تکمیل شده (sync mode)
        handleUploadComplete(response.file_entry.name);
      }
    } catch (error: any) {
      toast.danger(
        error.message || 'خطا در شروع آپلود فایل به تلگرام',
      );
      setIsSubmitting(false);
    }
  };

  const handleUploadComplete = async (filename?: string) => {
    toast.positive(
      `فایل ${filename || 'فایل'} با موفقیت به تلگرام آپلود شد`,
    );

    // Refresh drive list - استفاده از invalidateEntryQueries برای به‌روزرسانی تمام query های drive
    await invalidateEntryQueries();

    // Reset state
    setIsSubmitting(false);
    setUploadSessionId(null);

    // بستن dialog بعد از 1.5 ثانیه (زمان بیشتر برای نمایش toast)
    setTimeout(() => {
      close();
    }, 1500);
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

      // Refresh drive list - استفاده از invalidateEntryQueries
      await invalidateEntryQueries();

      // Show detailed results
      if (response.failed.length > 0) {
        console.log('Failed uploads:', response.failed);
      }

      // بستن dialog بعد از 1 ثانیه
      setTimeout(() => {
        close();
      }, 1000);
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
        <Trans message="Upload from URL to Telegram" />
      </DialogHeader>
      <DialogBody>
        {/* نمایش Progress اگر آپلود شروع شده */}
        {uploadSessionId ? (
          <div className="space-y-16">
            <div className="text-center text-sm text-muted mb-16">
              <Trans message="Uploading..." />
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
          <div className="space-y-24">
            {/* Tab Buttons */}
            <ButtonGroup variant="outline" size="sm" className="w-full">
              <Button
                className="flex-1"
                color={activeTab === 'single' ? 'primary' : 'paper'}
                variant={activeTab === 'single' ? 'flat' : 'outline'}
                onClick={() => setActiveTab('single')}
              >
                <Trans message="Single Upload" />
              </Button>
              <Button
                className="flex-1"
                color={activeTab === 'bulk' ? 'primary' : 'paper'}
                variant={activeTab === 'bulk' ? 'flat' : 'outline'}
                onClick={() => setActiveTab('bulk')}
              >
                <Trans message="Bulk Upload" />
              </Button>
            </ButtonGroup>

            {/* Tab Content */}
            <div className="pt-12">
              {activeTab === 'single' ? (
                <SingleUrlForm
                  onSubmit={handleSingleSubmit}
                  isSubmitting={isSubmitting}
                />
              ) : (
                <BulkUrlsForm
                  onSubmit={handleBulkSubmit}
                  isSubmitting={isSubmitting}
                />
              )}
            </div>
          </div>
        )}
      </DialogBody>
    </Dialog>
  );
}