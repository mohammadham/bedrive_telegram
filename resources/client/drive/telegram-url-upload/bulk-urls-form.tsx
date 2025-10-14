/**
 * Phase 8.1: Telegram URL Upload - Bulk URLs Form
 * فرم آپلود دسته‌ای از URL
 */

import React, {useState} from 'react';
import {Trans} from '@ui/i18n/trans';
import {TextField} from '@ui/forms/input-field/text-field/text-field';
import {Button} from '@ui/buttons/button';
import {ProgressCircle} from '@ui/progress/progress-circle';
import {CloudUploadIcon} from '@ui/icons/material/CloudUpload';
import {AddIcon} from '@ui/icons/material/Add';
import {RemoveIcon} from '@ui/icons/material/Remove';
import {IconButton} from '@ui/buttons/icon-button';
import {validateUrl, extractFilenameFromUrl} from './telegram-url-upload-api';
import {CheckCircleIcon} from '@ui/icons/material/CheckCircle';
import {ErrorIcon} from '@ui/icons/material/Error';

interface UrlField {
  id: number;
  url: string;
  filename: string;
  isValid?: boolean;
  error?: string;
}

interface BulkUrlsFormProps {
  onSubmit: (urls: {url: string; filename: string}[]) => void;
  isSubmitting: boolean;
}

export function BulkUrlsForm({onSubmit, isSubmitting}: BulkUrlsFormProps) {
  const [fields, setFields] = useState<UrlField[]>([
    {id: 1, url: '', filename: ''},
    {id: 2, url: '', filename: ''},
    {id: 3, url: '', filename: ''},
  ]);
  const [textareaMode, setTextareaMode] = useState(false);
  const [bulkText, setBulkText] = useState('');

  const addField = () => {
    const newId = Math.max(...fields.map(f => f.id)) + 1;
    setFields([...fields, {id: newId, url: '', filename: ''}]);
  };

  const removeField = (id: number) => {
    if (fields.length > 1) {
      setFields(fields.filter(f => f.id !== id));
    }
  };

  const updateField = (
    id: number,
    key: keyof UrlField,
    value: string | boolean,
  ) => {
    setFields(
      fields.map(f => {
        if (f.id === id) {
          const updated = {...f, [key]: value};

          // Auto-fill filename from URL
          if (key === 'url' && typeof value === 'string' && value) {
            const extracted = extractFilenameFromUrl(value);
            if (extracted && !f.filename) {
              updated.filename = extracted;
            }

            // Validate URL
            const validation = validateUrl(value);
            updated.isValid = validation.isValid;
            updated.error = validation.error;
          }

          return updated;
        }
        return f;
      }),
    );
  };

  const handleBulkPaste = () => {
    const lines = bulkText
      .split('\n')
      .map(line => line.trim())
      .filter(line => line.length > 0);

    const newFields: UrlField[] = lines.map((url, index) => {
      const validation = validateUrl(url);
      return {
        id: index + 1,
        url,
        filename: extractFilenameFromUrl(url) || `file-${index + 1}`,
        isValid: validation.isValid,
        error: validation.error,
      };
    });

    setFields(newFields);
    setTextareaMode(false);
    setBulkText('');
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const validFields = fields.filter(
      f => f.url && f.filename && f.isValid !== false,
    );

    if (validFields.length === 0) {
      return;
    }

    onSubmit(validFields.map(f => ({url: f.url, filename: f.filename})));
  };

  const validCount = fields.filter(f => f.isValid === true).length;
  const invalidCount = fields.filter(f => f.isValid === false).length;

  return (
    <form onSubmit={handleSubmit} className="space-y-16">
      {/* Switch Mode */}
      <div className="flex items-center justify-between">
        <Button
          type="button"
          variant="text"
          size="xs"
          onClick={() => setTextareaMode(!textareaMode)}
        >
          <Trans
            message={textareaMode ? 'فیلدهای جداگانه' : 'حالت Paste چندتایی'}
          />
        </Button>

        {!textareaMode && (
          <div className="flex items-center gap-8 text-xs text-muted">
            {validCount > 0 && (
              <span className="flex items-center gap-4 text-positive">
                <CheckCircleIcon className="text-positive" size="xs" />
                {validCount} معتبر
              </span>
            )}
            {invalidCount > 0 && (
              <span className="flex items-center gap-4 text-danger">
                <ErrorIcon className="text-danger" size="xs" />
                {invalidCount} نامعتبر
              </span>
            )}
          </div>
        )}
      </div>

      {/* Textarea Mode */}
      {textareaMode ? (
        <div className="space-y-12">
          <TextField
            label={<Trans message="لینک‌ها (هر خط یک لینک)" />}
            inputElementType="textarea"
            value={bulkText}
            onChange={e => setBulkText(e.target.value)}
            rows={10}
            placeholder="https://example.com/file1.pdf\nhttps://example.com/file2.zip\nhttps://example.com/file3.mp4"
            description={
              <Trans message="هر لینک را در یک خط جداگانه وارد کنید" />
            }
          />
          <Button
            type="button"
            onClick={handleBulkPaste}
            disabled={!bulkText.trim()}
          >
            <Trans message="تبدیل به فیلدها" />
          </Button>
        </div>
      ) : (
        /* Individual Fields */
        <div className="space-y-12">
          {fields.map((field, index) => (
            <div key={field.id} className="flex items-start gap-8">
              <div className="flex-1 space-y-8">
                <TextField
                  label={`لینک ${index + 1}`}
                  value={field.url}
                  onChange={e => updateField(field.id, 'url', e.target.value)}
                  placeholder="https://example.com/file.pdf"
                  required
                  error={field.error}
                  endAppend={
                    field.isValid === true ? (
                      <CheckCircleIcon className="text-positive" size="sm" />
                    ) : field.isValid === false ? (
                      <ErrorIcon className="text-danger" size="sm" />
                    ) : undefined
                  }
                />
                <TextField
                  label="نام فایل"
                  value={field.filename}
                  onChange={e =>
                    updateField(field.id, 'filename', e.target.value)
                  }
                  placeholder="my-file.pdf"
                  required
                />
              </div>

              <div className="pt-28">
                <IconButton
                  size="sm"
                  color="danger"
                  onClick={() => removeField(field.id)}
                  disabled={fields.length === 1}
                >
                  <RemoveIcon />
                </IconButton>
              </div>
            </div>
          ))}

          <Button
            type="button"
            variant="outline"
            size="xs"
            onClick={addField}
            startIcon={<AddIcon />}
          >
            <Trans message="افزودن لینک" />
          </Button>
        </div>
      )}

      {/* Submit */}
      <div className="flex items-center justify-between pt-8">
        <div className="text-xs text-muted">
          <Trans
            message=":count لینک آماده آپلود"
            values={{count: validCount}}
          />
        </div>
        <Button
          type="submit"
          variant="flat"
          color="primary"
          disabled={
            isSubmitting || validCount === 0 || textareaMode || fields.length === 0
          }
          startIcon={
            isSubmitting ? (
              <ProgressCircle size="sm" isIndeterminate />
            ) : (
              <CloudUploadIcon />
            )
          }
        >
          {isSubmitting ? (
            <Trans message="در حال آپلود..." />
          ) : (
            <Trans message="شروع آپلود" />
          )}
        </Button>
      </div>
    </form>
  );
}
