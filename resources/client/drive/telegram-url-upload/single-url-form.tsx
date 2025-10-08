/**
 * Phase 8.1: Telegram URL Upload - Single URL Form
 * فرم آپلود تکی از URL
 */

import React, {useState, useEffect} from 'react';
import {Trans} from '@ui/i18n/trans';
import {TextField} from '@ui/forms/input-field/text-field/text-field';
import {Button} from '@ui/buttons/button';
import {ProgressCircle} from '@ui/progress/progress-circle';
import {LinkIcon} from '@ui/icons/material/Link';
import {CloudUploadIcon} from '@ui/icons/material/CloudUpload';
import {ErrorIcon} from '@ui/icons/material/Error';
import {CheckCircleIcon} from '@ui/icons/material/CheckCircle';
import {VerifiedIcon} from '@ui/icons/material/Verified';
import {
  validateUrl,
  previewUrl,
  extractFilenameFromUrl,
  formatFileSize,
} from './telegram-url-upload-api';
import {TelegramUrlPreview} from './telegram-types';

interface SingleUrlFormProps {
  onSubmit: (url: string, filename: string) => void;
  isSubmitting: boolean;
}

export function SingleUrlForm({onSubmit, isSubmitting}: SingleUrlFormProps) {
  const [url, setUrl] = useState('');
  const [filename, setFilename] = useState('');
  const [urlError, setUrlError] = useState('');
  const [isLoadingPreview, setIsLoadingPreview] = useState(false);
  const [preview, setPreview] = useState<TelegramUrlPreview | null>(null);
  const [isValidated, setIsValidated] = useState(false);

  // Reset preview وقتی URL تغییر می‌کند
  useEffect(() => {
    setPreview(null);
    setUrlError('');
    setIsValidated(false);
  }, [url]);

  const handleValidateClick = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!url.trim()) {
      setUrlError('لطفاً URL را وارد کنید');
      return;
    }

    const validation = validateUrl(url);
    if (!validation.isValid) {
      setUrlError(validation.error || '');
      return;
    }

    // Auto-fill filename
    if (!filename) {
      setFilename(extractFilenameFromUrl(url));
    }

    // Load preview
    await loadPreview();
  };

  const loadPreview = async () => {
    setIsLoadingPreview(true);
    setIsValidated(false);
    try {
      const previewData = await previewUrl(url);
      setPreview(previewData);
      
      if (!previewData.is_accessible) {
        setUrlError(previewData.error || 'فایل قابل دسترسی نیست');
        setIsValidated(false);
      } else {
        setUrlError('');
        setIsValidated(true);
        // Update filename if available
        if (previewData.filename && !filename) {
          setFilename(previewData.filename);
        }
      }
    } catch (error: any) {
      setUrlError(error.message || 'خطا در بارگذاری اطلاعات URL');
      setIsValidated(false);
    } finally {
      setIsLoadingPreview(false);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const validation = validateUrl(url);
    if (!validation.isValid) {
      setUrlError(validation.error || '');
      return;
    }

    if (!filename.trim()) {
      setFilename(extractFilenameFromUrl(url));
    }

    onSubmit(url.trim(), filename.trim() || extractFilenameFromUrl(url));
  };

  const canValidate = url.trim() && !isLoadingPreview && !isValidated;
  const canUpload = isValidated && !urlError && !isSubmitting;

  return (
    <form onSubmit={handleSubmit} className="space-y-24">
      {/* URL Input */}
      <TextField
        label={<Trans message="File URL" />}
        placeholder="https://example.com/file.pdf"
        value={url}
        onChange={e => setUrl(e.target.value)}
        disabled={isSubmitting || isLoadingPreview}
        required
        autoFocus
        startAdornment={<LinkIcon className="text-muted" />}
        errorMessage={urlError}
        description={
          <Trans message="Direct link to the file you want to upload to Telegram" />
        }
      />

      {/* Preview Section */}
      {isLoadingPreview && (
        <div className="flex items-center gap-8 rounded border border-primary-light bg-primary-light/10 p-12">
          <ProgressCircle size="sm" isIndeterminate />
          <span className="text-sm text-muted">
            <Trans message="Loading information..." />
          </span>
        </div>
      )}

      {preview && preview.is_accessible && (
        <div className="rounded border border-positive bg-positive/10 p-12">
          <div className="mb-6 flex items-center gap-8">
            <CheckCircleIcon size="sm" className="text-positive" />
            <span className="text-sm font-medium text-positive">
              <Trans message="✓ فایل معتبر است و آماده آپلود" />
            </span>
          </div>
          <div className="space-y-4 text-xs text-muted">
            {preview.size && (
              <div>
                <Trans message="Size" />: {formatFileSize(preview.size)}
              </div>
            )}
            {preview.mime_type && (
              <div>
                <Trans message="Type" />: {preview.mime_type}
              </div>
            )}
            {preview.upload_method && (
              <div>
                <Trans message="Upload Method" />: {preview.upload_method === 'bot' ? 'Bot API (< 50MB)' : 'User Account (50MB-2GB)'}
              </div>
            )}
          </div>
        </div>
      )}

      {preview && !preview.is_accessible && (
        <div className="rounded border border-danger bg-danger/10 p-12">
          <div className="flex items-center gap-8">
            <ErrorIcon size="sm" className="text-danger" />
            <span className="text-sm text-danger">
              {preview.error || <Trans message="File is not accessible2" />}
            </span>
          </div>
        </div>
      )}

      {/* Filename Input */}
      <TextField
        label={<Trans message="Filename (Optional)" />}
        placeholder="document.pdf"
        value={filename}
        onChange={e => setFilename(e.target.value)}
        disabled={isSubmitting || isLoadingPreview}
        description={
          <Trans message="File name in your system. If empty, the original file name will be used" />
        }
      />

      {/* Action Buttons */}
      <div className="flex gap-12">
        {/* Validate Button */}
        {!isValidated && (
          <Button
            type="button"
            variant="outline"
            color="primary"
            disabled={!canValidate}
            className="flex-1"
            onClick={handleValidateClick}
            startIcon={<VerifiedIcon />}
          >
            {isLoadingPreview ? (
              <Trans message="در حال بررسی..." />
            ) : (
              <Trans message="بررسی اعتبار" />
            )}
          </Button>
        )}

        {/* Upload Button */}
        {isValidated && (
          <Button
            type="submit"
            variant="flat"
            color="primary"
            disabled={!canUpload}
            className="flex-1"
            startIcon={<CloudUploadIcon />}
          >
            {isSubmitting ? (
              <Trans message="در حال آپلود..." />
            ) : (
              <Trans message="آپلود به تلگرام" />
            )}
          </Button>
        )}
      </div>

      {/* Help Text */}
      <div className="rounded bg-alt p-12 text-xs text-muted">
        <div className="mb-6 font-medium">
          <Trans message="💡 Note:" />
        </div>
        <ul className="list-inside list-disc space-y-2">
          <li>
            <Trans message="Files less than 50MB are uploaded via Bot (fast)" />
          </li>
          <li>
            <Trans message="Files 50MB to 2GB via User Account (slower)" />
          </li>
          <li>
            <Trans message="Maximum upload size: 2GB" />
          </li>
        </ul>
      </div>
    </form>
  );
}