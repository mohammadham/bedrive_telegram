# Phase 3: Telegram Clients - مستندات کامل

## 📦 فایل‌های ایجاد شده

### 1. Exception Classes:
```
✅ /app/common/foundation/src/Files/Telegram/Exceptions/TelegramException.php
✅ /app/common/foundation/src/Files/Telegram/Exceptions/TelegramUploadException.php
✅ /app/common/foundation/src/Files/Telegram/Exceptions/TelegramDownloadException.php
✅ /app/common/foundation/src/Files/Telegram/Exceptions/TelegramAuthException.php
✅ /app/common/foundation/src/Files/Telegram/Exceptions/TelegramConfigException.php
```

### 2. Interface:
```
✅ /app/common/foundation/src/Files/Telegram/Contracts/TelegramClientInterface.php
```

### 3. Client Classes:
```
✅ /app/common/foundation/src/Files/Telegram/TelegramBotClient.php
✅ /app/common/foundation/src/Files/Telegram/TelegramUserClient.php
✅ /app/common/foundation/src/Files/Telegram/TelegramFileManager.php
```

---

## 🎯 معماری کلی

```
TelegramFileManager
    ├── TelegramBotClient (< 50MB)
    │   └── Telegram Bot API (irazasyed/telegram-bot-sdk)
    └── TelegramUserClient (up to 2GB)
        └── MadelineProto (danog/madelineproto)
```

---

## 📚 راهنمای استفاده

### 1. TelegramBotClient (فایل‌های < 50MB)

#### ایجاد Instance:
```php
use Common\Files\Telegram\TelegramBotClient;

$botClient = new TelegramBotClient();
// یا با token سفارشی:
$botClient = new TelegramBotClient('YOUR_BOT_TOKEN');
```

#### آپلود فایل:
```php
$result = $botClient->uploadFile(
    '/path/to/file.jpg',
    '-1001234567890', // Channel ID
    [
        'caption' => 'توضیحات فایل',
        'filename' => 'custom_name.jpg'
    ]
);

// نتیجه:
// [
//     'success' => true,
//     'file_id' => 'BQACAgQAAxkBAAI...',
//     'file_unique_id' => 'AgADAgADyqcxG',
//     'message_id' => 12345,
//     'file_size' => 5242880,
//     'mime_type' => 'image/jpeg',
//     'uploaded_at' => '2024-10-02 12:00:00'
// ]
```

#### دانلود فایل:
```php
$success = $botClient->downloadFile(
    'BQACAgQAAxkBAAI...',
    '/path/to/save/file.jpg'
);
```

#### حذف فایل:
```php
$deleted = $botClient->deleteFile('-1001234567890', 12345);
```

#### دریافت اطلاعات فایل:
```php
$info = $botClient->getFileInfo('BQACAgQAAxkBAAI...');
```

---

### 2. TelegramUserClient (فایل‌های 50MB تا 2GB)

#### ایجاد Instance:
```php
use Common\Files\Telegram\TelegramUserClient;

$userClient = new TelegramUserClient();
// یا با تنظیمات سفارشی:
$userClient = new TelegramUserClient(
    123456,           // API ID
    'your_api_hash',  // API Hash
    '/path/to/session.madeline'
);
```

#### آپلود فایل:
```php
$result = $userClient->uploadFile(
    '/path/to/large-file.mp4',
    '-1001234567890',
    [
        'caption' => 'ویدیوی بزرگ',
        'filename' => 'video.mp4'
    ]
);

// نتیجه:
// [
//     'success' => true,
//     'file_id' => '5472547544',
//     'message_id' => 12346,
//     'file_size' => 104857600,
//     'mime_type' => 'video/mp4',
//     'uploaded_at' => '2024-10-02 12:05:00'
// ]
```

#### دانلود فایل:
```php
// برای User Client، fileId باید به فرمت channel_id:message_id باشد
$success = $userClient->downloadFile(
    '-1001234567890:12346',
    '/path/to/save/video.mp4'
);
```

#### حذف فایل:
```php
$deleted = $userClient->deleteFile('-1001234567890', 12346);
```

---

### 3. TelegramFileManager (انتخاب خودکار)

#### استفاده ساده:
```php
use Common\Files\Telegram\TelegramFileManager;

$manager = new TelegramFileManager();

// آپلود خودکار (خود تشخیص می‌دهد از bot یا user استفاده کند)
$result = $manager->uploadFile('/path/to/any-size-file.zip');

// فایل 30MB → استفاده از Bot API
// فایل 100MB → استفاده از User Account
```

#### آپلود با گزینه‌ها:
```php
$result = $manager->uploadFile(
    '/path/to/file.pdf',
    '-1001234567890', // Channel ID (optional)
    [
        'caption' => 'فایل PDF',
        'filename' => 'document.pdf'
    ]
);

echo "Uploaded via: " . $result['upload_method']; // 'bot' or 'user'
```

#### دانلود:
```php
// دانلود با Bot API
$manager->downloadFile(
    'BQACAgQAAxkBAAI...',
    '/path/to/save.jpg',
    'bot'
);

// دانلود با User Account
$manager->downloadFile(
    '-1001234567890:12346',
    '/path/to/save.mp4',
    'user'
);
```

#### بررسی امکان آپلود:
```php
$fileSize = 75 * 1024 * 1024; // 75MB
$check = TelegramFileManager::canUpload($fileSize);

// نتیجه:
// [
//     'can_upload' => true,
//     'method' => 'user',
//     'reason' => 'File size requires User Account upload (50MB - 2GB)'
// ]
```

#### تست اتصال:
```php
$results = $manager->testConnections();

// نتیجه:
// [
//     'bot' => [
//         'success' => true,
//         'info' => ['id' => 123456, 'username' => 'my_bot']
//     ],
//     'user' => [
//         'success' => true,
//         'info' => ['id' => 789012, 'username' => 'myusername']
//     ]
// ]
```

---

## 🔧 Exception Handling

### استفاده از Try-Catch:
```php
use Common\Files\Telegram\Exceptions\TelegramUploadException;
use Common\Files\Telegram\Exceptions\TelegramAuthException;

try {
    $manager = new TelegramFileManager();
    $result = $manager->uploadFile('/path/to/file.jpg');
    
} catch (TelegramAuthException $e) {
    // مشکل احراز هویت
    echo "Authentication failed: " . $e->getMessage();
    
} catch (TelegramUploadException $e) {
    // مشکل آپلود
    echo "Upload failed: " . $e->getMessage();
    $context = $e->getContext();
    
} catch (\Exception $e) {
    // خطای عمومی
    echo "Error: " . $e->getMessage();
}
```

### Exception Types:

#### 1. TelegramUploadException:
```php
// فایل بزرگتر از حد مجاز
TelegramUploadException::fileTooLarge($fileSize, $maxSize);

// فایل نامعتبر
TelegramUploadException::invalidFile('File not found');

// دسترسی به کانال
TelegramUploadException::channelNotAccessible($channelId);

// آپلود ناموفق
TelegramUploadException::uploadFailed($reason, $context);
```

#### 2. TelegramDownloadException:
```php
// فایل پیدا نشد
TelegramDownloadException::fileNotFound($fileId);

// دانلود ناموفق
TelegramDownloadException::downloadFailed($reason, $context);
```

#### 3. TelegramAuthException:
```php
// توکن نامعتبر
TelegramAuthException::invalidToken();

// اطلاعات ورود نامعتبر
TelegramAuthException::invalidCredentials($reason);

// session منقضی شده
TelegramAuthException::sessionExpired();
```

#### 4. TelegramConfigException:
```php
// تنظیمات گم شده
TelegramConfigException::missingConfig('bot_token');

// تنظیمات نامعتبر
TelegramConfigException::invalidConfig('channel_id', 'Invalid format');
```

---

## 📊 مقایسه Bot API vs User Account

| ویژگی | Bot API | User Account (MTProto) |
|-------|---------|----------------------|
| حداکثر حجم | 50 MB | 2 GB |
| سرعت | سریع | متوسط |
| پیچیدگی | ساده | پیچیده‌تر |
| نیاز به احراز هویت | Bot Token | API ID + Hash + Phone |
| Session | بدون نیاز | نیاز به session file |
| محدودیت Rate | بالاتر | پایین‌تر |
| پشتیبانی File Types | همه | همه |

---

## 🎨 نمونه کد کامل:

```php
<?php

use Common\Files\Telegram\TelegramFileManager;
use Common\Files\Telegram\TelegramMetadataHelper;
use App\Models\FileEntry;

class TelegramUploadService
{
    protected TelegramFileManager $manager;

    public function __construct()
    {
        $this->manager = new TelegramFileManager();
    }

    public function uploadAndStore(string $filePath, FileEntry $fileEntry): array
    {
        try {
            // 1. بررسی امکان آپلود
            $fileSize = filesize($filePath);
            $canUpload = TelegramFileManager::canUpload($fileSize);
            
            if (!$canUpload['can_upload']) {
                throw new \Exception($canUpload['reason']);
            }

            // 2. آپلود فایل
            $result = $this->manager->uploadFile($filePath, null, [
                'caption' => $fileEntry->name,
                'filename' => $fileEntry->file_name,
            ]);

            // 3. ذخیره metadata
            $metadata = TelegramMetadataHelper::createMetadata($fileEntry, [
                'file_id' => $result['file_id'],
                'message_id' => $result['message_id'],
                'channel_id' => $result['channel_id'],
                'upload_method' => $result['upload_method'],
                'upload_status' => 'completed',
                'uploaded_at' => now(),
            ]);

            // 4. به‌روزرسانی وضعیت
            $metadata->markAsCompleted();

            return [
                'success' => true,
                'file_entry_id' => $fileEntry->id,
                'telegram_file_id' => $result['file_id'],
                'message_id' => $result['message_id'],
                'upload_method' => $result['upload_method'],
            ];

        } catch (\Exception $e) {
            // ذخیره خطا در metadata
            if (isset($metadata)) {
                $metadata->markAsFailed($e->getMessage());
            }

            throw $e;
        }
    }

    public function downloadAndSave(FileEntry $fileEntry, string $savePath): bool
    {
        $metadata = $fileEntry->telegramMetadata;
        
        if (!$metadata) {
            throw new \Exception('No Telegram metadata found');
        }

        // تعیین fileId بر اساس روش آپلود
        $fileId = $metadata->isUploadedViaBot()
            ? $metadata->telegram_file_id
            : "{$metadata->channel_id}:{$metadata->message_id}";

        return $this->manager->downloadFile(
            $fileId,
            $savePath,
            $metadata->upload_method
        );
    }
}
```

---

## ✅ چک‌لیست Phase 3:

- [x] ایجاد Exception classes (5 کلاس)
- [x] ایجاد TelegramClientInterface
- [x] پیاده‌سازی TelegramBotClient
- [x] پیاده‌سازی TelegramUserClient
- [x] پیاده‌سازی TelegramFileManager
- [x] مستندسازی کامل

---

## ⏭️ آماده برای Phase 4:

Phase 4 شامل موارد زیر است:
- پیاده‌سازی **Flysystem Adapter** (TelegramAdapter)
- یکپارچه‌سازی با Laravel Storage
- متدهای Flysystem: write, read, delete, listContents, has, getSize, getMimetype

**آماده‌اید برای Phase 4؟**
