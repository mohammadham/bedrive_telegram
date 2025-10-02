import {Trans} from '@common/i18n/trans';
import {TextField} from '@common/ui/forms/input-field/text-field/text-field';
import {InfoIcon} from '@common/icons/material/Info';
import {CheckCircleIcon} from '@common/icons/material/CheckCircle';
import {ErrorIcon} from '@common/icons/material/Error';
import type {BulkUrlsFormProps} from '../telegram-types';
import {parseUrlsFromText, isValidUrl} from '../telegram-url-upload-api';
import {useMemo} from 'react';

export function BulkUrlsForm({
  urls,
  setUrls,
  caption,
  setCaption,
}: BulkUrlsFormProps) {
  const urlsText = urls.join('\n');

  const handleUrlsChange = (text: string) => {
    const parsedUrls = parseUrlsFromText(text);
    setUrls(parsedUrls);
  };

  const stats = useMemo(() => {
    const lines = urlsText.split('\n').filter(line => line.trim());
    const validUrls = lines.filter(line => isValidUrl(line.trim()));
    const invalidUrls = lines.filter(
      line => line.trim() && !isValidUrl(line.trim())
    );

    return {
      total: lines.length,
      valid: validUrls.length,
      invalid: invalidUrls.length,
    };
  }, [urlsText]);

  const hasInvalidUrls = stats.invalid > 0;
  const exceededMax = stats.total > 100;

  return (
    <div className="space-y-4">
      {/* URLs Textarea */}
      <TextField
        label={<Trans message="File URLs (one per line)" />}
        value={urlsText}
        onChange={e => handleUrlsChange(e.target.value)}
        placeholder={`https://example.com/file1.pdf\nhttps://example.com/file2.jpg\nhttps://example.com/file3.mp4`}
        inputElementType="textarea"
        rows={10}
        required
        className="font-mono text-sm"
      />

      {/* Stats */}
      {stats.total > 0 && (
        <div className="bg-background border rounded-md p-4">
          <div className="text-sm font-semibold mb-3">
            <Trans message="Summary" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="flex items-center gap-2">
              <InfoIcon size="sm" className="text-muted" />
              <div>
                <div className="text-xs text-muted">
                  <Trans message="Total URLs" />
                </div>
                <div className="text-lg font-semibold">{stats.total}</div>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <CheckCircleIcon size="sm" className="text-positive" />
              <div>
                <div className="text-xs text-muted">
                  <Trans message="Valid" />
                </div>
                <div className="text-lg font-semibold text-positive">
                  {stats.valid}
                </div>
              </div>
            </div>
            {hasInvalidUrls && (
              <div className="flex items-center gap-2 col-span-2">
                <ErrorIcon size="sm" className="text-danger" />
                <div>
                  <div className="text-xs text-muted">
                    <Trans message="Invalid URLs" />
                  </div>
                  <div className="text-lg font-semibold text-danger">
                    {stats.invalid}
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Max limit warning */}
          {exceededMax && (
            <div className="mt-3 pt-3 border-t text-xs text-danger">
              <Trans
                message="Maximum 100 URLs allowed. Current: :count"
                values={{count: stats.total}}
              />
            </div>
          )}
        </div>
      )}

      {/* Caption */}
      <TextField
        label={<Trans message="Caption for all files (optional)" />}
        value={caption}
        onChange={e => setCaption(e.target.value)}
        placeholder="Add a caption that will be applied to all files"
        inputElementType="textarea"
        rows={2}
      />

      {/* Info Boxes */}
      <div className="space-y-2">
        {/* Instructions */}
        <div className="bg-primary/5 border border-primary/20 rounded-md p-3">
          <div className="flex items-start gap-3">
            <InfoIcon className="text-primary mt-0.5" size="sm" />
            <div className="flex-1 text-xs space-y-1">
              <div className="font-semibold">
                <Trans message="How to use" />
              </div>
              <ul className="list-disc list-inside space-y-0.5 text-muted">
                <li>
                  <Trans message="Paste one URL per line" />
                </li>
                <li>
                  <Trans message="Maximum 100 URLs at once" />
                </li>
                <li>
                  <Trans message="Invalid URLs will be automatically filtered out" />
                </li>
                <li>
                  <Trans message="Each file will be uploaded independently" />
                </li>
              </ul>
            </div>
          </div>
        </div>

        {/* Limitations */}
        <div className="bg-warning/5 border border-warning/20 rounded-md p-3">
          <div className="flex items-start gap-3">
            <InfoIcon className="text-warning mt-0.5" size="sm" />
            <div className="flex-1 text-xs">
              <div className="font-semibold mb-1">
                <Trans message="Important Notes" />
              </div>
              <ul className="list-disc list-inside space-y-0.5 text-muted">
                <li>
                  <Trans message="Bulk uploads may take several minutes" />
                </li>
                <li>
                  <Trans message="Failed uploads will be reported in results" />
                </li>
                <li>
                  <Trans message="Files over 2GB cannot be uploaded" />
                </li>
                <li>
                  <Trans message="Large files (50MB+) require User Account setup" />
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
