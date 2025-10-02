import {Trans} from '@common/i18n/trans';
import {Button} from '@common/ui/buttons/button';
import {TextField} from '@common/ui/forms/input-field/text-field/text-field';
import {CheckIcon} from '@common/icons/material/Check';
import {InfoIcon} from '@common/icons/material/Info';
import {WarningIcon} from '@common/icons/material/Warning';
import type {SingleUrlFormProps} from '../telegram-types';
import {isValidUrl} from '../telegram-url-upload-api';

export function SingleUrlForm({
  url,
  setUrl,
  name,
  setName,
  caption,
  setCaption,
  validation,
  validating,
  onValidate,
}: SingleUrlFormProps) {
  const urlError = url && !isValidUrl(url) ? 'Invalid URL format' : undefined;
  const canValidate = url && isValidUrl(url) && !validating;

  return (
    <div className="space-y-4">
      {/* URL Input */}
      <div className="flex gap-2">
        <TextField
          label={<Trans message="File URL" />}
          value={url}
          onChange={e => setUrl(e.target.value)}
          placeholder="https://example.com/file.pdf"
          errorMessage={urlError}
          required
          autoFocus
          className="flex-1"
        />
        <Button
          variant="outline"
          color="primary"
          size="md"
          onClick={onValidate}
          disabled={!canValidate}
          className="mt-6"
        >
          {validating ? (
            <Trans message="Checking..." />
          ) : (
            <Trans message="Validate" />
          )}
        </Button>
      </div>

      {/* Validation Result */}
      {validation && validation.valid && (
        <div className="bg-positive/10 border border-positive rounded-md p-4">
          <div className="flex items-start gap-3">
            <CheckIcon className="text-positive mt-0.5" size="sm" />
            <div className="flex-1 space-y-2">
              <div className="font-semibold text-sm">
                <Trans message="URL is valid and ready to upload" />
              </div>
              <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                <div>
                  <span className="text-muted">
                    <Trans message="Type:" />
                  </span>{' '}
                  <span>{validation.content_type}</span>
                </div>
                <div>
                  <span className="text-muted">
                    <Trans message="Size:" />
                  </span>{' '}
                  <span>{validation.content_length_formatted}</span>
                </div>
                <div className="col-span-2">
                  <span className="text-muted">
                    <Trans message="Method:" />
                  </span>{' '}
                  <span>
                    {validation.upload_method === 'bot'
                      ? 'Bot API (< 50MB)'
                      : 'User Account (50MB-2GB)'}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {validation && !validation.can_upload && (
        <div className="bg-danger/10 border border-danger rounded-md p-4">
          <div className="flex items-start gap-3">
            <WarningIcon className="text-danger mt-0.5" size="sm" />
            <div className="flex-1">
              <div className="font-semibold text-sm">
                <Trans message="Cannot upload this file" />
              </div>
              <div className="text-xs mt-1">{validation.reason}</div>
            </div>
          </div>
        </div>
      )}

      {/* Optional Fields */}
      <TextField
        label={<Trans message="File name (optional)" />}
        value={name}
        onChange={e => setName(e.target.value)}
        placeholder="My Document"
      />

      <TextField
        label={<Trans message="Caption (optional)" />}
        value={caption}
        onChange={e => setCaption(e.target.value)}
        placeholder="Add a caption for this file"
        inputElementType="textarea"
        rows={3}
      />

      {/* Info Box */}
      <div className="bg-primary/5 border border-primary/20 rounded-md p-4">
        <div className="flex items-start gap-3">
          <InfoIcon className="text-primary mt-0.5" size="sm" />
          <div className="flex-1 text-xs space-y-1">
            <div className="font-semibold">
              <Trans message="Upload Information" />
            </div>
            <ul className="list-disc list-inside space-y-0.5 text-muted">
              <li>
                <Trans message="Files under 50MB will use Bot API (faster)" />
              </li>
              <li>
                <Trans message="Files 50MB-2GB require User Account credentials" />
              </li>
              <li>
                <Trans message="Maximum file size: 2GB" />
              </li>
              <li>
                <Trans message="The file will be downloaded to server temporarily and then uploaded to Telegram" />
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}
