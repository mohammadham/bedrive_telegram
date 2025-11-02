import {UploadedFile} from '@ui/utils/files/uploaded-file';
import {UploadStrategy, UploadStrategyConfig} from './upload-strategy';
import {apiClient} from '@common/http/query-client';
import {getAxiosErrorMessage} from '@common/http/get-axios-error-message';
import {AxiosProgressEvent} from 'axios';

export class AxiosUpload implements UploadStrategy {
  private abortController: AbortController;
  constructor(
    private file: UploadedFile,
    private config: UploadStrategyConfig,
  ) {
    this.abortController = new AbortController();
  }

async start() {
    const formData = new FormData();
    const {onSuccess, onError, onProgress, metadata} = this.config;

    // 🔍 LOG: شروع آپلود
    console.log('[AXIOS-UPLOAD] Starting upload', {
      fileName: this.file.name,
      fileSize: this.file.size,
      fileSizeMB: (this.file.size / (1024 * 1024)).toFixed(2) + 'MB',
      mimeType: this.file.mime,
      extension: this.file.extension,
      metadata: metadata,
    });

    formData.set('file', this.file.native);
    formData.set('clientMime', this.file.mime);
    formData.set('clientExtension', this.file.extension);
    if (metadata) {
      Object.entries(metadata).forEach(([key, value]) => {
        formData.set(key, `${value}`);
      });
    }

    console.log('[AXIOS-UPLOAD] FormData prepared, sending request...');
    const startTime = Date.now();

    const response = await apiClient
      .post('file-entries', formData, {
        onUploadProgress: (e: AxiosProgressEvent) => {
          if (e.event.lengthComputable) {
            const percentage = ((e.loaded / (e.total || 1)) * 100).toFixed(1);
            const loadedMB = (e.loaded / (1024 * 1024)).toFixed(2);
            const totalMB = ((e.total || 0) / (1024 * 1024)).toFixed(2);
            
            console.log(`[AXIOS-UPLOAD] Progress: ${percentage}% (${loadedMB}/${totalMB} MB)`);
            
            onProgress?.({
              bytesUploaded: e.loaded,
              bytesTotal: e.total || 0,
            });
          }
        },
        signal: this.abortController.signal,
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })
      .catch(err => {
        const duration = Date.now() - startTime;
        
        // 🔍 LOG: خطا در آپلود
        console.error('[AXIOS-UPLOAD] Upload failed', {
          fileName: this.file.name,
          fileSize: this.file.size,
          duration: duration + 'ms',
          errorCode: err.code,
          errorMessage: err.message,
          status: err.response?.status,
          statusText: err.response?.statusText,
          responseData: err.response?.data,
          headers: err.response?.headers,
        });
        
        if (err.code !== 'ERR_CANCELED') {
          const errorMsg = getAxiosErrorMessage(err);
          console.error('[AXIOS-UPLOAD] Error message for user:', errorMsg);
          onError?.(errorMsg, this.file);
        }
      });

    // if upload was aborted, it will be handled and set
    // as \"aborted\" already, no need to set it as \"failed\"
    if (this.abortController.signal.aborted) {
      console.warn('[AXIOS-UPLOAD] Upload was aborted by user');
      return;
    }

    if (response && response.data.fileEntry) {
      const duration = Date.now() - startTime;
      
      // 🔍 LOG: موفقیت آپلود
      console.log('[AXIOS-UPLOAD] Upload successful', {
        fileName: this.file.name,
        fileSize: this.file.size,
        duration: duration + 'ms',
        entryId: response.data.fileEntry.id,
        entryPath: response.data.fileEntry.path,
      });
      
      onSuccess?.(response.data.fileEntry, this.file);
    } else if (response) {
      // 🔍 LOG: پاسخ نامعتبر
      console.error('[AXIOS-UPLOAD] Invalid response', {
        fileName: this.file.name,
        hasResponse: !!response,
        hasData: !!response.data,
        hasFileEntry: !!response.data?.fileEntry,
        responseData: response.data,
      });
    } else {
      // 🔍 LOG: بدون پاسخ
      console.error('[AXIOS-UPLOAD] No response received', {
        fileName: this.file.name,
      });
    }
  }

  abort() {
    this.abortController.abort();
    return Promise.resolve();
  }

  static async create(
    file: UploadedFile,
    config: UploadStrategyConfig,
  ): Promise<AxiosUpload> {
    return new AxiosUpload(file, config);
  }
}
