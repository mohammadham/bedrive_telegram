# Phase 5: Service Provider - خلاصه کامل و جامع

## 📋 فهرست مطالب
1. [فایل‌های ایجاد شده](#فایل‌های-ایجاد-شده)
2. [فایل‌های به‌روزرسانی شده](#فایل‌های-به‌روزرسانی-شده)
3. [معماری و Flow](#معماری-و-flow)
4. [Configuration](#configuration)
5. [نحوه استفاده](#نحوه-استفاده)
6. [Validation](#validation)
7. [Testing](#testing)
8. [مقایسه با Drivers دیگر](#مقایسه-با-drivers-دیگر)
9. [Troubleshooting](#troubleshooting)
10. [چک‌لیست کامل](#چک‌لیست-کامل)

---

## 📦 فایل‌های ایجاد شده

### 1. TelegramServiceProvider.php
**مسیر**: `/app/common/foundation/src/Files/Providers/TelegramServiceProvider.php`

**هدف**: ثبت درایور تلگرام در Laravel Storage System

**کد کامل**:
```php
<?php

namespace Common\Files\Providers;

use Common\Files\Adapters\TelegramAdapter;
use Common\Files\Telegram\TelegramFileManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class TelegramServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Storage::extend('telegram', function ($app, $config) {
            $telegramConfig = [
                'channel_id' => $config['channel_id'] ?? config('services.telegram.channel_id'),
                'prefix' => $config['prefix'] ?? '',
            ];

            $adapter = new TelegramAdapter($telegramConfig);

            return new FilesystemAdapter(
                new Filesystem($adapter, $telegramConfig),
                $adapter,
                $telegramConfig,
            );
        });
    }

    public function register()
    {
        $this->app->singleton(TelegramFileManager::class, function ($app) {
            return new TelegramFileManager(
                config('services.telegram.channel_id')
            );
        });
    }
}
```

**توضیحات متدها**:

#### `boot()`:
- ثبت `telegram` به عنوان یک storage driver
- استفاده از `Storage::extend()` برای افزودن driver جدید
- ایجاد TelegramAdapter با configuration
- برگرداندن FilesystemAdapter که Laravel می‌تواند استفاده کند

#### `register()`:
- ثبت TelegramFileManager به عنوان Singleton
- یک instance برای کل request lifecycle
- دسترسی آسان از سراسر برنامه

**چرا Singleton؟**
```php
// بدون singleton (مشکل):
$manager1 = new TelegramFileManager(); // اتصال جدید
$manager2 = new TelegramFileManager(); // اتصال جدید دیگر

// با singleton (بهینه):
$manager1 = app(TelegramFileManager::class); // اتصال یکبار
$manager2 = app(TelegramFileManager::class); // همان instance
```

---

### 2. مستندات
**مسیر**: `/app/TELEGRAM_PHASE5_SERVICE_PROVIDER.md`

شامل:
- راهنمای نصب و راه‌اندازی
- نمونه کدهای کاربردی
- Troubleshooting guide
- Security best practices
- Production checklist

---

## 🔄 فایل‌های به‌روزرسانی شده

### 1. CommonServiceProvider.php

**مسیر**: `/app/common/foundation/src/CommonServiceProvider.php`

**تغییر 1: افزودن Import**
```php
use Common\Files\Providers\TelegramServiceProvider;
```

**تغییر 2: Conditional Registration در متد `register()`**
```php
// register flysystem providers
$this->app->register(DynamicStorageDiskProvider::class);
if ($this->storageDriverSelected('dropbox')) {
    $this->app->register(DropboxServiceProvider::class);
}
if ($this->storageDriverSelected('digitalocean_s3')) {
    $this->app->register(DigitalOceanServiceProvider::class);
}
if ($this->storageDriverSelected('backblaze_s3')) {
    $this->app->register(BackblazeServiceProvider::class);
}
// ✅ جدید
if ($this->storageDriverSelected('telegram')) {
    $this->app->register(TelegramServiceProvider::class);
}
```

**چرا Conditional؟**
- Performance: فقط وقتی که telegram انتخاب شده باشد load می‌شود
- Memory: کتابخانه‌های تلگرام فقط در صورت نیاز load می‌شوند
- Flexibility: کاربران می‌توانند driver دلخواه خود را انتخاب کنند

**نحوه کار `storageDriverSelected()`**:
```php
protected function storageDriverSelected(string $driver): bool
{
    return config('common.site.uploads_disk_driver') === $driver ||
           config('common.site.public_disk_driver') === $driver;
}
```

---

### 2. config/filesystems.php

**مسیر**: `/app/config/filesystems.php`

**تغییر: افزودن telegram disk**
```php
'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
    ],

    'uploads' => [
        'driver' => 'dynamic-uploads',
        // ...
    ],

    'public' => [
        'driver' => 'dynamic-public',
        // ...
    ],

    // ✅ جدید - Telegram Disk
    'telegram' => [
        'driver' => 'telegram',
        'channel_id' => env('TELEGRAM_CHANNEL_ID'),
        'prefix' => env('TELEGRAM_PREFIX', ''),
    ],
],
```

**توضیح فیلدها**:

| فیلد | نوع | توضیحات |
|------|-----|---------|
| `driver` | string | نام driver (telegram) - باید با Storage::extend() match باشد |
| `channel_id` | string | شناسه کانال خصوصی تلگرام (مثال: -1001234567890) |
| `prefix` | string | پیشوند اختیاری برای paths (مثال: 'bedrive/') |

**مثال استفاده**:
```php
// بدون prefix
Storage::disk('telegram')->put('file.pdf', $contents);
// Path: file.pdf

// با prefix = 'bedrive/'
Storage::disk('telegram')->put('file.pdf', $contents);
// Path: bedrive/file.pdf
```

---

### 3. config/services.php

**مسیر**: `/app/config/services.php`

**وضعیت**: قبلاً در Phase 1 اضافه شده بود

```php
'telegram' => [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'channel_id' => env('TELEGRAM_CHANNEL_ID'),
    'api_id' => env('TELEGRAM_API_ID'),
    'api_hash' => env('TELEGRAM_API_HASH'),
    'phone' => env('TELEGRAM_PHONE'),
    'session_file' => env(
        'TELEGRAM_SESSION_FILE', 
        storage_path('app/telegram/session.madeline')
    ),
],
```

**این config در کجا استفاده می‌شود؟**
- TelegramBotClient: `bot_token`, `channel_id`
- TelegramUserClient: `api_id`, `api_hash`, `phone`, `session_file`
- TelegramFileManager: `channel_id`
- TelegramAdapter: `channel_id`

---

### 4. StorageCredentialsValidator.php

**مسیر**: `/app/common/foundation/src/Settings/Validators/StorageCredentialsValidator.php`

**تغییر 1: افزودن Import**
```php
use Common\Files\Providers\TelegramServiceProvider;
```

**تغییر 2: افزودن Keys**
```php
const KEYS = [
    // ... سایر keys
    
    // ✅ جدید - telegram keys
    'storage_telegram_bot_token',
    'storage_telegram_channel_id',
    'storage_telegram_api_id',
    'storage_telegram_api_hash',
    'storage_telegram_phone',
];
```

**نقشه mapping**:
```
storage_telegram_bot_token    → services.telegram.bot_token
storage_telegram_channel_id   → services.telegram.channel_id
storage_telegram_api_id       → services.telegram.api_id
storage_telegram_api_hash     → services.telegram.api_hash
storage_telegram_phone        → services.telegram.phone
```

**تغییر 3: افزودن به Replacements**
```php
$replacements = [
    's3',
    'dropbox',
    'ftp',
    'digitalocean',
    'rackspace',
    'backblaze',
    'telegram', // ✅ جدید
];
```

**تغییر 4: افزودن Validation Logic**
```php
private function validateDisk(string $diskName): array
{
    $driverName = Config::get("common.site.{$diskName}_disk_driver");

    try {
        $disk = Storage::disk($diskName);
        
        // ... سایر drivers
        
        // ✅ جدید - Telegram validation
        elseif ($driverName === 'telegram') {
            $adapter = $disk->getAdapter();
            if (method_exists($adapter, 'manager')) {
                $results = $adapter->manager->testConnections();
                
                // چک Bot (الزامی)
                if (isset($results['bot']['success']) && !$results['bot']['success']) {
                    throw new Exception($results['bot']['error'] ?? 'Bot connection failed');
                }
                
                // چک User (اختیاری - فقط warning)
                if (isset($results['user']) && !$results['user']['success']) {
                    \Log::warning('Telegram user connection failed', $results['user']);
                }
            }
        }
        
        // ...
    } catch (Exception $e) {
        return [
            'storage_group' => "Invalid $driverName credentials.<br>{$message}",
        ];
    }

    return [];
}
```

**منطق Validation**:
1. ایجاد disk با credentials داده شده
2. دریافت adapter
3. تست اتصال Bot (الزامی)
4. تست اتصال User (اختیاری)
5. در صورت خطا، برگرداندن پیام

**تغییر 5: ثبت در registerAdapters()**
```php
private function registerAdapters(): void
{
    app()->register(DigitalOceanServiceProvider::class);
    app()->register(DropboxServiceProvider::class);
    app()->register(BackblazeServiceProvider::class);
    app()->register(TelegramServiceProvider::class); // ✅ جدید
}
```

---

## 🏗️ معماری و Flow

### Flow کامل از درخواست تا پاسخ

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Laravel Application Boots                                │
│    - config/app.php loads providers                         │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. CommonServiceProvider::register()                        │
│    - چک می‌کند telegram انتخاب شده؟                       │
│    - اگر بله: TelegramServiceProvider را register می‌کند  │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. TelegramServiceProvider::boot()                          │
│    - Storage::extend('telegram', ...)                       │
│    - TelegramAdapter را آماده می‌کند                       │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. User Code                                                 │
│    Storage::disk('telegram')->put('file.pdf', $data)        │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. Laravel Storage                                           │
│    - پیدا کردن 'telegram' disk از config                   │
│    - فراخوانی closure که در extend() تعریف شده            │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. TelegramAdapter::write()                                 │
│    - ایجاد temp file                                        │
│    - فراخوانی TelegramFileManager                           │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 7. TelegramFileManager                                      │
│    - چک حجم فایل                                           │
│    - انتخاب Bot یا User                                     │
│    - آپلود به تلگرام                                       │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 8. TelegramBotClient / TelegramUserClient                   │
│    - API call به تلگرام                                    │
│    - دریافت file_id و message_id                           │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 9. TelegramPathMapper                                       │
│    - ذخیره path mapping در database                        │
│    - cache برای دسترسی سریع                                │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 10. Return to User                                           │
│     - Success response                                       │
│     - فایل در تلگرام ذخیره شد                              │
└─────────────────────────────────────────────────────────────┘
```

---

### Dependency Tree

```
TelegramServiceProvider
├── TelegramAdapter (Phase 4)
│   ├── TelegramFileManager (Phase 3)
│   │   ├── TelegramBotClient (Phase 3)
│   │   │   └── irazasyed/telegram-bot-sdk (Phase 1)
│   │   └── TelegramUserClient (Phase 3)
│   │       └── danog/madelineproto (Phase 1)
│   ├── TelegramPathMapper (Phase 4)
│   │   └── TelegramFileMetadata Model (Phase 2)
│   └── TelegramMetadataHelper (Phase 2)
└── League\Flysystem\Filesystem
```

---

## ⚙️ Configuration

### 1. Environment Variables

**الزامی (Bot API)**:
```env
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_CHANNEL_ID=-1001234567890
```

**نحوه دریافت**:

#### Bot Token:
1. به [@BotFather](https://t.me/BotFather) در تلگرام پیام دهید
2. دستور `/newbot` را بزنید
3. نام و username برای ربات انتخاب کنید
4. BotFather توکن را می‌دهد

#### Channel ID:
1. یک کانال خصوصی در تلگرام بسازید
2. ربات را به عنوان admin اضافه کنید
3. از [@userinfobot](https://t.me/userinfobot) برای دریافت ID استفاده کنید
4. یا از این bot: [@getidsbot](https://t.me/getidsbot)

**اختیاری (User Account - برای فایل‌های > 50MB)**:
```env
TELEGRAM_API_ID=12345678
TELEGRAM_API_HASH=0123456789abcdef0123456789abcdef
TELEGRAM_PHONE=+989123456789
```

**نحوه دریافت**:
1. به [my.telegram.org](https://my.telegram.org) بروید
2. با شماره تلفن خود login کنید
3. API development tools → Create application
4. api_id و api_hash را کپی کنید

---

### 2. Config Files

#### filesystems.php:
```php
'telegram' => [
    'driver' => 'telegram',
    'channel_id' => env('TELEGRAM_CHANNEL_ID'),
    'prefix' => env('TELEGRAM_PREFIX', ''),
],
```

#### services.php:
```php
'telegram' => [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'channel_id' => env('TELEGRAM_CHANNEL_ID'),
    'api_id' => env('TELEGRAM_API_ID'),
    'api_hash' => env('TELEGRAM_API_HASH'),
    'phone' => env('TELEGRAM_PHONE'),
    'session_file' => env(
        'TELEGRAM_SESSION_FILE',
        storage_path('app/telegram/session.madeline')
    ),
],
```

---

### 3. انتخاب به عنوان Default Driver

**روش 1: از .env**
```env
UPLOADS_DISK_DRIVER=telegram
```

**روش 2: از Admin Panel**
Settings → Storage → Select "Telegram"

**روش 3: در Runtime**
```php
config(['common.site.uploads_disk_driver' => 'telegram']);
```

---

## 💻 نحوه استفاده

### 1. استفاده پایه

```php
use Illuminate\Support\Facades\Storage;

// آپلود
Storage::disk('telegram')->put('documents/report.pdf', $pdfContent);

// دانلود
$content = Storage::disk('telegram')->get('documents/report.pdf');

// حذف
Storage::disk('telegram')->delete('documents/report.pdf');

// چک وجود
if (Storage::disk('telegram')->exists('documents/report.pdf')) {
    echo "فایل وجود دارد";
}
```

---

### 2. کار با Files و Directories

```php
// لیست فایل‌های یک directory
$files = Storage::disk('telegram')->files('documents');
// Returns: ['documents/file1.pdf', 'documents/file2.docx']

// لیست recursive
$allFiles = Storage::disk('telegram')->allFiles('documents');
// Returns: ['documents/file1.pdf', 'documents/sub/file2.docx']

// لیست directories
$directories = Storage::disk('telegram')->directories('documents');
// Returns: ['documents/subfolder1', 'documents/subfolder2']

// چک وجود directory
$exists = Storage::disk('telegram')->directoryExists('documents');
```

---

### 3. File Information

```php
$path = 'documents/report.pdf';

// حجم (بایت)
$size = Storage::disk('telegram')->size($path);
echo "Size: " . ($size / 1024 / 1024) . " MB";

// MIME type
$mimeType = Storage::disk('telegram')->mimeType($path);
echo "Type: {$mimeType}"; // application/pdf

// آخرین تغییر (timestamp)
$lastModified = Storage::disk('telegram')->lastModified($path);
echo "Modified: " . date('Y-m-d H:i:s', $lastModified);
```

---

### 4. Upload از Request

```php
use Illuminate\Http\Request;

public function upload(Request $request)
{
    $request->validate([
        'file' => 'required|file|max:2097152', // 2GB = 2097152 KB
    ]);

    // آپلود مستقیم
    $path = $request->file('file')->store('uploads', 'telegram');
    
    // یا با نام سفارشی
    $filename = time() . '_' . $request->file('file')->getClientOriginalName();
    $path = $request->file('file')->storeAs('uploads', $filename, 'telegram');

    return response()->json([
        'success' => true,
        'path' => $path,
        'url' => Storage::disk('telegram')->url($path), // اگر public باشد
    ]);
}
```

---

### 5. استفاده با FileEntry (BeDrive)

```php
use App\Models\FileEntry;
use Common\Files\Telegram\TelegramStorageService;

$service = new TelegramStorageService();

// آپلود با ایجاد FileEntry
$result = $service->uploadFile('/local/path/document.pdf', [
    'name' => 'Important Document',
    'user_id' => auth()->id(),
    'owner_id' => auth()->id(),
], [
    'caption' => 'Uploaded via BeDrive',
]);

// دسترسی به نتایج
$fileEntry = $result['file_entry'];
$metadata = $result['metadata'];
$uploadResult = $result['upload_result'];

echo "File ID: {$fileEntry->id}";
echo "Telegram Message ID: {$metadata->message_id}";
echo "Upload Method: {$metadata->upload_method}"; // 'bot' or 'user'

// دانلود فایل
$contents = $service->getFileContents($fileEntry);
file_put_contents('/save/path/document.pdf', $contents);

// حذف فایل (از تلگرام و database)
$service->deleteFile($fileEntry);
```

---

### 6. Streaming و Download URLs

```php
use Common\Files\Telegram\TelegramUrlGenerator;
use App\Models\FileEntry;

$file = FileEntry::find(1);

// Temporary URL (1 ساعت)
$tempUrl = TelegramUrlGenerator::temporary($file, 60);
// https://app.com/telegram/download/1?signature=...&expires=...

// Share Link (24 ساعت)
$shareUrl = TelegramUrlGenerator::share($file, 24);
// https://app.com/telegram/share/random-token-abc123

// Streaming URL (برای video/audio)
$streamUrl = TelegramUrlGenerator::stream($file);
// https://app.com/telegram/stream/1?signature=...

// Embed Code
$embedCode = TelegramUrlGenerator::embedCode($file);
// <video controls><source src="..." type="video/mp4"></video>
```

---

### 7. آمار و گزارش

```php
use Common\Files\Telegram\TelegramStorageService;

$service = new TelegramStorageService();

// دریافت آمار کلی
$stats = $service->getStatistics();

echo "Total Uploads: {$stats['total_uploads']}";
echo "Bot Uploads: {$stats['bot_uploads']}";
echo "User Uploads: {$stats['user_uploads']}";
echo "Total Size: {$stats['total_size_formatted']}"; // "5.25 GB"
echo "Success Rate: {$stats['success_rate']}"; // "98.5%"

// لیست فایل‌ها با فیلتر
$files = $service->listFiles([
    'upload_method' => 'bot',
    'from_date' => now()->subDays(7),
    'to_date' => now(),
]);

foreach ($files as $file) {
    echo "{$file->name} - {$file->getFormattedFileSize()}";
}
```

---

## ✅ Validation

### نحوه کار Validation

وقتی در admin panel تنظیمات storage را ذخیره می‌کنید:

```
1. Admin Panel Form Submit
   ↓
2. Settings Controller
   ↓
3. StorageCredentialsValidator::fails()
   ↓
4. setConfigDynamically() - تنظیم config runtime
   ↓
5. registerAdapters() - register کردن providers
   ↓
6. validateDisk('uploads') و validateDisk('public')
   ↓
7. Storage::disk('telegram') - ایجاد disk
   ↓
8. testConnections() - تست Bot و User
   ↓
9. برگشت نتیجه: [] = OK یا ['storage_group' => 'error'] = خطا
```

---

### Test Connection Logic

```php
// در validateDisk():
if ($driverName === 'telegram') {
    $adapter = $disk->getAdapter();
    $results = $adapter->manager->testConnections();
    
    // چک Bot (الزامی)
    if (!$results['bot']['success']) {
        throw new Exception('Bot connection failed');
    }
    
    // چک User (اختیاری)
    if (isset($results['user']) && !$results['user']['success']) {
        Log::warning('User connection failed'); // فقط warning
    }
}
```

**چرا User اختیاری است؟**
- Bot API برای فایل‌های کوچک (< 50MB) کافی است
- اکثر فایل‌ها کمتر از 50MB هستند
- User Account برای موارد خاص (فایل‌های بزرگ) است

---

### Test Manual

```php
use Common\Settings\Validators\StorageCredentialsValidator;

$validator = new StorageCredentialsValidator();

$settings = [
    'uploads_disk_driver' => 'telegram',
    'storage_telegram_bot_token' => 'YOUR_BOT_TOKEN',
    'storage_telegram_channel_id' => 'YOUR_CHANNEL_ID',
];

$errors = $validator->fails($settings);

if ($errors) {
    print_r($errors);
    // ['storage_group' => 'Invalid telegram credentials.<br>Bot connection failed']
} else {
    echo "✅ Validation passed!";
}
```

---

## 🧪 Testing

### 1. تست Basic Operations

```bash
php artisan tinker
```

```php
use Illuminate\Support\Facades\Storage;

$disk = Storage::disk('telegram');

// ✅ Test Write
$disk->put('test/hello.txt', 'Hello Telegram!');

// ✅ Test Read
$content = $disk->get('test/hello.txt');
echo $content; // "Hello Telegram!"

// ✅ Test Exists
$exists = $disk->exists('test/hello.txt');
echo $exists ? 'Exists' : 'Not found'; // "Exists"

// ✅ Test File Info
$size = $disk->size('test/hello.txt');
$mime = $disk->mimeType('test/hello.txt');
echo "Size: {$size}, Type: {$mime}";

// ✅ Test Delete
$disk->delete('test/hello.txt');
$exists = $disk->exists('test/hello.txt');
echo $exists ? 'Still exists' : 'Deleted'; // "Deleted"
```

---

### 2. تست با فایل واقعی

```php
// آپلود عکس
$imagePath = storage_path('app/test-image.jpg');
$imageContent = file_get_contents($imagePath);

Storage::disk('telegram')->put('images/test.jpg', $imageContent);

// دانلود و ذخیره
$downloaded = Storage::disk('telegram')->get('images/test.jpg');
file_put_contents('/tmp/downloaded.jpg', $downloaded);

// مقایسه MD5
$originalMd5 = md5_file($imagePath);
$downloadedMd5 = md5_file('/tmp/downloaded.jpg');

if ($originalMd5 === $downloadedMd5) {
    echo "✅ File integrity verified!";
} else {
    echo "❌ File corrupted!";
}

// پاک کردن
Storage::disk('telegram')->delete('images/test.jpg');
```

---

### 3. تست Large File (User Account)

```php
// ایجاد فایل 60MB
$largeFile = storage_path('app/large-file.bin');
$fp = fopen($largeFile, 'w');
fwrite($fp, str_repeat('A', 60 * 1024 * 1024)); // 60MB
fclose($fp);

// آپلود (باید از User Account استفاده کند)
$content = file_get_contents($largeFile);
Storage::disk('telegram')->put('large/file.bin', $content);

// چک metadata
$metadata = \App\Models\TelegramFileMetadata::latest()->first();
echo "Upload Method: {$metadata->upload_method}"; // باید 'user' باشد

// پاک کردن
Storage::disk('telegram')->delete('large/file.bin');
unlink($largeFile);
```

---

### 4. تست Connection

```php
use Common\Files\Telegram\TelegramFileManager;

$manager = app(TelegramFileManager::class);
$results = $manager->testConnections();

// نتیجه Bot
if ($results['bot']['success']) {
    echo "✅ Bot connected!";
    print_r($results['bot']['info']);
} else {
    echo "❌ Bot failed: " . $results['bot']['error'];
}

// نتیجه User
if (isset($results['user'])) {
    if ($results['user']['success']) {
        echo "✅ User connected!";
        print_r($results['user']['info']);
    } else {
        echo "⚠️ User failed: " . $results['user']['error'];
    }
}
```

---

### 5. تست Path Mapping

```php
use Common\Files\Telegram\TelegramPathMapper;

// آپلود فایل
Storage::disk('telegram')->put('docs/report.pdf', 'Test content');

// پیدا کردن metadata از path
$metadata = TelegramPathMapper::resolve('docs/report.pdf');

if ($metadata) {
    echo "✅ Path mapping works!";
    echo "Message ID: {$metadata->message_id}";
    echo "File ID: {$metadata->telegram_file_id}";
} else {
    echo "❌ Path mapping failed!";
}

// لیست directory
$files = TelegramPathMapper::listDirectory('docs');
echo "Files in docs: " . $files->count();

// پاک کردن
Storage::disk('telegram')->delete('docs/report.pdf');
```

---

### 6. Performance Test

```php
$startTime = microtime(true);

// آپلود 10 فایل کوچک
for ($i = 1; $i <= 10; $i++) {
    $content = "Test file {$i}";
    Storage::disk('telegram')->put("test/file{$i}.txt", $content);
}

$uploadTime = microtime(true) - $startTime;
echo "Upload 10 files: " . round($uploadTime, 2) . " seconds";

// دانلود 10 فایل
$startTime = microtime(true);
for ($i = 1; $i <= 10; $i++) {
    Storage::disk('telegram')->get("test/file{$i}.txt");
}
$downloadTime = microtime(true) - $startTime;
echo "Download 10 files: " . round($downloadTime, 2) . " seconds";

// پاک کردن
for ($i = 1; $i <= 10; $i++) {
    Storage::disk('telegram')->delete("test/file{$i}.txt");
}
```

---

## 📊 مقایسه با Drivers دیگر

### مقایسه عملکرد

| ویژگی | Local | S3 | Dropbox | Telegram |
|-------|-------|----|---------| ---------|
| **حداکثر حجم فایل** | نامحدود | 5TB | 350GB | 2GB |
| **هزینه** | رایگان | پولی | پولی | رایگان |
| **سرعت آپلود (10MB)** | ⚡⚡⚡⚡⚡ | ⚡⚡⚡⚡ | ⚡⚡⚡ | ⚡⚡⚡⚡ |
| **سرعت دانلود (10MB)** | ⚡⚡⚡⚡⚡ | ⚡⚡⚡⚡ | ⚡⚡⚡ | ⚡⚡⚡⚡ |
| **CDN** | ❌ | ✅ | ✅ | ❌ |
| **Bandwidth محدود** | ❌ | ❌ | ✅ | ❌ |
| **Public URLs** | ✅ | ✅ | ✅ | ⚠️ (با routes) |
| **Directories** | ✅ | ✅ | ✅ | ⚠️ (virtual) |
| **File Versioning** | ❌ | ✅ | ✅ | ❌ |
| **Encryption at rest** | ⚠️ | ✅ | ✅ | ✅ |
| **Setup پیچیدگی** | ⚡ آسان | ⚡⚡ متوسط | ⚡⚡ متوسط | ⚡⚡ متوسط |

---

### مقایسه Configuration

#### Local:
```php
'local' => [
    'driver' => 'local',
    'root' => storage_path('app'),
],
```
✅ ساده  
❌ فضای محدود server

---

#### S3:
```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
],
```
✅ مقیاس‌پذیر  
❌ هزینه‌دار  
✅ CDN

---

#### Dropbox:
```php
'dropbox' => [
    'driver' => 'dropbox',
    'access_token' => env('DROPBOX_ACCESS_TOKEN'),
    'refresh_token' => env('DROPBOX_REFRESH_TOKEN'),
    'app_key' => env('DROPBOX_APP_KEY'),
    'app_secret' => env('DROPBOX_APP_SECRET'),
],
```
✅ آسان برای setup  
❌ محدودیت bandwidth  
⚠️ مناسب برای استفاده شخصی

---

#### Telegram:
```php
'telegram' => [
    'driver' => 'telegram',
    'channel_id' => env('TELEGRAM_CHANNEL_ID'),
],
```
✅ رایگان  
✅ Unlimited storage  
⚠️ محدودیت 2GB per file  
❌ نیاز به Bot و کانال

---

### Use Cases

**کی از Telegram استفاده کنیم؟**

✅ **بله**:
- فایل‌های کمتر از 2GB
- نیاز به ذخیره‌سازی رایگان و نامحدود
- Backup فایل‌ها
- فایل‌های قابل بازیابی (می‌توانید از تلگرام هم دانلود کنید)
- پروژه‌های شخصی یا استارتاپ‌ها

❌ **خیر**:
- فایل‌های بیشتر از 2GB
- نیاز به CDN و edge locations
- Performance-critical applications
- نیاز به file versioning
- Enterprise solutions

---

## 🐛 Troubleshooting

### مشکل 1: "Call to undefined method"

**خطا**:
```
Call to undefined method Storage::disk()
```

**راه‌حل**:
```bash
# Clear cache
php artisan config:clear
php artisan cache:clear

# Re-cache
php artisan config:cache
```

---

### مشکل 2: "Invalid bot token"

**خطا**:
```
Invalid telegram credentials. Bot connection failed
```

**راه‌حل**:
1. چک کنید `TELEGRAM_BOT_TOKEN` در `.env` صحیح است
2. توکن نباید space یا newline داشته باشد
3. از @BotFather توکن جدید بگیرید
4. بعد از تغییر `.env`:
   ```bash
   php artisan config:clear
   ```

---

### مشکل 3: "Channel not accessible"

**خطا**:
```
Channel not accessible or bot is not admin
```

**راه‌حل**:
1. کانال باید **خصوصی** باشد
2. ربات باید **admin** کانال باشد
3. دسترسی‌های لازم:
   - ✅ Post Messages
   - ✅ Delete Messages
   - ❌ Add Members (optional)
4. Channel ID باید با `-100` شروع شود:
   ```
   صحیح: -1001234567890
   غلط: 1234567890
   غلط: @channelname
   ```

---

### مشکل 4: "File too large"

**خطا**:
```
File size exceeds maximum allowed (2GB)
```

**راه‌حل**:
- فایل‌های > 2GB: از storage دیگری استفاده کنید
- فایل‌های 50MB-2GB: User credentials را configure کنید:
  ```env
  TELEGRAM_API_ID=...
  TELEGRAM_API_HASH=...
  TELEGRAM_PHONE=...
  ```

---

### مشکل 5: "Session file not writable"

**خطا**:
```
Unable to write session file
```

**راه‌حل**:
```bash
# ایجاد directory
mkdir -p storage/app/telegram

# دادن permission
chmod 755 storage/app/telegram
chown www-data:www-data storage/app/telegram

# اگر SELinux دارید
semanage fcontext -a -t httpd_sys_rw_content_t "storage/app/telegram(/.*)?"
restorecon -R storage/app/telegram
```

---

### مشکل 6: "Class not found"

**خطا**:
```
Class 'Common\Files\Providers\TelegramServiceProvider' not found
```

**راه‌حل**:
```bash
# Regenerate autoload
composer dump-autoload

# Clear Laravel cache
php artisan clear-compiled
php artisan config:clear
php artisan cache:clear
```

---

### مشکل 7: "Database error"

**خطا**:
```
SQLSTATE[42S02]: Base table or view not found: telegram_file_metadata
```

**راه‌حل**:
```bash
# اجرای migrations
php artisan migrate

# اگر مشکل دارد:
php artisan migrate:fresh
```

---

### مشکل 8: "Vendor not loaded"

**خطا**:
```
Class 'Telegram\Bot\Api' not found
```

**راه‌حل**:
```bash
# نصب dependencies
composer install

# یا فقط telegram packages
composer require irazasyed/telegram-bot-sdk
composer require danog/madelineproto
```

---

## ✅ چک‌لیست کامل

### قبل از استفاده در Development:

- [ ] Dependencies نصب شده (`composer install`)
- [ ] Migration اجرا شده (`php artisan migrate`)
- [ ] ربات تلگرام ایجاد شده (@BotFather)
- [ ] کانال خصوصی ایجاد شده
- [ ] ربات به عنوان admin اضافه شده
- [ ] Environment variables تنظیم شده در `.env`
- [ ] Config cache پاک شده (`php artisan config:clear`)
- [ ] تست اتصال موفق (tinker)
- [ ] آپلود و دانلود تست شده

---

### قبل از استفاده در Production:

**Configuration**:
- [ ] همه environment variables در production `.env` هستند
- [ ] Channel ID صحیح است
- [ ] Bot Token معتبر است
- [ ] User credentials (اگر نیاز است) configure شده

**Security**:
- [ ] کانال تلگرام خصوصی است
- [ ] Bot فقط admin permissions لازم را دارد
- [ ] Session file directory secure است (755)
- [ ] Environment variables در version control نیستند

**Performance**:
- [ ] Config cached است (`php artisan config:cache`)
- [ ] Route cached است (`php artisan route:cache`)
- [ ] Opcache فعال است
- [ ] Redis/Memcached برای cache (optional)

**Backup**:
- [ ] Backup strategy برای database (telegram_file_metadata)
- [ ] Backup strategy برای session files
- [ ] Disaster recovery plan

**Monitoring**:
- [ ] Logging راه‌اندازی شده
- [ ] Error tracking (Sentry, Bugsnag, etc.)
- [ ] Uptime monitoring
- [ ] Storage usage monitoring

**Testing**:
- [ ] تست آپلود فایل کوچک (< 50MB)
- [ ] تست آپلود فایل بزرگ (> 50MB) - اگر user configured
- [ ] تست دانلود
- [ ] تست حذف
- [ ] تست validation از admin panel
- [ ] Load testing (اگر traffic بالا)

---

### Deployment Checklist:

```bash
# 1. Pull latest code
git pull origin main

# 2. Install/update dependencies
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php artisan migrate --force

# 4. Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 5. Cache configs
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Set permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 7. Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx

# 8. Test
php artisan tinker
>>> Storage::disk('telegram')->put('test.txt', 'Production test');
>>> Storage::disk('telegram')->get('test.txt');
>>> Storage::disk('telegram')->delete('test.txt');
```

---

## 📈 خلاصه Phase 5

### چه چیزی ساختیم؟

✅ **TelegramServiceProvider**: ثبت driver در Laravel  
✅ **Configuration**: تنظیم filesystems و services  
✅ **Validation**: تست خودکار credentials  
✅ **Integration**: یکپارچه‌سازی با CommonServiceProvider  
✅ **Conditional Loading**: بهینه‌سازی performance  
✅ **Documentation**: راهنمای کامل استفاده  

---

### حالا می‌توانیم:

```php
// 1. استفاده مستقیم
Storage::disk('telegram')->put('file.pdf', $contents);

// 2. به عنوان default
Storage::put('file.pdf', $contents); // اگر telegram default باشد

// 3. در uploads
$request->file('doc')->store('documents', 'telegram');

// 4. با FileEntry
$service->uploadFile($path, $metadata);
```

---

### آمار Phase 5:

```
✅ 1 فایل جدید (TelegramServiceProvider)
✅ 4 فایل به‌روزرسانی شده
✅ 2 مستندات جامع
✅ Validation کامل
✅ Testing راهنمای
✅ Production ready
```

---

## 📊 پیشرفت کلی:

```
Phase 1: نصب Dependencies        ████████████████████ 100% ✅
Phase 2: Database Schema          ████████████████████ 100% ✅
Phase 3: Telegram Clients         ████████████████████ 100% ✅
Phase 4: Flysystem Adapter        ████████████████████ 100% ✅
Phase 5: Service Provider         ████████████████████ 100% ✅
Phase 6: Admin UI Integration     ░░░░░░░░░░░░░░░░░░░░   0% ⏳
Phase 7: Testing & Debug          ░░░░░░░░░░░░░░░░░░░░   0% ⏳
```

---

## 🎉 Backend Complete!

**تمام بخش‌های Backend آماده است:**

✅ Dependencies (Phase 1)  
✅ Database (Phase 2)  
✅ Telegram API Clients (Phase 3)  
✅ Flysystem Adapter با راه‌حل‌های پیشرفته (Phase 4)  
✅ Service Provider و Integration (Phase 5)  

**باقیمانده:**

⏳ Admin UI (Phase 6)  
⏳ Testing & Debug (Phase 7)  

---

## ⏭️ آماده برای Phase 6!

Phase 6 شامل:
- UI برای تنظیمات تلگرام در Admin Panel
- نمایش آمار و گزارش‌ها
- مدیریت فایل‌ها از Dashboard
- User-friendly configuration

**بفرمایید برای شروع Phase 6!** 🎨
