# Phase 2: Database Schema - مستندات کامل

## 📊 ساختار جدول `telegram_file_metadata`

### هدف:
ذخیره اطلاعات خاص تلگرام برای فایل‌های آپلود شده

### فیلدهای جدول:

| فیلد | نوع | توضیحات |
|------|-----|---------|
| `id` | BIGINT | شناسه یکتا |
| `file_entry_id` | BIGINT | Foreign Key به `file_entries` (unique) |
| `telegram_file_id` | VARCHAR(255) | شناسه فایل در تلگرام |
| `telegram_file_unique_id` | VARCHAR(255) | شناسه یکتای دائمی فایل |
| `message_id` | BIGINT | شناسه پیام در کانال |
| `channel_id` | VARCHAR(100) | شناسه کانال تلگرام |
| `upload_method` | ENUM | `bot` یا `user` |
| `original_file_size` | BIGINT | حجم اصلی فایل (بایت) |
| `original_mime_type` | VARCHAR(100) | نوع MIME اصلی |
| `telegram_mime_type` | VARCHAR(100) | نوع MIME در تلگرام |
| `session_file` | VARCHAR(255) | مسیر فایل session (برای user uploads) |
| `telegram_file_type` | ENUM | نوع فایل تلگرام (document, photo, video, ...) |
| `upload_status` | ENUM | وضعیت آپلود (pending, uploading, completed, failed, deleted) |
| `error_message` | TEXT | پیام خطا در صورت شکست |
| `uploaded_at` | TIMESTAMP | زمان آپلود موفق |
| `last_accessed_at` | TIMESTAMP | آخرین زمان دسترسی |
| `metadata` | JSON | اطلاعات اضافی (ابعاد، مدت، کدک و ...) |
| `created_at` | TIMESTAMP | زمان ایجاد رکورد |
| `updated_at` | TIMESTAMP | زمان به‌روزرسانی |
| `deleted_at` | TIMESTAMP | زمان حذف نرم (Soft Delete) |

### Indexes:
- `file_entry_id` - UNIQUE
- `telegram_file_id` - INDEX
- `message_id` - INDEX
- `channel_id` - INDEX
- `upload_method` - INDEX
- `upload_status` - INDEX
- `upload_method + upload_status` - COMPOSITE INDEX
- `channel_id + message_id` - COMPOSITE INDEX
- `uploaded_at` - INDEX

---

## 📁 فایل‌های ایجاد شده:

### 1. Migration:
```
/app/database/migrations/2025_10_02_095034_create_telegram_file_metadata_table.php
```

### 2. Model:
```
/app/app/Models/TelegramFileMetadata.php
```

### 3. Helper Class:
```
/app/common/foundation/src/Files/Telegram/TelegramMetadataHelper.php
```

### 4. Seeder:
```
/app/database/seeders/TelegramMetadataSeeder.php
```

---

## 🔗 Relationships:

### FileEntry → TelegramFileMetadata (One-to-One):
```php
// در /app/app/Models/FileEntry.php
public function telegramMetadata(): HasOne
{
    return $this->hasOne(TelegramFileMetadata::class, 'file_entry_id');
}
```

### استفاده:
```php
$fileEntry = FileEntry::find(1);
$telegramData = $fileEntry->telegramMetadata;

if ($telegramData) {
    echo "Telegram File ID: " . $telegramData->telegram_file_id;
    echo "Upload Method: " . $telegramData->upload_method;
}
```

---

## 🛠️ Helper Methods:

### TelegramMetadataHelper:

#### 1. تعیین روش آپلود:
```php
$method = TelegramMetadataHelper::determineUploadMethod(60 * 1024 * 1024); // 60MB
// Returns: 'user' (چون بیشتر از 50MB است)
```

#### 2. تعیین نوع فایل تلگرام:
```php
$type = TelegramMetadataHelper::determineTelegramFileType('image/jpeg');
// Returns: 'photo'
```

#### 3. بررسی امکان آپلود:
```php
$result = TelegramMetadataHelper::canUploadFile(100 * 1024 * 1024); // 100MB
// Returns:
// [
//     'can_upload' => true,
//     'method' => 'user',
//     'reason' => 'File size requires User Account upload (50MB - 2GB)'
// ]
```

#### 4. ایجاد metadata:
```php
$metadata = TelegramMetadataHelper::createMetadata($fileEntry, [
    'file_id' => 'BQACAgQAAxkBAAI...',
    'message_id' => 12345,
    'channel_id' => '-1001234567890',
    'upload_method' => 'bot',
]);
```

#### 5. دریافت آمار:
```php
$stats = TelegramMetadataHelper::getUploadStatistics();
// Returns:
// [
//     'total_uploads' => 150,
//     'bot_uploads' => 120,
//     'user_uploads' => 30,
//     'completed_uploads' => 145,
//     'failed_uploads' => 5,
//     'total_size' => 5368709120,
//     'total_size_formatted' => '5.00 GB',
//     'success_rate' => '96.67%'
// ]
```

---

## 🎯 Model Methods:

### TelegramFileMetadata:

```php
// بررسی روش آپلود
$metadata->isUploadedViaBot(); // true/false
$metadata->isUploadedViaUserAccount(); // true/false

// بررسی وضعیت
$metadata->isUploadCompleted(); // true/false
$metadata->isUploadFailed(); // true/false

// تغییر وضعیت
$metadata->markAsCompleted();
$metadata->markAsFailed('Error message');

// به‌روزرسانی زمان دسترسی
$metadata->touchLastAccessed();

// دریافت حجم قابل خواندن
$size = $metadata->getFormattedFileSize(); // "5.25 MB"

// کار با metadata JSON
$width = $metadata->getMetadataValue('width', 1920);
$metadata->setMetadataValue('processed', true);
```

---

## 📦 نحوه اجرای Migration:

```bash
# اجرای migration
php artisan migrate

# یا با Laravel Sail:
./vendor/bin/sail artisan migrate

# برگشت به عقب (rollback):
php artisan migrate:rollback

# Refresh (drop all + migrate):
php artisan migrate:refresh
```

---

## 🧪 تست Migration:

### در Tinker:
```bash
php artisan tinker
```

```php
// ایجاد یک فایل تستی
$file = \App\Models\FileEntry::first();

// ایجاد metadata برای آن
$metadata = \App\Models\TelegramFileMetadata::create([
    'file_entry_id' => $file->id,
    'telegram_file_id' => 'test_file_id_123',
    'message_id' => 99999,
    'channel_id' => '-1001234567890',
    'upload_method' => 'bot',
    'original_file_size' => 5242880,
    'telegram_file_type' => 'document',
    'upload_status' => 'completed',
    'uploaded_at' => now(),
]);

// بررسی relationship
$file->telegramMetadata; // باید metadata را برگرداند

// تست helper
\Common\Files\Telegram\TelegramMetadataHelper::getUploadStatistics();
```

---

## 🔒 نکات امنیتی:

1. **Foreign Key Cascade**: حذف FileEntry به صورت خودکار metadata را هم حذف می‌کند
2. **Soft Delete**: metadata حذف شده قابل بازیابی است
3. **Unique Constraint**: هر FileEntry فقط یک metadata دارد
4. **Index Performance**: تمام فیلدهای پرجستجو index شده‌اند

---

## ✅ چک‌لیست Phase 2:

- [x] ایجاد migration جدول telegram_file_metadata
- [x] ایجاد Model TelegramFileMetadata
- [x] افزودن relationship به FileEntry
- [x] ایجاد Helper class برای مدیریت metadata
- [x] ایجاد Seeder برای تست
- [x] مستندسازی کامل

---

## ⏭️ آماده برای Phase 3:

Phase 3 شامل موارد زیر است:
- پیاده‌سازی TelegramBotClient (Bot API)
- پیاده‌سازی TelegramUserClient (MTProto)
- پیاده‌سازی TelegramFileManager (مدیریت انتخاب روش)

**برای اجرای migration و ادامه به Phase 3 آماده‌اید؟**
