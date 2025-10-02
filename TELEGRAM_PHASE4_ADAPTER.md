# Phase 4: Flysystem Adapter - مستندات کامل

## 📦 فایل‌های ایجاد شده

### 1. Core Adapter:
```
✅ /app/common/foundation/src/Files/Adapters/TelegramAdapter.php
```
- پیاده‌سازی FilesystemAdapter interface
- 500+ خط کد
- پشتیبانی از تمام متدهای Flysystem v3

### 2. Laravel Wrapper:
```
✅ /app/common/foundation/src/Files/Adapters/TelegramFilesystemAdapter.php
```
- Wrapper برای یکپارچه‌سازی با Laravel
- متدهای اضافی برای کار راحت‌تر

### 3. Storage Service:
```
✅ /app/common/foundation/src/Files/Telegram/TelegramStorageService.php
```
- سرویس سطح بالا برای مدیریت فایل‌ها
- یکپارچه‌سازی FileEntry و TelegramMetadata
- آماده برای استفاده در کنترلرها

---

## 🎯 معماری کلی

```
Laravel Storage Facade
    ↓
TelegramFilesystemAdapter (Laravel wrapper)
    ↓
TelegramAdapter (Flysystem v3)
    ↓
TelegramFileManager
    ↓
TelegramBotClient / TelegramUserClient
    ↓
Telegram API
```

---

## 🔧 متدهای پیاده‌سازی شده

### Flysystem Core Methods:

| متد | وضعیت | توضیحات |
|-----|-------|---------|
| `write()` | ✅ | نوشتن فایل |
| `writeStream()` | ✅ | نوشتن از stream |
| `read()` | ✅ | خواندن فایل |
| `readStream()` | ✅ | خواندن به stream |
| `delete()` | ✅ | حذف فایل |
| `deleteDirectory()` | ✅ | No-op (تلگرام دایرکتوری ندارد) |
| `createDirectory()` | ✅ | No-op |
| `setVisibility()` | ✅ | No-op (همیشه private) |
| `fileExists()` | ✅ | چک وجود فایل |
| `directoryExists()` | ✅ | همیشه false |
| `listContents()` | ✅ | لیست فایل‌ها |
| `move()` | ✅ | جابجایی فایل |
| `copy()` | ✅ | کپی فایل |
| `fileSize()` | ✅ | دریافت حجم |
| `mimeType()` | ✅ | دریافت MIME type |
| `lastModified()` | ✅ | آخرین تغییر |
| `visibility()` | ✅ | همیشه private |
| `checksum()` | ✅ | محاسبه checksum |

---

## 📚 راهنمای استفاده

### 1. تنظیمات اولیه

این کار در Phase 5 انجام می‌شود، اما برای مرجع:

```php
// در config/filesystems.php
'disks' => [
    'telegram' => [
        'driver' => 'telegram',
        'channel_id' => env('TELEGRAM_CHANNEL_ID'),
        'prefix' => '',
    ],
],
```

---

### 2. استفاده از Laravel Storage Facade

#### آپلود فایل:
```php
use Illuminate\Support\Facades\Storage;

// آپلود ساده
Storage::disk('telegram')->put('documents/file.pdf', $contents);

// آپلود از مسیر محلی
Storage::disk('telegram')->putFile('documents', '/local/path/file.pdf');

// آپلود با گزینه‌ها
Storage::disk('telegram')->put('images/photo.jpg', $contents, [
    'caption' => 'عکس جدید من',
]);
```

#### دانلود فایل:
```php
// خواندن محتوا
$contents = Storage::disk('telegram')->get('documents/file.pdf');

// دانلود به مسیر محلی
Storage::disk('telegram')->download('documents/file.pdf', '/local/save/path.pdf');
```

#### حذف فایل:
```php
Storage::disk('telegram')->delete('documents/file.pdf');
```

#### چک وجود:
```php
$exists = Storage::disk('telegram')->exists('documents/file.pdf');
```

#### لیست فایل‌ها:
```php
$files = Storage::disk('telegram')->files('documents');
$allFiles = Storage::disk('telegram')->allFiles();
```

#### دریافت اطلاعات:
```php
$size = Storage::disk('telegram')->size('documents/file.pdf');
$mimeType = Storage::disk('telegram')->mimeType('documents/file.pdf');
$lastModified = Storage::disk('telegram')->lastModified('documents/file.pdf');
```

---

### 3. استفاده از TelegramStorageService (توصیه می‌شود)

#### ایجاد Instance:
```php
use Common\Files\Telegram\TelegramStorageService;

$service = new TelegramStorageService();
// یا با disk سفارشی:
$service = new TelegramStorageService('telegram');
```

#### آپلود فایل با FileEntry:
```php
$result = $service->uploadFile(
    '/path/to/local/file.pdf',
    [
        'name' => 'مستندات مهم',
        'user_id' => auth()->id(),
        'owner_id' => auth()->id(),
    ],
    [
        'caption' => 'فایل PDF مهم',
    ]
);

// دسترسی به نتیجه:
$fileEntry = $result['file_entry'];
$metadata = $result['metadata'];
$uploadResult = $result['upload_result'];

echo "Uploaded via: " . $metadata->upload_method; // 'bot' or 'user'
echo "Message ID: " . $metadata->message_id;
```

#### دانلود فایل:
```php
$fileEntry = FileEntry::find(1);
$success = $service->downloadFile($fileEntry, '/path/to/save/file.pdf');
```

#### حذف فایل:
```php
$deleted = $service->deleteFile($fileEntry);
```

#### دریافت محتوا:
```php
$contents = $service->getFileContents($fileEntry);
```

#### لیست فایل‌ها:
```php
// همه فایل‌ها
$files = $service->listFiles();

// با فیلتر
$botFiles = $service->listFiles([
    'upload_method' => 'bot',
    'from_date' => now()->subDays(7),
]);
```

#### آمار:
```php
$stats = $service->getStatistics();

echo "Total uploads: " . $stats['total_uploads'];
echo "Bot uploads: " . $stats['bot_uploads'];
echo "User uploads: " . $stats['user_uploads'];
echo "Total size: " . $stats['total_size_formatted'];
echo "Success rate: " . $stats['success_rate'];
```

---

### 4. استفاده مستقیم از TelegramAdapter

```php
$adapter = Storage::disk('telegram')->getAdapter();

// دسترسی به manager
$manager = $adapter->manager;

// تست اتصال
$results = $manager->testConnections();

// بررسی امکان آپلود
$canUpload = \Common\Files\Telegram\TelegramFileManager::canUpload($fileSize);
```

---

## 🎨 نمونه کد کامل: Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\FileEntry;
use Common\Files\Telegram\TelegramStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TelegramFileController extends Controller
{
    protected TelegramStorageService $telegramStorage;

    public function __construct()
    {
        $this->telegramStorage = new TelegramStorageService();
    }

    /**
     * آپلود فایل
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:2097152', // 2GB
            'name' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $tempPath = $file->getPathname();

        try {
            $result = $this->telegramStorage->uploadFile(
                $tempPath,
                [
                    'name' => $request->name ?? $file->getClientOriginalName(),
                    'user_id' => auth()->id(),
                    'owner_id' => auth()->id(),
                ],
                [
                    'caption' => $request->name ?? 'Uploaded file',
                ]
            );

            return response()->json([
                'success' => true,
                'file' => $result['file_entry'],
                'upload_method' => $result['metadata']->upload_method,
                'message' => 'File uploaded successfully to Telegram',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * دانلود فایل
     */
    public function download($id)
    {
        $fileEntry = FileEntry::findOrFail($id);

        // Check permissions
        if ($fileEntry->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        try {
            $contents = $this->telegramStorage->getFileContents($fileEntry);

            return response($contents)
                ->header('Content-Type', $fileEntry->mime)
                ->header('Content-Disposition', 'attachment; filename="' . $fileEntry->name . '"');

        } catch (\Exception $e) {
            abort(500, 'Failed to download file: ' . $e->getMessage());
        }
    }

    /**
     * حذف فایل
     */
    public function delete($id)
    {
        $fileEntry = FileEntry::findOrFail($id);

        // Check permissions
        if ($fileEntry->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        try {
            $deleted = $this->telegramStorage->deleteFile($fileEntry);

            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'File deleted successfully' : 'Failed to delete file',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * لیست فایل‌ها
     */
    public function index(Request $request)
    {
        $filters = [
            'upload_method' => $request->method,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
        ];

        $files = $this->telegramStorage->listFiles(array_filter($filters));

        return response()->json([
            'files' => $files,
            'total' => $files->count(),
        ]);
    }

    /**
     * آمار
     */
    public function statistics()
    {
        $stats = $this->telegramStorage->getStatistics();

        return response()->json($stats);
    }
}
```

---

## 🔄 Flow Diagram

### آپلود:
```
User uploads file
    ↓
Controller receives file
    ↓
TelegramStorageService.uploadFile()
    ↓
Create FileEntry in database
    ↓
TelegramFileManager.uploadFile()
    ↓
Check file size → Select Bot or User client
    ↓
Upload to Telegram channel
    ↓
Create TelegramFileMetadata
    ↓
Return success
```

### دانلود:
```
User requests file
    ↓
Controller finds FileEntry
    ↓
Get TelegramFileMetadata
    ↓
TelegramFileManager.downloadFile()
    ↓
Download from Telegram (Bot or User)
    ↓
Return file contents
    ↓
Update last_accessed_at
```

---

## 🚨 نکات مهم

### 1. Path Mapping:
- تلگرام مفهوم path ندارد، فقط message_id
- ما path را در metadata JSON ذخیره می‌کنیم
- برای پروداکشن، باید FileEntry را مستقیم لینک کنیم

### 2. Directories:
- تلگرام دایرکتوری ندارد
- `createDirectory()` و `deleteDirectory()` no-op هستند
- می‌توانید از prefix در path استفاده کنید

### 3. Visibility:
- تمام فایل‌ها در کانال خصوصی همیشه private هستند
- `setVisibility()` تاثیری ندارد

### 4. Public URLs:
- تلگرام کانال‌های خصوصی URL عمومی ندارند
- باید یک route برای دانلود ایجاد کنید

### 5. Large Files:
- فایل‌های < 50MB: Bot API (سریع)
- فایل‌های 50MB-2GB: User Account (کندتر)
- بیشتر از 2GB: غیرممکن

---

## ✅ چک‌لیست Phase 4:

- [x] ایجاد TelegramAdapter (Flysystem v3)
- [x] پیاده‌سازی تمام متدهای الزامی
- [x] ایجاد TelegramFilesystemAdapter (Laravel wrapper)
- [x] ایجاد TelegramStorageService
- [x] یکپارچه‌سازی با FileEntry و Metadata
- [x] مستندسازی کامل

---

## ⏭️ آماده برای Phase 5:

Phase 5 شامل موارد زیر است:
- ایجاد **TelegramServiceProvider**
- ثبت driver در Laravel
- افزودن به `config/filesystems.php`
- ثبت در `CommonServiceProvider`

**آماده‌اید برای Phase 5؟**
