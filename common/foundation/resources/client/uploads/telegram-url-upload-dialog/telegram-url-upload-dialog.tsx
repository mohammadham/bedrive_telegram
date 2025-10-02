import {useState} from 'react';
import {Trans} from '@ui/i18n/trans';
import {Dialog} from '@ui/overlays/dialog/dialog';
import {DialogHeader} from '@ui/overlays/dialog/dialog-header';
import {DialogBody} from '@ui/overlays/dialog/dialog-body';
import {DialogFooter} from '@ui/overlays/dialog/dialog-footer';
import {Button} from '@ui/buttons/button';
import {Tabs} from '@ui/tabs/tabs';
import {TabList} from '@ui/tabs/tab-list';
import {Tab} from '@ui/tabs/tab';
import {TabPanel} from '@ui/tabs/tab-panels';
import {toast} from '@ui/toast/toast';
import {SingleUrlForm} from './single-url-form';
import {BulkUrlsForm} from './bulk-urls-form';
import type {
  TelegramUrlUploadDialogProps,
  UploadMode,
  UrlValidation,
} from '../telegram-types';
import {
  validateUrl,
  uploadFromUrl,
  uploadBulkUrls,
  isValidUrl,
} from '../telegram-url-upload-api';

export function TelegramUrlUploadDialog({
  isOpen,
  onClose,
  onSuccess,
}: TelegramUrlUploadDialogProps) {
  const [mode, setMode] = useState<UploadMode>('single');
  
  // Single upload state
  const [url, setUrl] = useState('');
  const [name, setName] = useState('');
  const [caption, setCaption] = useState('');
  const [validation, setValidation] = useState<UrlValidation | null>(null);
  const [validating, setValidating] = useState(false);
  
  // Bulk upload state
  const [urls, setUrls] = useState<string[]>([]);
  const [bulkCaption, setBulkCaption] = useState('');
  
  // Upload state
  const [uploading, setUploading] = useState(false);

  const handleValidate = async () => {
    if (!url || !isValidUrl(url)) {
      return;
    }

    setValidating(true);
    try {
      const result = await validateUrl(url);
      setValidation(result);
      
      if (!result.valid || !result.can_upload) {
        toast.danger(result.reason || 'URL validation failed');
      }
    } catch (error: any) {
      toast.danger(error.message || 'Failed to validate URL');
      setValidation(null);
    } finally {
      setValidating(false);
    }
  };

  const handleSingleUpload = async () => {
    if (!url || !isValidUrl(url)) {
      toast.danger('Please enter a valid URL');
      return;
    }

    // Validate first if not already validated
    if (!validation) {
      await handleValidate();
      return;
    }

    if (!validation.can_upload) {
      toast.danger('This file cannot be uploaded');
      return;
    }

    setUploading(true);
    try {
      const result = await uploadFromUrl(url, {
        name: name || undefined,
        caption: caption || undefined,
      });

      toast.positive('File uploaded successfully!');
      
      if (onSuccess) {
        onSuccess(result);
      }
      
      handleClose();
    } catch (error: any) {
      toast.danger(error.message || 'Failed to upload file');
    } finally {
      setUploading(false);
    }
  };

  const handleBulkUpload = async () => {
    if (urls.length === 0) {
      toast.danger('Please enter at least one URL');
      return;
    }

    if (urls.length > 100) {
      toast.danger('Maximum 100 URLs allowed');
      return;
    }

    setUploading(true);
    try {
      const result = await uploadBulkUrls(urls, {
        caption: bulkCaption || undefined,
      });

      const message = `Uploaded ${result.successful} of ${result.total} files successfully`;
      
      if (result.failed > 0) {
        toast.danger(message);
      } else {
        toast.positive(message);
      }
      
      if (onSuccess) {
        onSuccess(result);
      }
      
      handleClose();
    } catch (error: any) {
      toast.danger(error.message || 'Failed to upload files');
    } finally {
      setUploading(false);
    }
  };

  const handleClose = () => {
    // Reset all state
    setUrl('');
    setName('');
    setCaption('');
    setValidation(null);
    setValidating(false);
    setUrls([]);
    setBulkCaption('');
    setUploading(false);
    setMode('single');
    
    onClose();
  };

  const canUpload = mode === 'single' 
    ? url && isValidUrl(url) && validation?.can_upload
    : urls.length > 0 && urls.length <= 100;

  return (
    <Dialog size="lg">
      <DialogHeader>
        <Trans message="Upload from URL" />
      </DialogHeader>

      <DialogBody>
        <Tabs selectedTab={mode === 'single' ? 0 : 1} onTabChange={(index) => setMode(index === 0 ? 'single' : 'bulk')}>
          <TabList>
            <Tab>
              <Trans message="Single File" />
            </Tab>
            <Tab>
              <Trans message="Multiple Files" />
            </Tab>
          </TabList>

          <TabPanel>
            <SingleUrlForm
              url={url}
              setUrl={setUrl}
              name={name}
              setName={setName}
              caption={caption}
              setCaption={setCaption}
              validation={validation}
              validating={validating}
              onValidate={handleValidate}
            />
          </TabPanel>

          <TabPanel>
            <BulkUrlsForm
              urls={urls}
              setUrls={setUrls}
              caption={bulkCaption}
              setCaption={setBulkCaption}
            />
          </TabPanel>
        </Tabs>
      </DialogBody>

      <DialogFooter>
        <Button onClick={handleClose} disabled={uploading}>
          <Trans message="Cancel" />
        </Button>
        <Button
          variant="flat"
          color="primary"
          onClick={mode === 'single' ? handleSingleUpload : handleBulkUpload}
          disabled={!canUpload || uploading}
        >
          {uploading ? (
            <Trans message="Uploading..." />
          ) : (
            <Trans message="Upload" />
          )}
        </Button>
      </DialogFooter>
    </Dialog>
  );
}
