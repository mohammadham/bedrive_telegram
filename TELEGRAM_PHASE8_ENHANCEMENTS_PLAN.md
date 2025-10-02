# Phase 8: Optional Enhancements - پلن کامل

## 📋 فهرست مطالب
1. [خلاصه Phase 8](#خلاصه-phase-8)
2. [بخش 1: Frontend URL Upload UI](#بخش-1-frontend-url-upload-ui)
3. [بخش 2: Progress Tracking & Real-time Updates](#بخش-2-progress-tracking--real-time-updates)
4. [بخش 3: Resume Upload](#بخش-3-resume-upload)
5. [بخش 4: Auto-Retry System](#بخش-4-auto-retry-system)
6. [بخش 5: Advanced Telegram Features](#بخش-5-advanced-telegram-features)
7. [بخش 6: Performance Optimizations](#بخش-6-performance-optimizations)
8. [بخش 7: Security Enhancements](#بخش-7-security-enhancements)
9. [Timeline و Priority](#timeline-و-priority)
10. [چک‌لیست کامل](#چک‌لیست-کامل)

---

## 🎯 خلاصه Phase 8

### هدف:
افزودن قابلیت‌های پیشرفته و بهبود UX/Performance درایور تلگرام

### محدوده:
- ✨ Frontend UI کامل برای URL Upload
- 📊 Progress tracking و real-time updates
- ⏸️ Resume upload برای فایل‌های بزرگ
- 🔄 Auto-retry برای failed uploads
- 🚀 ویژگی‌های پیشرفته تلگرام
- ⚡ بهینه‌سازی performance
- 🔐 بهبود امنیت

### زمان تخمینی:
**40-60 ساعت** (تقسیم به 7 بخش)

---

## 📊 پیشرفت کلی Phase 8

```
Phase 8: Optional Enhancements
├── بخش 1: Frontend UI              ░░░░░░░░░░░░░░░░░░░░   0% ⏳
├── بخش 2: Progress Tracking        ░░░░░░░░░░░░░░░░░░░░   0% ⏳
├── بخش 3: Resume Upload            ░░░░░░░░░░░░░░░░░░░░   0% ⏳
├── بخش 4: Auto-Retry               ░░░░░░░░░░░░░░░░░░░░   0% ⏳
├── بخش 5: Advanced Features        ░░░░░░░░░░░░░░░░░░░░   0% ⏳
├── بخش 6: Performance              ░░░░░░░░░░░░░░░░░░░░   0% ⏳
└── بخش 7: Security                 ░░░░░░░░░░░░░░░░░░░░   0% ⏳
```

---

## 🎨 بخش 1: Frontend URL Upload UI

### زمان تخمینی: 8-10 ساعت

### هدف:
رابط کاربری کامل برای آپلود از URL در BeDrive

---

### 1.1 فایل‌های مورد نیاز

#### Frontend Components (6 فایل):

##### 1. `TelegramUrlUploadDialog.tsx`
**مسیر**: `/app/common/foundation/resources/client/uploads/telegram-url-upload-dialog.tsx`

**ویژگی‌ها**:
- ✅ Dialog modal برای URL input
- ✅ Validation در real-time
- ✅ Preview URL info (size, type)
- ✅ Single و Bulk tabs
- ✅ Error display
- ✅ Success notification

**ساختار**:
```tsx
export function TelegramUrlUploadDialog({
  isOpen,
  onClose,
  onSuccess
}: Props) {
  const [mode, setMode] = useState<'single' | 'bulk'>('single');
  const [url, setUrl] = useState('');
  const [urls, setUrls] = useState<string[]>([]);
  const [validating, setValidating] = useState(false);
  const [uploading, setUploading] = useState(false);
  
  // Single URL upload
  const handleSingleUpload = async () => {
    // Validate
    const validation = await validateUrl(url);
    
    // Upload
    const result = await uploadFromUrl(url);
    
    // Success
    onSuccess(result);
  };
  
  // Bulk URL upload
  const handleBulkUpload = async () => {
    // Validate all
    const validUrls = await Promise.all(
      urls.map(url => validateUrl(url))
    );
    
    // Upload
    const result = await uploadBulkUrls(validUrls);
    
    // Success
    onSuccess(result);
  };
  
  return (
    <Dialog open={isOpen} onClose={onClose}>
      <DialogHeader>
        <DialogTitle>آپلود از URL</DialogTitle>
      </DialogHeader>
      
      <DialogBody>
        <Tabs value={mode} onChange={setMode}>
          <TabList>
            <Tab value="single">تکی</Tab>
            <Tab value="bulk">چندتایی</Tab>
          </TabList>
          
          <TabPanel value="single">
            <SingleUrlForm
              url={url}
              setUrl={setUrl}
              validating={validating}
              onValidate={handleValidate}
            />
          </TabPanel>
          
          <TabPanel value="bulk">
            <BulkUrlsForm
              urls={urls}
              setUrls={setUrls}
            />
          </TabPanel>
        </Tabs>
      </DialogBody>
      
      <DialogFooter>
        <Button onClick={onClose}>لغو</Button>
        <Button
          onClick={mode === 'single' ? handleSingleUpload : handleBulkUpload}
          loading={uploading}
          disabled={!isValid}
        >
          آپلود
        </Button>
      </DialogFooter>
    </Dialog>
  );
}
```

---

##### 2. `SingleUrlForm.tsx`
**مسیر**: `/app/common/foundation/resources/client/uploads/telegram-url-upload-dialog/single-url-form.tsx`

**ویژگی‌ها**:
- ✅ URL input با validation
- ✅ دکمه Validate
- ✅ نمایش URL info (size, type, method)
- ✅ Optional: name و caption
- ✅ Warning برای فایل‌های بزرگ

**UI Layout**:
```
┌─────────────────────────────────────┐
│ آپلود تکی از URL                   │
├─────────────────────────────────────┤
│ URL:                                │
│ [___________________________] [✓]   │
│                                     │
│ ℹ️ File Info (بعد از validate):    │
│ • Type: PDF Document               │
│ • Size: 5.2 MB                     │
│ • Method: Bot API (< 50MB)         │
│                                     │
│ Name (optional):                    │
│ [___________________________]       │
│                                     │
│ Caption (optional):                 │
│ [___________________________]       │
│                                     │
│ ⚠️ فایل‌های > 50MB نیاز به         │
│    User Account دارند              │
└─────────────────────────────────────┘
```

---

##### 3. `BulkUrlsForm.tsx`
**مسیر**: `/app/common/foundation/resources/client/uploads/telegram-url-upload-dialog/bulk-urls-form.tsx`

**ویژگی‌ها**:
- ✅ Textarea برای لیست URLs
- ✅ Parse کردن URLs (هر خط یک URL)
- ✅ نمایش تعداد URLs valid/invalid
- ✅ Option: caption مشترک
- ✅ Validation summary

**UI Layout**:
```
┌─────────────────────────────────────┐
│ آپلود چندتایی از URL                │
├─────────────────────────────────────┤
│ URLs (هر خط یک URL):                │
│ ┌─────────────────────────────────┐ │
│ │https://example.com/file1.pdf   │ │
│ │https://example.com/file2.jpg   │ │
│ │https://example.com/file3.mp4   │ │
│ │                                 │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ℹ️ Summary:                         │
│ • Total URLs: 3                    │
│ • Valid: 3 ✓                       │
│ • Invalid: 0                       │
│ • Max: 100 URLs                    │
│                                     │
│ Caption برای همه (optional):        │
│ [___________________________]       │
└─────────────────────────────────────┘
```

---

##### 4. `TelegramUrlUploadButton.tsx`
**مسیر**: `/app/common/foundation/resources/client/uploads/telegram-url-upload-button.tsx`

**ویژگی‌ها**:
- ✅ دکمه در Drive page
- ✅ فقط نمایش اگر driver تلگرام فعال باشد
- ✅ باز کردن Dialog
- ✅ Icon + Tooltip

**استفاده**:
```tsx
// در DriveToolbar یا FileList
{isTelegramDriver && (
  <TelegramUrlUploadButton
    onSuccess={(files) => {
      toast.positive('Files uploaded successfully');
      refetchFiles();
    }}
  />
)}
```

---

##### 5. `telegram-url-upload-api.ts`
**مسیر**: `/app/common/foundation/resources/client/uploads/telegram-url-upload-api.ts`

**Functions**:
```typescript
// Validate URL
export async function validateUrl(url: string): Promise<UrlValidation> {
  const response = await apiClient.post('telegram/validate-url', { url });
  return response.data;
}

// Upload single
export async function uploadFromUrl(
  url: string,
  options?: UploadOptions
): Promise<UploadResult> {
  const response = await apiClient.post('telegram/upload-url', {
    url,
    ...options,
  });
  return response.data;
}

// Upload bulk
export async function uploadBulkUrls(
  urls: string[],
  options?: UploadOptions
): Promise<BulkUploadResult> {
  const response = await apiClient.post('telegram/upload-bulk-urls', {
    urls,
    ...options,
  });
  return response.data;
}

// Check bulk status
export async function checkBulkUploadStatus(
  jobId: string
): Promise<BulkUploadStatus> {
  const response = await apiClient.get(`telegram/bulk-upload-status/${jobId}`);
  return response.data;
}
```

---

##### 6. Integration در Drive UI
**فایل‌ها برای Update**:
```
✅ /app/resources/client/drive/drive-toolbar.tsx
✅ /app/resources/client/drive/drive-page.tsx (اگر لازم باشد)
```

**تغییرات**:
```tsx
import {TelegramUrlUploadButton} from '@common/uploads/telegram-url-upload-button';
import {useDriveSettings} from './use-drive-settings';

export function DriveToolbar() {
  const {isTelegramDriver} = useDriveSettings();
  
  return (
    <div className="drive-toolbar">
      {/* سایر دکمه‌ها */}
      <UploadButton />
      <NewFolderButton />
      
      {/* دکمه URL Upload */}
      {isTelegramDriver && (
        <TelegramUrlUploadButton
          onSuccess={handleUploadSuccess}
        />
      )}
    </div>
  );
}
```

---

### 1.2 API Changes (اگر نیاز باشد)

**ممکن است نیاز باشد Response format را بهبود دهیم**:

```php
// در TelegramUrlUploadController
public function uploadSingle(Request $request): JsonResponse
{
    // ... existing code
    
    // بهبود response
    return $this->success([
        'message' => 'File uploaded successfully',
        'file' => [
            'id' => $result['file_entry']->id,
            'name' => $result['file_entry']->name,
            'size' => $result['file_entry']->file_size,
            'mime' => $result['file_entry']->mime,
            'path' => $result['file_entry']->path,
            'url' => $result['file_entry']->url, // اگر لازم باشد
        ],
        'telegram' => [
            'upload_method' => $result['upload_result']['upload_method'],
            'message_id' => $result['upload_result']['message_id'],
        ],
    ]);
}
```

---

### 1.3 Types و Interfaces

**فایل جدید**: `/app/common/foundation/resources/client/uploads/telegram-types.ts`

```typescript
export interface UrlValidation {
  valid: boolean;
  content_type: string;
  content_length: number;
  content_length_formatted: string;
  can_upload: boolean;
  upload_method: 'bot' | 'user' | null;
  reason: string;
}

export interface UploadOptions {
  name?: string;
  caption?: string;
}

export interface UploadResult {
  message: string;
  file: {
    id: number;
    name: string;
    size: number;
    mime: string;
    path: string;
  };
  telegram: {
    upload_method: 'bot' | 'user';
    message_id: number;
  };
}

export interface BulkUploadResult {
  message: string;
  total: number;
  successful: number;
  failed: number;
  results: Array<{
    url: string;
    success: boolean;
    file_entry_id?: number;
    error?: string;
  }>;
}

export interface BulkUploadStatus {
  status: 'processing' | 'completed' | 'failed' | 'not_found';
  total?: number;
  successful?: number;
  failed?: number;
  results?: any[];
}
```

---

### 1.4 چک‌لیست بخش 1

- [ ] ایجاد `TelegramUrlUploadDialog.tsx`
- [ ] ایجاد `SingleUrlForm.tsx`
- [ ] ایجاد `BulkUrlsForm.tsx`
- [ ] ایجاد `TelegramUrlUploadButton.tsx`
- [ ] ایجاد `telegram-url-upload-api.ts`
- [ ] ایجاد `telegram-types.ts`
- [ ] Integration در Drive Toolbar
- [ ] استایل‌دهی با Tailwind
- [ ] Responsive design
- [ ] تست در مرورگر
- [ ] تست validation
- [ ] تست upload flow

---

## 📊 بخش 2: Progress Tracking & Real-time Updates

### زمان تخمینی: 6-8 ساعت

### هدف:
نمایش پیشرفت آپلود به صورت real-time

---

### 2.1 فایل‌های مورد نیاز

#### Backend (3 فایل):

##### 1. `TelegramUploadProgressService.php`
**مسیر**: `/app/common/foundation/src/Files/Telegram/TelegramUploadProgressService.php`

**ویژگی‌ها**:
- ✅ ذخیره progress در Redis/Cache
- ✅ Update progress در chunks
- ✅ Broadcast events (optional)
- ✅ Cleanup بعد از complete

```php
namespace Common\Files\Telegram;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramUploadProgressService
{
    protected string $cachePrefix = 'telegram_upload_progress:';
    protected int $ttl = 3600; // 1 hour

    /**
     * Initialize progress tracking
     */
    public function initProgress(string $uploadId, array $data = []): void
    {
        $progress = [
            'status' => 'initializing',
            'progress' => 0,
            'total_size' => $data['total_size'] ?? 0,
            'downloaded' => 0,
            'uploaded' => 0,
            'speed' => 0,
            'eta' => null,
            'started_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ];

        Cache::put(
            $this->cachePrefix . $uploadId,
            $progress,
            $this->ttl
        );
    }

    /**
     * Update download progress
     */
    public function updateDownloadProgress(
        string $uploadId,
        int $downloaded,
        int $totalSize
    ): void {
        $progress = $this->getProgress($uploadId);
        
        if (!$progress) {
            return;
        }

        $progress['status'] = 'downloading';
        $progress['downloaded'] = $downloaded;
        $progress['progress'] = $totalSize > 0 
            ? round(($downloaded / $totalSize) * 50, 2) // 50% for download
            : 0;
        $progress['updated_at'] = now()->toISOString();

        // Calculate speed and ETA
        $elapsed = now()->diffInSeconds($progress['started_at']);
        if ($elapsed > 0) {
            $progress['speed'] = round($downloaded / $elapsed, 2);
            $remaining = $totalSize - $downloaded;
            $progress['eta'] = $progress['speed'] > 0
                ? round($remaining / $progress['speed'])
                : null;
        }

        Cache::put(
            $this->cachePrefix . $uploadId,
            $progress,
            $this->ttl
        );

        // Broadcast event (optional)
        // event(new UploadProgressUpdated($uploadId, $progress));
    }

    /**
     * Update upload progress
     */
    public function updateUploadProgress(
        string $uploadId,
        int $uploaded,
        int $totalSize
    ): void {
        $progress = $this->getProgress($uploadId);
        
        if (!$progress) {
            return;
        }

        $progress['status'] = 'uploading';
        $progress['uploaded'] = $uploaded;
        // 50-100% for upload
        $progress['progress'] = 50 + round(($uploaded / $totalSize) * 50, 2);
        $progress['updated_at'] = now()->toISOString();

        Cache::put(
            $this->cachePrefix . $uploadId,
            $progress,
            $this->ttl
        );
    }

    /**
     * Mark as completed
     */
    public function markCompleted(string $uploadId, array $result = []): void
    {
        $progress = $this->getProgress($uploadId) ?? [];
        
        $progress['status'] = 'completed';
        $progress['progress'] = 100;
        $progress['completed_at'] = now()->toISOString();
        $progress['result'] = $result;
        $progress['updated_at'] = now()->toISOString();

        Cache::put(
            $this->cachePrefix . $uploadId,
            $progress,
            300 // Keep for 5 minutes after completion
        );
    }

    /**
     * Mark as failed
     */
    public function markFailed(string $uploadId, string $error): void
    {
        $progress = $this->getProgress($uploadId) ?? [];
        
        $progress['status'] = 'failed';
        $progress['error'] = $error;
        $progress['failed_at'] = now()->toISOString();
        $progress['updated_at'] = now()->toISOString();

        Cache::put(
            $this->cachePrefix . $uploadId,
            $progress,
            300
        );
    }

    /**
     * Get progress
     */
    public function getProgress(string $uploadId): ?array
    {
        return Cache::get($this->cachePrefix . $uploadId);
    }

    /**
     * Clear progress
     */
    public function clearProgress(string $uploadId): void
    {
        Cache::forget($this->cachePrefix . $uploadId);
    }
}
```

---

##### 2. Update `TelegramUrlUploadService.php`

**افزودن Progress Tracking**:

```php
// در TelegramUrlUploadService

protected TelegramUploadProgressService $progressService;

public function __construct()
{
    $this->manager = app(TelegramFileManager::class);
    $this->progressService = app(TelegramUploadProgressService::class);
}

public function uploadFromUrl(
    string $url,
    array $metadata = [],
    array $options = [],
    ?string $uploadId = null // جدید
): array {
    $uploadId = $uploadId ?? uniqid('upload_', true);
    $tempPath = null;

    try {
        // Initialize progress
        $this->progressService->initProgress($uploadId, [
            'url' => $url,
        ]);

        // Download با progress tracking
        $tempPath = $this->downloadUrlToTempWithProgress($url, $uploadId);

        // Get file info
        $fileSize = filesize($tempPath);
        
        // Upload با progress tracking
        $uploadResult = $this->uploadFileWithProgress(
            $tempPath,
            $fileSize,
            $uploadId,
            $options
        );

        // Mark completed
        $this->progressService->markCompleted($uploadId, $uploadResult);

        // ... rest of code

    } catch (\Exception $e) {
        $this->progressService->markFailed($uploadId, $e->getMessage());
        throw $e;
    }
}

protected function downloadUrlToTempWithProgress(
    string $url,
    string $uploadId
): string {
    // ... existing code با اضافه کردن progress callback

    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function(
        $resource,
        $downloadSize,
        $downloaded,
        $uploadSize,
        $uploaded
    ) use ($uploadId) {
        if ($downloadSize > 0) {
            $this->progressService->updateDownloadProgress(
                $uploadId,
                (int) $downloaded,
                (int) $downloadSize
            );
        }
        return 0; // Continue download
    });
    
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    
    // ... rest
}
```

---

##### 3. `TelegramUploadProgressController.php`
**مسیر**: `/app/app/Http/Controllers/TelegramUploadProgressController.php`

```php
namespace App\Http\Controllers;

use Common\Files\Telegram\TelegramUploadProgressService;
use Common\Foundation\Http\Controller;
use Illuminate\Http\JsonResponse;

class TelegramUploadProgressController extends Controller
{
    protected TelegramUploadProgressService $progressService;

    public function __construct(TelegramUploadProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Get upload progress
     * GET /api/v1/telegram/upload-progress/{uploadId}
     */
    public function show(string $uploadId): JsonResponse
    {
        $progress = $this->progressService->getProgress($uploadId);

        if (!$progress) {
            return $this->error('Upload not found', 404);
        }

        return $this->success($progress);
    }
}
```

**Route**:
```php
Route::get('telegram/upload-progress/{uploadId}', [
    TelegramUploadProgressController::class,
    'show'
]);
```

---

#### Frontend (2 فایل):

##### 4. `TelegramUploadProgress.tsx`
**مسیر**: `/app/common/foundation/resources/client/uploads/telegram-url-upload-dialog/telegram-upload-progress.tsx`

```tsx
import {useQuery} from '@tanstack/react-query';
import {Progress} from '@common/ui/progress/progress';
import {formatBytes} from '@common/utils/format-bytes';

interface Props {
  uploadId: string;
  onComplete?: (result: any) => void;
  onError?: (error: string) => void;
}

export function TelegramUploadProgress({
  uploadId,
  onComplete,
  onError,
}: Props) {
  const {data: progress} = useQuery({
    queryKey: ['telegram-upload-progress', uploadId],
    queryFn: () => fetchUploadProgress(uploadId),
    refetchInterval: (data) => {
      // Stop polling when completed or failed
      if (!data) return 1000;
      if (data.status === 'completed') {
        onComplete?.(data.result);
        return false;
      }
      if (data.status === 'failed') {
        onError?.(data.error);
        return false;
      }
      return 1000; // Poll every second
    },
  });

  if (!progress) {
    return <div>Loading...</div>;
  }

  return (
    <div className="space-y-4">
      {/* Progress Bar */}
      <Progress value={progress.progress} size="lg" />

      {/* Status */}
      <div className="text-center">
        <div className="text-lg font-semibold">
          {progress.status === 'downloading' && 'در حال دانلود...'}
          {progress.status === 'uploading' && 'در حال آپلود به تلگرام...'}
          {progress.status === 'completed' && '✓ کامل شد'}
          {progress.status === 'failed' && '✗ خطا'}
        </div>
        <div className="text-sm text-muted">
          {progress.progress.toFixed(1)}%
        </div>
      </div>

      {/* Details */}
      {progress.status !== 'completed' && progress.status !== 'failed' && (
        <div className="grid grid-cols-2 gap-4 text-sm">
          <div>
            <div className="text-muted">دانلود شده:</div>
            <div>{formatBytes(progress.downloaded)}</div>
          </div>
          <div>
            <div className="text-muted">آپلود شده:</div>
            <div>{formatBytes(progress.uploaded)}</div>
          </div>
          <div>
            <div className="text-muted">سرعت:</div>
            <div>{formatBytes(progress.speed)}/s</div>
          </div>
          <div>
            <div className="text-muted">زمان باقیمانده:</div>
            <div>
              {progress.eta ? `${progress.eta}s` : 'محاسبه...'}
            </div>
          </div>
        </div>
      )}

      {/* Error */}
      {progress.status === 'failed' && (
        <div className="text-danger p-4 bg-danger/10 rounded">
          {progress.error}
        </div>
      )}
    </div>
  );
}

async function fetchUploadProgress(uploadId: string) {
  const response = await apiClient.get(
    `telegram/upload-progress/${uploadId}`
  );
  return response.data;
}
```

---

##### 5. Integration در Dialog

**Update `TelegramUrlUploadDialog.tsx`**:

```tsx
const [uploadId, setUploadId] = useState<string | null>(null);
const [showProgress, setShowProgress] = useState(false);

const handleSingleUpload = async () => {
  const newUploadId = `upload_${Date.now()}`;
  setUploadId(newUploadId);
  setShowProgress(true);
  
  try {
    // Pass uploadId to backend
    await uploadFromUrl(url, { uploadId: newUploadId });
  } catch (error) {
    // Handle error
  }
};

return (
  <Dialog>
    {/* ... existing code ... */}
    
    {showProgress && uploadId && (
      <TelegramUploadProgress
        uploadId={uploadId}
        onComplete={(result) => {
          toast.positive('Upload completed!');
          setShowProgress(false);
          onSuccess(result);
          onClose();
        }}
        onError={(error) => {
          toast.danger(error);
          setShowProgress(false);
        }}
      />
    )}
  </Dialog>
);
```

---

### 2.2 WebSocket Integration (Optional - پیشرفته)

**اگر می‌خواهید real-time واقعی داشته باشید**:

#### Laravel Broadcasting:

```php
// Event
namespace App\Events;

class TelegramUploadProgressUpdated implements ShouldBroadcast
{
    public string $uploadId;
    public array $progress;

    public function __construct(string $uploadId, array $progress)
    {
        $this->uploadId = $uploadId;
        $this->progress = $progress;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("telegram-upload.{$this->uploadId}");
    }
}
```

```tsx
// Frontend - با Laravel Echo
import Echo from 'laravel-echo';

Echo.private(`telegram-upload.${uploadId}`)
  .listen('TelegramUploadProgressUpdated', (e) => {
    setProgress(e.progress);
  });
```

---

### 2.3 چک‌لیست بخش 2

- [ ] ایجاد `TelegramUploadProgressService.php`
- [ ] Update `TelegramUrlUploadService.php` با progress tracking
- [ ] ایجاد `TelegramUploadProgressController.php`
- [ ] افزودن route برای progress
- [ ] ایجاد `TelegramUploadProgress.tsx`
- [ ] Integration در Dialog
- [ ] تست progress tracking
- [ ] تست polling
- [ ] (Optional) WebSocket setup
- [ ] (Optional) Broadcasting events

---

## ⏸️ بخش 3: Resume Upload

### زمان تخمینی: 8-12 ساعت

### هدف:
امکان ادامه آپلود بعد از قطع شدن

---

### 3.1 معماری Resume Upload

**چالش‌ها**:
1. تلگرام API از resume پشتیبانی نمی‌کند
2. باید chunked upload پیاده‌سازی کنیم
3. نیاز به ذخیره state آپلود

**راه‌حل**:
- Chunked download و upload
- ذخیره chunks completed در database/redis
- Resume از آخرین chunk موفق

---

### 3.2 فایل‌های مورد نیاز

#### Database:

##### Migration:
```php
// 2025_01_03_create_telegram_upload_sessions_table.php

Schema::create('telegram_upload_sessions', function (Blueprint $table) {
    $table->id();
    $table->string('session_id')->unique();
    $table->bigInteger('user_id')->nullable();
    $table->text('source_url');
    $table->bigInteger('total_size');
    $table->bigInteger('downloaded_size')->default(0);
    $table->bigInteger('uploaded_size')->default(0);
    $table->integer('total_chunks');
    $table->json('completed_chunks')->nullable();
    $table->string('temp_file_path')->nullable();
    $table->string('status'); // pending, downloading, uploading, completed, failed
    $table->text('error_message')->nullable();
    $table->timestamps();
    $table->timestamp('expires_at');
    
    $table->index(['user_id', 'status']);
    $table->index('expires_at');
});
```

#### Backend Service:

```php
namespace Common\Files\Telegram;

class TelegramResumableUploadService
{
    protected int $chunkSize = 5 * 1024 * 1024; // 5MB chunks

    /**
     * Start or resume upload
     */
    public function uploadWithResume(
        string $url,
        ?string $sessionId = null,
        array $options = []
    ): array {
        // Get or create session
        $session = $sessionId
            ? $this->getSession($sessionId)
            : $this->createSession($url, $options);

        if (!$session) {
            throw new \Exception('Invalid session');
        }

        try {
            // Download chunks
            $this->downloadChunks($session);
            
            // Upload to Telegram
            $result = $this->uploadToTelegram($session);
            
            // Complete session
            $this->completeSession($session, $result);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->markSessionFailed($session, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Download file in chunks with resume support
     */
    protected function downloadChunks($session): void
    {
        $completedChunks = $session->completed_chunks ?? [];
        $totalChunks = $session->total_chunks;

        for ($i = 0; $i < $totalChunks; $i++) {
            // Skip already downloaded chunks
            if (in_array($i, $completedChunks)) {
                continue;
            }

            $start = $i * $this->chunkSize;
            $end = min(($i + 1) * $this->chunkSize - 1, $session->total_size - 1);

            // Download chunk with range header
            $this->downloadChunk($session, $i, $start, $end);
            
            // Mark as completed
            $completedChunks[] = $i;
            $session->update([
                'completed_chunks' => $completedChunks,
                'downloaded_size' => ($i + 1) * $this->chunkSize,
            ]);
        }
    }

    /**
     * Download single chunk
     */
    protected function downloadChunk($session, int $chunkIndex, int $start, int $end): void
    {
        $ch = curl_init($session->source_url);
        
        // Range header for resumable download
        curl_setopt($ch, CURLOPT_RANGE, "{$start}-{$end}");
        
        $fp = fopen($session->temp_file_path, 'a'); // Append mode
        
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
        ]);

        $success = curl_exec($ch);
        
        curl_close($ch);
        fclose($fp);

        if (!$success) {
            throw new \Exception("Failed to download chunk {$chunkIndex}");
        }
    }

    /**
     * Resume upload command
     */
    public function resumeUpload(string $sessionId): array
    {
        $session = $this->getSession($sessionId);
        
        if (!$session) {
            throw new \Exception('Session not found');
        }

        if ($session->status === 'completed') {
            throw new \Exception('Upload already completed');
        }

        // Continue from where it stopped
        return $this->uploadWithResume($session->source_url, $sessionId);
    }

    /**
     * Cleanup expired sessions
     */
    public function cleanupExpiredSessions(): void
    {
        TelegramUploadSession::where('expires_at', '<', now())
            ->chunk(100, function ($sessions) {
                foreach ($sessions as $session) {
                    // Delete temp file
                    if ($session->temp_file_path && file_exists($session->temp_file_path)) {
                        @unlink($session->temp_file_path);
                    }
                    
                    $session->delete();
                }
            });
    }
}
```

---

### 3.3 Frontend

```tsx
export function ResumableUploadDialog() {
  const [sessions, setSessions] = useState<UploadSession[]>([]);

  // Load existing sessions on mount
  useEffect(() => {
    loadPendingSessions().then(setSessions);
  }, []);

  const handleResumeUpload = async (sessionId: string) => {
    try {
      const result = await resumeUpload(sessionId);
      toast.positive('Upload resumed and completed!');
      // Remove from list
      setSessions(prev => prev.filter(s => s.id !== sessionId));
    } catch (error) {
      toast.danger('Failed to resume upload');
    }
  };

  return (
    <div>
      <h3>آپلودهای ناتمام</h3>
      {sessions.map(session => (
        <div key={session.id} className="border p-4 rounded">
          <div className="flex justify-between items-center">
            <div>
              <div className="font-semibold">{session.filename}</div>
              <div className="text-sm text-muted">
                {session.downloaded_size} / {session.total_size} دانلود شده
              </div>
              <Progress 
                value={(session.downloaded_size / session.total_size) * 100} 
              />
            </div>
            <Button onClick={() => handleResumeUpload(session.id)}>
              ادامه آپلود
            </Button>
          </div>
        </div>
      ))}
    </div>
  );
}
```

---

### 3.4 چک‌لیست بخش 3

- [ ] ایجاد migration `telegram_upload_sessions`
- [ ] ایجاد model `TelegramUploadSession`
- [ ] ایجاد `TelegramResumableUploadService`
- [ ] پیاده‌سازی chunked download
- [ ] پیاده‌سازی resume logic
- [ ] ایجاد cleanup command
- [ ] ایجاد Resume UI component
- [ ] تست با قطع کردن manual
- [ ] تست با فایل‌های بزرگ
- [ ] تست cleanup

---

## 🔄 بخش 4: Auto-Retry System

### زمان تخمینی: 4-6 ساعت

### هدف:
تلاش مجدد خودکار برای آپلودهای ناموفق

---

### 4.1 Strategy

**Exponential Backoff**:
```
Retry 1: بعد از 5 ثانیه
Retry 2: بعد از 15 ثانیه (3x)
Retry 3: بعد از 45 ثانیه (3x)
Max: 3 تلاش
```

---

### 4.2 Implementation

#### Backend:

```php
namespace Common\Files\Telegram;

class TelegramAutoRetryService
{
    protected array $retryConfig = [
        'max_attempts' => 3,
        'base_delay' => 5, // seconds
        'multiplier' => 3,
    ];

    /**
     * Upload با auto-retry
     */
    public function uploadWithRetry(
        string $url,
        array $metadata = [],
        array $options = []
    ): array {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->retryConfig['max_attempts']) {
            try {
                $attempt++;
                
                Log::info("Upload attempt {$attempt}/{$this->retryConfig['max_attempts']}", [
                    'url' => $url,
                ]);

                // Try upload
                $result = app(TelegramUrlUploadService::class)
                    ->uploadFromUrl($url, $metadata, $options);

                // Success!
                return $result;

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                
                Log::warning("Upload attempt {$attempt} failed", [
                    'url' => $url,
                    'error' => $lastError,
                ]);

                // Check if should retry
                if (!$this->shouldRetry($e, $attempt)) {
                    break;
                }

                // Wait before retry (exponential backoff)
                if ($attempt < $this->retryConfig['max_attempts']) {
                    $delay = $this->calculateDelay($attempt);
                    
                    Log::info("Waiting {$delay}s before retry {$attempt}", [
                        'url' => $url,
                    ]);
                    
                    sleep($delay);
                }
            }
        }

        // All attempts failed
        throw new \Exception(
            "Failed after {$attempt} attempts. Last error: {$lastError}"
        );
    }

    /**
     * Calculate delay با exponential backoff
     */
    protected function calculateDelay(int $attempt): int
    {
        return $this->retryConfig['base_delay'] * pow(
            $this->retryConfig['multiplier'],
            $attempt - 1
        );
    }

    /**
     * Check if error is retryable
     */
    protected function shouldRetry(\Exception $e, int $attempt): bool
    {
        // Don't retry if max attempts reached
        if ($attempt >= $this->retryConfig['max_attempts']) {
            return false;
        }

        // Retryable errors
        $retryableErrors = [
            'timeout',
            'connection',
            'network',
            'temporary',
        ];

        $message = strtolower($e->getMessage());
        
        foreach ($retryableErrors as $error) {
            if (str_contains($message, $error)) {
                return true;
            }
        }

        // Don't retry for client errors (4xx)
        if (str_contains($message, '4')) {
            return false;
        }

        // Retry for server errors (5xx) and unknown
        return true;
    }
}
```

---

#### Integration:

```php
// در Controller
public function uploadSingle(Request $request): JsonResponse
{
    try {
        $useRetry = $request->boolean('auto_retry', true);
        
        if ($useRetry) {
            $result = app(TelegramAutoRetryService::class)
                ->uploadWithRetry(
                    $request->input('url'),
                    [
                        'name' => $request->input('name'),
                        'user_id' => auth()->id(),
                    ],
                    [
                        'caption' => $request->input('caption'),
                    ]
                );
        } else {
            // Normal upload without retry
            $result = app(TelegramUrlUploadService::class)
                ->uploadFromUrl(/* ... */);
        }

        return $this->success($result);
        
    } catch (\Exception $e) {
        return $this->error($e->getMessage(), 500);
    }
}
```

---

#### Frontend:

```tsx
// Checkbox در form
<FormCheckbox
  name="auto_retry"
  defaultChecked={true}
>
  تلاش مجدد خودکار در صورت خطا
</FormCheckbox>

// نمایش retry attempts
{isRetrying && (
  <div className="text-sm text-warning">
    در حال تلاش مجدد ({currentAttempt}/3)...
  </div>
)}
```

---

### 4.3 چک‌لیست بخش 4

- [ ] ایجاد `TelegramAutoRetryService`
- [ ] پیاده‌سازی exponential backoff
- [ ] پیاده‌سازی shouldRetry logic
- [ ] Integration در Controller
- [ ] افزودن option به frontend
- [ ] نمایش retry status
- [ ] تست با شبیه‌سازی timeout
- [ ] تست با server errors
- [ ] تست max attempts
- [ ] Logging برای debugging

---

## 🚀 بخش 5: Advanced Telegram Features

### زمان تخمینی: 10-12 ساعت

### 5.1 Video/Image Thumbnail Generation

**هدف**: تولید thumbnail برای video و image قبل از آپلود

```php
class TelegramThumbnailService
{
    public function generateThumbnail(string $filePath, string $type): ?string
    {
        if ($type === 'video') {
            return $this->generateVideoThumbnail($filePath);
        }
        
        if ($type === 'image') {
            return $this->generateImageThumbnail($filePath);
        }
        
        return null;
    }

    protected function generateVideoThumbnail(string $videoPath): string
    {
        // استفاده از FFmpeg
        $thumbnailPath = storage_path('app/telegram/thumbnails/') . uniqid() . '.jpg';
        
        $command = "ffmpeg -i {$videoPath} -ss 00:00:01 -vframes 1 -vf scale=320:-1 {$thumbnailPath}";
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception('Failed to generate video thumbnail');
        }
        
        return $thumbnailPath;
    }

    protected function generateImageThumbnail(string $imagePath): string
    {
        // استفاده از GD یا Intervention Image
        $image = Image::make($imagePath);
        
        $thumbnailPath = storage_path('app/telegram/thumbnails/') . uniqid() . '.jpg';
        
        $image->resize(320, null, function ($constraint) {
            $constraint->aspectRatio();
        })->save($thumbnailPath, 80);
        
        return $thumbnailPath;
    }
}
```

---

### 5.2 File Compression

**هدف**: فشرده‌سازی فایل‌ها قبل از آپلود (optional)

```php
class TelegramFileCompressor
{
    public function compress(string $filePath, string $type): string
    {
        switch ($type) {
            case 'image':
                return $this->compressImage($filePath);
            case 'video':
                return $this->compressVideo($filePath);
            case 'document':
                return $this->compressDocument($filePath);
            default:
                return $filePath; // No compression
        }
    }

    protected function compressImage(string $imagePath): string
    {
        $image = Image::make($imagePath);
        
        // فشرده‌سازی با کیفیت 85%
        $compressedPath = str_replace('.', '_compressed.', $imagePath);
        $image->save($compressedPath, 85);
        
        // اگر فایل فشرده شده بزرگ‌تر شد، از اصلی استفاده کن
        if (filesize($compressedPath) >= filesize($imagePath)) {
            @unlink($compressedPath);
            return $imagePath;
        }
        
        return $compressedPath;
    }

    protected function compressVideo(string $videoPath): string
    {
        // فشرده‌سازی با FFmpeg
        $compressedPath = str_replace('.', '_compressed.', $videoPath);
        
        $command = "ffmpeg -i {$videoPath} -vcodec h264 -acodec aac -strict -2 {$compressedPath}";
        
        exec($command);
        
        return file_exists($compressedPath) ? $compressedPath : $videoPath;
    }
}
```

---

### 5.3 Duplicate Detection

**هدف**: جلوگیری از آپلود مجدد فایل‌های تکراری

```php
class TelegramDuplicateDetector
{
    public function findDuplicate(string $filePath, int $userId): ?FileEntry
    {
        $hash = hash_file('sha256', $filePath);
        
        return FileEntry::where('user_id', $userId)
            ->where('hash', $hash)
            ->whereHas('telegramMetadata')
            ->first();
    }

    public function handleDuplicate(
        FileEntry $duplicate,
        array $options = []
    ): array {
        // گزینه 1: استفاده مجدد از فایل موجود
        if ($options['reuse'] ?? true) {
            return [
                'duplicate' => true,
                'file_entry' => $duplicate,
                'message' => 'File already exists, reusing existing upload',
            ];
        }
        
        // گزینه 2: آپلود مجدد با نام جدید
        return [
            'duplicate' => true,
            'action' => 'upload_new',
        ];
    }
}
```

---

### 5.4 Scheduled Upload

**هدف**: زمان‌بندی آپلود برای زمان‌های کم‌بار

```php
class TelegramScheduledUploadService
{
    public function scheduleUpload(
        string $url,
        \DateTime $scheduledAt,
        array $metadata = []
    ): string {
        $jobId = uniqid('scheduled_', true);
        
        // ذخیره در database
        TelegramScheduledUpload::create([
            'job_id' => $jobId,
            'url' => $url,
            'metadata' => json_encode($metadata),
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
        ]);
        
        // Schedule job
        TelegramScheduledUploadJob::dispatch($jobId)
            ->delay($scheduledAt);
        
        return $jobId;
    }
}
```

---

### 5.5 Batch Operations

**هدف**: عملیات دسته‌جمعی روی فایل‌های تلگرام

```php
class TelegramBatchOperations
{
    /**
     * حذف دسته‌جمعی
     */
    public function batchDelete(array $fileIds, int $userId): array
    {
        $results = [];
        
        foreach ($fileIds as $fileId) {
            try {
                $file = FileEntry::where('id', $fileId)
                    ->where('user_id', $userId)
                    ->firstOrFail();
                
                // حذف از تلگرام
                if ($file->telegramMetadata) {
                    app(TelegramStorageService::class)->deleteFile($file);
                }
                
                $results[] = [
                    'id' => $fileId,
                    'success' => true,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'id' => $fileId,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * Forward دسته‌جمعی
     */
    public function batchForward(
        array $fileIds,
        string $targetId,
        int $userId
    ): array {
        // مشابه batchDelete
    }

    /**
     * تغییر caption دسته‌جمعی
     */
    public function batchUpdateCaption(
        array $fileIds,
        string $caption,
        int $userId
    ): array {
        // پیاده‌سازی
    }
}
```

---

### 5.6 Storage Analytics

**هدف**: آمار و گزارش دقیق‌تر

```php
class TelegramStorageAnalytics
{
    public function getDetailedStatistics(int $userId): array
    {
        return [
            'total_files' => $this->getTotalFiles($userId),
            'total_size' => $this->getTotalSize($userId),
            'by_type' => $this->getStatsByType($userId),
            'by_month' => $this->getStatsByMonth($userId),
            'upload_methods' => $this->getUploadMethodStats($userId),
            'top_files' => $this->getTopFilesBySize($userId, 10),
            'recent_uploads' => $this->getRecentUploads($userId, 20),
            'failed_uploads' => $this->getFailedUploads($userId),
        ];
    }

    protected function getStatsByType(int $userId): array
    {
        return FileEntry::where('user_id', $userId)
            ->whereHas('telegramMetadata')
            ->selectRaw('type, COUNT(*) as count, SUM(file_size) as total_size')
            ->groupBy('type')
            ->get()
            ->map(function ($stat) {
                return [
                    'type' => $stat->type,
                    'count' => $stat->count,
                    'size' => $stat->total_size,
                    'size_formatted' => format_bytes($stat->total_size),
                ];
            })
            ->toArray();
    }

    protected function getStatsByMonth(int $userId): array
    {
        return TelegramFileMetadata::whereHas('fileEntry', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->selectRaw('
                DATE_FORMAT(uploaded_at, "%Y-%m") as month,
                COUNT(*) as count,
                SUM(original_file_size) as total_size
            ')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->toArray();
    }
}
```

---

### 5.7 چک‌لیست بخش 5

- [ ] پیاده‌سازی thumbnail generation
- [ ] پیاده‌سازی file compression
- [ ] پیاده‌سازی duplicate detection
- [ ] پیاده‌سازی scheduled upload
- [ ] پیاده‌سازی batch operations
- [ ] پیاده‌سازی storage analytics
- [ ] Frontend برای analytics
- [ ] Frontend برای batch operations
- [ ] تست همه features
- [ ] مستندسازی

---

## ⚡ بخش 6: Performance Optimizations

### زمان تخمینی: 6-8 ساعت

### 6.1 Parallel Downloads

```php
class TelegramParallelDownloadService
{
    protected int $maxConcurrent = 3;

    public function downloadMultiple(array $urls): array
    {
        $multiHandle = curl_multi_init();
        $handles = [];
        $results = [];

        // Setup all handles
        foreach ($urls as $index => $url) {
            $handle = $this->createHandle($url, $index);
            $handles[$index] = $handle;
            curl_multi_add_handle($multiHandle, $handle);
        }

        // Execute all
        $active = null;
        do {
            $mrc = curl_multi_exec($multiHandle, $active);
        } while ($mrc === CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc === CURLM_OK) {
            if (curl_multi_select($multiHandle) === -1) {
                usleep(100);
            }

            do {
                $mrc = curl_multi_exec($multiHandle, $active);
            } while ($mrc === CURLM_CALL_MULTI_PERFORM);
        }

        // Process results
        foreach ($handles as $index => $handle) {
            $results[$index] = curl_multi_getcontent($handle);
            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        return $results;
    }
}
```

---

### 6.2 Database Query Optimization

```php
// Eager loading
$files = FileEntry::with([
    'telegramMetadata',
    'user:id,name,email'
])
    ->where('user_id', $userId)
    ->latest()
    ->paginate(50);

// Index optimization
// در migration
$table->index(['user_id', 'created_at']);
$table->index(['user_id', 'disk_prefix']);

// Caching
Cache::remember("user_telegram_files:{$userId}", 300, function () use ($userId) {
    return FileEntry::where('user_id', $userId)
        ->whereHas('telegramMetadata')
        ->get();
});
```

---

### 6.3 Redis Queue Optimization

```php
// config/queue.php
'connections' => [
    'redis' => [
        // ...
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        
        // افزودن priority queues
        'after_commit' => false,
    ],
];

// استفاده از priority
TelegramBulkUrlUploadJob::dispatch($urls)
    ->onQueue('high-priority');
```

---

### 6.4 CDN Integration

```php
class TelegramCdnService
{
    public function getCdnUrl(FileEntry $file): string
    {
        $metadata = $file->telegramMetadata;
        
        if (!$metadata) {
            throw new \Exception('No Telegram metadata');
        }

        // Generate signed URL via CDN
        return $this->generateCdnUrl(
            $metadata->telegram_file_id,
            $metadata->upload_method
        );
    }

    protected function generateCdnUrl(string $fileId, string $method): string
    {
        // CDN proxy URL
        $baseUrl = config('services.telegram.cdn_url');
        
        // Signed URL with expiration
        $signature = $this->generateSignature($fileId);
        
        return "{$baseUrl}/files/{$method}/{$fileId}?sig={$signature}";
    }
}
```

---

### 6.5 چک‌لیست بخش 6

- [ ] پیاده‌سازی parallel downloads
- [ ] بهینه‌سازی database queries
- [ ] افزودن proper indexes
- [ ] Caching strategy
- [ ] Redis queue optimization
- [ ] (Optional) CDN integration
- [ ] تست performance
- [ ] Load testing
- [ ] Profiling و bottleneck analysis

---

## 🔐 بخش 7: Security Enhancements

### زمان تخمینی: 6-8 ساعت

### 7.1 URL Whitelist/Blacklist

```php
class TelegramUrlValidator
{
    protected array $whitelist = [
        'example.com',
        'trusted-domain.com',
    ];

    protected array $blacklist = [
        'malicious-site.com',
        'spam-domain.com',
    ];

    public function validateUrl(string $url): bool
    {
        $domain = parse_url($url, PHP_URL_HOST);

        // Check blacklist
        if ($this->isBlacklisted($domain)) {
            throw new \Exception('URL domain is blacklisted');
        }

        // Check whitelist (if enabled)
        if (config('telegram.url_whitelist_enabled')) {
            if (!$this->isWhitelisted($domain)) {
                throw new \Exception('URL domain is not whitelisted');
            }
        }

        return true;
    }

    protected function isWhitelisted(string $domain): bool
    {
        foreach ($this->whitelist as $allowed) {
            if (str_ends_with($domain, $allowed)) {
                return true;
            }
        }
        return false;
    }

    protected function isBlacklisted(string $domain): bool
    {
        foreach ($this->blacklist as $blocked) {
            if (str_contains($domain, $blocked)) {
                return true;
            }
        }
        return false;
    }
}
```

---

### 7.2 File Type Restrictions

```php
class TelegramFileTypeValidator
{
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'video/mp4',
        'application/pdf',
        // ...
    ];

    protected array $blockedExtensions = [
        'exe',
        'bat',
        'sh',
        'cmd',
    ];

    public function validate(string $filePath): bool
    {
        // Check MIME type
        $mimeType = mime_content_type($filePath);
        
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw new \Exception('File type not allowed');
        }

        // Check extension
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($extension), $this->blockedExtensions)) {
            throw new \Exception('File extension not allowed');
        }

        return true;
    }
}
```

---

### 7.3 Virus Scanning

```php
class TelegramVirusScanner
{
    public function scan(string $filePath): array
    {
        // استفاده از ClamAV
        $command = "clamscan --stdout {$filePath}";
        
        exec($command, $output, $returnCode);
        
        $infected = $returnCode !== 0;
        
        if ($infected) {
            Log::warning('Virus detected', [
                'file' => $filePath,
                'output' => implode("\n", $output),
            ]);
            
            // حذف فایل آلوده
            @unlink($filePath);
            
            throw new \Exception('Virus detected in file');
        }

        return [
            'clean' => true,
            'scanned_at' => now(),
        ];
    }
}
```

---

### 7.4 Rate Limiting

```php
// در Controller
use Illuminate\Support\Facades\RateLimiter;

public function uploadSingle(Request $request): JsonResponse
{
    $key = 'telegram-upload:' . auth()->id();
    
    if (RateLimiter::tooManyAttempts($key, $perMinute = 10)) {
        $seconds = RateLimiter::availableIn($key);
        
        return $this->error(
            "Too many uploads. Try again in {$seconds} seconds.",
            429
        );
    }
    
    RateLimiter::hit($key, 60);
    
    // ... upload logic
}
```

---

### 7.5 IP Blocking

```php
class TelegramIpBlocker
{
    public function isBlocked(string $ip): bool
    {
        return Cache::has("telegram_blocked_ip:{$ip}");
    }

    public function blockIp(string $ip, int $minutes = 60): void
    {
        Cache::put("telegram_blocked_ip:{$ip}", true, $minutes * 60);
        
        Log::warning('IP blocked', ['ip' => $ip]);
    }

    public function autoBlockOnFailure(string $ip): void
    {
        $key = "telegram_failures:{$ip}";
        $failures = Cache::get($key, 0) + 1;
        
        Cache::put($key, $failures, 3600); // 1 hour
        
        if ($failures >= 5) {
            $this->blockIp($ip, 60); // Block for 1 hour
        }
    }
}
```

---

### 7.6 Audit Logging

```php
class TelegramAuditLogger
{
    public function logUpload(array $data): void
    {
        TelegramAuditLog::create([
            'user_id' => $data['user_id'],
            'action' => 'upload',
            'url' => $data['url'] ?? null,
            'file_id' => $data['file_id'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => json_encode($data),
        ]);
    }

    public function logAccess(FileEntry $file): void
    {
        TelegramAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'download',
            'file_id' => $file->id,
            'ip_address' => request()->ip(),
        ]);
    }
}
```

---

### 7.7 چک‌لیست بخش 7

- [ ] پیاده‌سازی URL whitelist/blacklist
- [ ] پیاده‌سازی file type restrictions
- [ ] (Optional) پیاده‌سازی virus scanning
- [ ] افزودن rate limiting
- [ ] پیاده‌سازی IP blocking
- [ ] پیاده‌سازی audit logging
- [ ] افزودن CAPTCHA (optional)
- [ ] تست همه security features
- [ ] Security audit
- [ ] مستندسازی

---

## 📅 Timeline و Priority

### High Priority (2-3 هفته):
```
Week 1:
✅ بخش 1: Frontend UI (3 روز)
✅ بخش 2: Progress Tracking (2 روز)

Week 2:
✅ بخش 4: Auto-Retry (2 روز)
✅ بخش 7: Security (اولویت‌های بالا) (3 روز)

Week 3:
✅ Testing و Bug fixes
✅ Documentation
```

### Medium Priority (2-4 هفته بعد):
```
✅ بخش 5: Advanced Features (انتخابی)
✅ بخش 6: Performance Optimizations
```

### Low Priority (در صورت نیاز):
```
✅ بخش 3: Resume Upload (complex)
✅ بخش 5: سایر features پیشرفته
```

---

## ✅ چک‌لیست کامل Phase 8

### بخش 1: Frontend UI
- [ ] TelegramUrlUploadDialog
- [ ] SingleUrlForm
- [ ] BulkUrlsForm
- [ ] TelegramUrlUploadButton
- [ ] API integration
- [ ] Types و interfaces
- [ ] Styling و responsive
- [ ] Testing

### بخش 2: Progress Tracking
- [ ] TelegramUploadProgressService
- [ ] Update upload services
- [ ] Progress controller
- [ ] Frontend progress component
- [ ] Polling implementation
- [ ] (Optional) WebSocket

### بخش 3: Resume Upload
- [ ] Database schema
- [ ] ResumableUploadService
- [ ] Chunked download
- [ ] Resume logic
- [ ] Frontend UI
- [ ] Cleanup command

### بخش 4: Auto-Retry
- [ ] AutoRetryService
- [ ] Exponential backoff
- [ ] Retry logic
- [ ] Frontend integration
- [ ] Testing

### بخش 5: Advanced Features
- [ ] Thumbnail generation
- [ ] File compression
- [ ] Duplicate detection
- [ ] Scheduled upload
- [ ] Batch operations
- [ ] Storage analytics

### بخش 6: Performance
- [ ] Parallel downloads
- [ ] Query optimization
- [ ] Caching strategy
- [ ] Queue optimization
- [ ] Load testing

### بخش 7: Security
- [ ] URL validation
- [ ] File type restrictions
- [ ] Rate limiting
- [ ] IP blocking
- [ ] Audit logging
- [ ] (Optional) Virus scanning

---

## 📊 خلاصه Phase 8

```
تعداد فایل‌های جدید: 40+
تعداد فایل‌های به‌روزرسانی: 15+
خطوط کد تخمینی: 5000+
زمان تخمینی: 40-60 ساعت
سطح پیچیدگی: متوسط تا پیشرفته
```

### Features جدید:
✨ Frontend کامل URL Upload  
📊 Real-time progress tracking  
⏸️ Resume upload capability  
🔄 Auto-retry system  
🎯 Advanced Telegram features  
⚡ Performance optimizations  
🔐 Security enhancements  

---

**این پلن آماده پیاده‌سازی است! 🚀**

**توصیه**: شروع از High Priority items و سپس بر اساس نیاز، Medium و Low Priority را اضافه کنید.