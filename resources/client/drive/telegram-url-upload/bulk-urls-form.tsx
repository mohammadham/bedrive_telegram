/**
 * Phase 8.1: Telegram URL Upload - Bulk URLs Form
 * فرم آپلود چندتایی از URL
 */

import React, {useState} from 'react';
import {Trans} from '@ui/i18n/trans';
import {Button} from '@ui/buttons/button';
import {CloudUploadIcon} from '@ui/icons/material/CloudUpload';
import {DeleteIcon} from '@ui/icons/material/Delete';
import {AddIcon} from '@ui/icons/material/Add';
import {validateUrl} from './telegram-url-upload-api';

interface BulkUrlsFormProps {
  onSubmit: (urls: string[]) => void;
  isSubmitting: boolean;
}

export function BulkUrlsForm({onSubmit, isSubmitting}: BulkUrlsFormProps) {
  const [urls, setUrls] = useState(['', '', '']);
  const [errors, setErrors] = useState<{[key: number]: string}>({});

  const addUrlField = () => {
    setUrls([...urls, '']);
  };

  const removeUrlField = (index: number) => {
    if (urls.length <= 1) return;
    const newUrls = urls.filter((_, i) => i !== index);
    setUrls(newUrls);
    
    // Remove error for this index
    const newErrors = {...errors};
    delete newErrors[index];
    setErrors(newErrors);
  };

  const updateUrl = (index: number, value: string) => {
    const newUrls = [...urls];
    newUrls[index] = value;
    setUrls(newUrls);

    // Clear error when typing
    if (errors[index]) {
      const newErrors = {...errors};
      delete newErrors[index];
      setErrors(newErrors);
    }
  };

  const validateUrlField = (index: number) => {
    const url = urls[index].trim();
    if (!url) {
      // Empty is OK, just skip
      return;
    }

    const validation = validateUrl(url);
    if (!validation.isValid) {
      setErrors({
        ...errors,
        [index]: validation.error || 'URL نامعتبر است',
      });
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    // فیلتر URL‌های خالی
    const validUrls = urls
      .map(u => u.trim())
      .filter(u => u.length > 0);

    if (validUrls.length === 0) {
      setErrors({0: 'حداقل یک URL وارد کنید'});
      return;
    }

    // اعتبارسنجی همه URL‌ها
    const newErrors: {[key: number]: string} = {};
    urls.forEach((url, index) => {
      if (url.trim()) {
        const validation = validateUrl(url);
        if (!validation.isValid) {
          newErrors[index] = validation.error || 'URL نامعتبر است';
        }
      }
    });

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }

    onSubmit(validUrls);
  };

  const validUrlsCount = urls.filter(u => u.trim()).length;
  const hasErrors = Object.keys(errors).length > 0;

  return (
    <form onSubmit={handleSubmit} className="space-y-24">
      {/* Info Banner */}
      <div className="rounded border border-primary-light bg-primary-light/10 p-12">
        <div className="mb-4 text-sm font-medium text-primary">
          <Trans message="آپلود دسته‌ای" />
        </div>
        <p className="text-xs text-muted">
          <Trans message="می‌توانید چندین URL را به صورت همزمان آپلود کنید. فایل‌هایی که خطا دارند، نادیده گرفته می‌شوند." />
        </p>
      </div>

      {/* URL Fields */}
      <div className="space-y-12">
        <div className="mb-8 flex items-center justify-between">
          <span className="text-sm font-medium">
            <Trans message="لیست URL‌ها" />
            {validUrlsCount > 0 && (
              <span className="ml-8 text-xs text-muted">
                ({validUrlsCount} <Trans message="مورد" />)
              </span>
            )}
          </span>
          <Button
            type="button"
            size="xs"
            variant="outline"
            startIcon={<AddIcon />}
            onClick={addUrlField}
            disabled={isSubmitting}
          >
            <Trans message="افزودن URL" />
          </Button>
        </div>

        {urls.map((url, index) => (
          <div key={index} className="flex items-start gap-8">
            <div className="flex-1">
              <input
                type="url"
                value={url}
                onChange={e => updateUrl(index, e.target.value)}
                onBlur={() => validateUrlField(index)}
                placeholder={`URL ${index + 1}`}
                disabled={isSubmitting}
                className={`w-full rounded border px-12 py-8 text-sm transition-colors ${
                  errors[index]
                    ? 'border-danger focus:border-danger'
                    : 'border-divider focus:border-primary'
                } disabled:opacity-50`}
              />
              {errors[index] && (
                <div className="mt-4 text-xs text-danger">{errors[index]}</div>
              )}
            </div>
            {urls.length > 1 && (
              <button
                type="button"
                onClick={() => removeUrlField(index)}
                disabled={isSubmitting}
                className="mt-6 rounded p-6 text-danger transition-colors hover:bg-danger/10 disabled:opacity-50"
                title="حذف"
              >
                <DeleteIcon size="sm" />
              </button>
            )}
          </div>
        ))}
      </div>

      {/* Textarea Alternative */}
      <div className="rounded border border-dashed border-divider p-12">
        <div className="mb-6 text-xs font-medium text-muted">
          <Trans message="یا هر URL را در یک خط جداگانه وارد کنید:" />
        </div>
        <textarea
          placeholder="https://example.com/file1.pdf&#10;https://example.com/file2.zip&#10;https://example.com/file3.mp4"
          rows={5}
          disabled={isSubmitting}
          className="w-full rounded border border-divider px-12 py-8 text-sm transition-colors focus:border-primary disabled:opacity-50"
          onBlur={e => {
            const lines = e.target.value
              .split('\n')
              .map(l => l.trim())
              .filter(l => l.length > 0);
            if (lines.length > 0) {
              setUrls(lines);
              e.target.value = '';
            }
          }}
        />
      </div>

      {/* Submit Button */}
      <Button
        type="submit"
        variant="flat"
        color="primary"
        disabled={validUrlsCount === 0 || hasErrors || isSubmitting}
        className="w-full"
        startIcon={<CloudUploadIcon />}
      >
        {isSubmitting ? (
          <Trans message="در حال آپلود..." />
        ) : (
          <>
            <Trans message="آپلود" /> {validUrlsCount}{' '}
            <Trans message="فایل به تلگرام" />
          </>
        )}
      </Button>

      {/* Statistics */}
      {(validUrlsCount > 0 || hasErrors) && (
        <div className="flex items-center justify-between rounded bg-alt p-12 text-xs">
          <div className="flex gap-16">
            <div>
              <span className="text-muted">
                <Trans message="معتبر:" />
              </span>{' '}
              <span className="font-medium text-positive">{validUrlsCount}</span>
            </div>
            {hasErrors && (
              <div>
                <span className="text-muted">
                  <Trans message="خطا:" />
                </span>{' '}
                <span className="font-medium text-danger">
                  {Object.keys(errors).length}
                </span>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Help Text */}
      <div className="rounded bg-alt p-12 text-xs text-muted">
        <div className="mb-6 font-medium">
          <Trans message="💡 نکته‌های آپلود دسته‌ای:" />
        </div>
        <ul className="list-inside list-disc space-y-2">
          <li>
            <Trans message="فایل‌ها به صورت موازی آپلود می‌شوند" />
          </li>
          <li>
            <Trans message="URL‌های نامعتبر یا غیرقابل دسترسی نادیده گرفته می‌شوند" />
          </li>
          <li>
            <Trans message="پس از آپلود، گزارش کامل موفقیت/خطاها نمایش داده می‌شود" />
          </li>
        </ul>
      </div>
    </form>
  );
}