# Phase 5: Service Provider و یکپارچه‌سازی با Laravel - مستندات کامل

## 📦 فایل‌های ایجاد شده و به‌روزرسانی شده

### 1. TelegramServiceProvider:
```
✅ /app/common/foundation/src/Files/Providers/TelegramServiceProvider.php
```
- ثبت driver در Laravel Storage
- Singleton برای TelegramFileManager
- Configuration management

### 2. فایل‌های به‌روزرسانی شده:

#### Backend Configuration:
```
✅ /app/config/filesystems.php - افزودن telegram disk
✅ /app/config/services.php - تنظیمات telegram (قبلاً اضافه شده)
✅ /app/common/foundation/src/CommonServiceProvider.php - ثبت provider
✅ /app/common/foundation/src/Settings/Validators/StorageCredentialsValidator.php - validation
```

---

## 🎯 معماری کلی

```
Laravel Application
    ↓
CommonServiceProvider
    ↓
TelegramServiceProvider (conditional registration)
    ↓
Storage::extend('telegram')
    ↓
TelegramAdapter + TelegramFileManager
    ↓
Telegram API (Bot + User)
```

---

## 🔧 تنظیمات

### 1. Environment Variables

افزودن به `.env`:

```env
# Telegram Storage Driver
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_CHANNEL_ID=-1001234567890
TELEGRAM_API_ID=12345678
TELEGRAM_API_HASH=0123456789abcdef0123456789abcdef
TELEGRAM_PHONE=+989123456789
```

**نکته**: Phone و User credentials اختیاری هستند (فقط برای فایل‌های بزرگتر از 50MB)

---

### 2. Disk Configuration

`config/filesystems.php` به صورت خودکار configure شده است:

```php
'disks' => [
    // ...
    
    'telegram' => [
        'driver' => 'telegram',
        'channel_id' => env('TELEGRAM_CHANNEL_ID'),
        'prefix' => env('TELEGRAM_PREFIX', ''),
    ],
],
```

---

### 3. Service Configuration

`config/services.php`:

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

## 🚀 استفاده

### 1. استفاده از Laravel Storage Facade

```php
use Illuminate\Support\Facades\Storage;

// آپلود فایل
Storage::disk('telegram')->put('documents/report.pdf', $contents);

// دانلود فایل
$contents = Storage::disk('telegram')->get('documents/report.pdf');

// حذف فایل
Storage::disk('telegram')->delete('documents/report.pdf');

// چک وجود
if (Storage::disk('telegram')->exists('documents/report.pdf')) {
    // فایل وجود دارد
}

// لیست فایل‌ها
$files = Storage::disk('telegram')->files('documents');
$allFiles = Storage::disk('telegram')->allFiles('documents');

// اطلاعات فایل
$size = Storage::disk('telegram')->size('documents/report.pdf');
$mimeType = Storage::disk('telegram')->mimeType('documents/report.pdf');
$lastModified = Storage::disk('telegram')->lastModified('documents/report.pdf');
```

---

### 2. تنظیم به عنوان Default Driver

می‌توانید telegram را به عنوان driver پیش‌فرض تنظیم کنید:

```env
UPLOADS_DISK_DRIVER=telegram
# یا
PUBLIC_DISK_DRIVER=telegram
```

سپس در کد:

```php
// استفاده از default disk
Storage::put('file.pdf', $contents); // به telegram می‌رود

// یا explicit
Storage::disk('uploads')->put('file.pdf', $contents);
```

---

### 3. استفاده در File Upload

```php
use Illuminate\Http\Request;

public function upload(Request $request)
{
    $request->validate([
        'file' => 'required|file|max:2097152', // 2GB
    ]);

    // آپلود به telegram
    $path = $request->file('file')->store('uploads', 'telegram');
    
    return response()->json([
        'success' => true,
        'path' => $path,
    ]);
}
```

---

### 4. استفاده با FileEntry (BeDrive)

```php
use App\Models\FileEntry;
use Common\Files\Telegram\TelegramStorageService;

$service = new TelegramStorageService();

// آپلود با ایجاد FileEntry
$result = $service->uploadFile('/path/to/file.pdf', [
    'name' => 'Important Document',
    'user_id' => auth()->id(),
    'owner_id' => auth()->id(),
]);

$fileEntry = $result['file_entry'];
$metadata = $result['metadata'];

// بعداً دانلود
$contents = $service->getFileContents($fileEntry);
```

---

## ✅ Validation

### چک اتصال تلگرام:

Validator به صورت خودکار اتصال را چک می‌کند وقتی telegram به عنوان driver انتخاب شود.

**از Admin Panel**:
1. Settings → Storage
2. انتخاب "Telegram" به عنوان driver
3. وارد کردن Bot Token و Channel ID
4. Save Settings
5. Validator به صورت خودکار اتصال را تست می‌کند

**از CLI**:

```php
use Common\Settings\Validators\StorageCredentialsValidator;

$validator = new StorageCredentialsValidator();
$errors = $validator->fails([
    'uploads_disk_driver' => 'telegram',
    'storage_telegram_bot_token' => 'YOUR_TOKEN',
    'storage_telegram_channel_id' => 'YOUR_CHANNEL_ID',
]);

if ($errors) {
    dd($errors); // خطاهای validation
}
```

---

## 🧪 تست

### 1. تست اتصال ساده:

```php
use Illuminate\Support\Facades\Storage;

try {
    // تست نوشتن
    Storage::disk('telegram')->put('test.txt', 'Hello Telegram!');
    
    // تست خواندن
    $contents = Storage::disk('telegram')->get('test.txt');
    
    echo "Contents: {$contents}"; // "Hello Telegram!"
    
    // پاک کردن
    Storage::disk('telegram')->delete('test.txt');
    
    echo "✅ Telegram storage is working!";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
```

---

### 2. تست با فایل واقعی:

```bash
php artisan tinker
```

```php
$disk = Storage::disk('telegram');

// آپلود
$testFile = storage_path('app/test.jpg');
$contents = file_get_contents($testFile);
$disk->put('images/test.jpg', $contents);

// دانلود
$downloaded = $disk->get('images/test.jpg');
file_put_contents('/tmp/downloaded.jpg', $downloaded);

// لیست
$files = $disk->files('images');
print_r($files);

// حذف
$disk->delete('images/test.jpg');
```

---

### 3. تست Manager:

```php
use Common\Files\Telegram\TelegramFileManager;

$manager = app(TelegramFileManager::class);

// تست اتصال
$results = $manager->testConnections();
dd($results);

// خروجی:
// [
//     'bot' => [
//         'success' => true,
//         'info' => [...bot info...],
//     ],
//     'user' => [
//         'success' => true,
//         'info' => [...user info...],
//     ]
// ]
```

---

## 🔐 امنیت

### 1. Environment Variables

**هرگز** اطلاعات حساس را در کد hardcode نکنید:

```php
// ❌ Bad
$botToken = '123456:ABC-DEF...';

// ✅ Good
$botToken = config('services.telegram.bot_token');
```

---

### 2. Channel Privacy

کانال تلگرام باید **خصوصی** باشد:

1. ایجاد کانال خصوصی در تلگرام
2. ربات را به عنوان admin اضافه کنید
3. دادن دسترسی "Post Messages" و "Delete Messages"

---

### 3. File Access Control

استفاده از routes برای کنترل دسترسی:

```php
// در routes/web.php
Route::get('/files/{file}/download', function ($fileId) {
    $file = FileEntry::findOrFail($fileId);
    
    // چک دسترسی
    if ($file->user_id !== auth()->id()) {
        abort(403, 'Unauthorized');
    }
    
    $service = new TelegramStorageService();
    $contents = $service->getFileContents($file);
    
    return response($contents)
        ->header('Content-Type', $file->mime)
        ->header('Content-Disposition', 'attachment; filename="' . $file->name . '"');
        
})->middleware('auth')->name('file.download');
```

---

## 📊 Performance

### 1. Caching

Path mappings به مدت 1 ساعت cache می‌شوند:

```php
// Clear cache manually if needed
TelegramPathMapper::clearCache();
```

---

### 2. Large Files

- **< 50MB**: Bot API (سریع) ⚡
- **50MB - 2GB**: User Account (کندتر) 🔄
- **> 2GB**: غیرممکن ❌

برای فایل‌های بزرگ، User credentials را configure کنید.

---

### 3. Batch Operations

برای آپلود چندین فایل، از Queue استفاده کنید:

```php
use Illuminate\Support\Facades\Queue;

foreach ($files as $file) {
    Queue::push(new UploadToTelegramJob($file));
}
```

---

## 🐛 Troubleshooting

### مشکل: "Invalid bot token"

```
✅ چک کنید TELEGRAM_BOT_TOKEN در .env صحیح است
✅ از @BotFather توکن جدید بگیرید
✅ composer install را اجرا کنید
```

---

### مشکل: "Channel not found"

```
✅ Channel ID باید با - شروع شود: -1001234567890
✅ ربات باید admin کانال باشد
✅ کانال باید خصوصی باشد
```

---

### مشکل: "File too large"

```
✅ فایل‌های < 50MB: فقط Bot Token نیاز است
✅ فایل‌های 50MB-2GB: User credentials را configure کنید
✅ فایل‌های > 2GB: از storage دیگری استفاده کنید
```

---

### مشکل: "Session file not writable"

```bash
# ایجاد directory
mkdir -p storage/app/telegram

# دادن permission
chmod 755 storage/app/telegram
```

---

## 📋 Checklist

### قبل از استفاده در Production:

- [ ] ربات تلگرام ایجاد شده (@BotFather)
- [ ] کانال خصوصی ایجاد شده
- [ ] ربات به عنوان admin اضافه شده
- [ ] Environment variables تنظیم شده
- [ ] `composer install` اجرا شده
- [ ] Migration اجرا شده: `php artisan migrate`
- [ ] تست اتصال موفق
- [ ] Routes برای download اضافه شده
- [ ] Backup strategy تعیین شده
- [ ] Monitoring راه‌اندازی شده

---

## 🎉 خلاصه Phase 5

### کارهای انجام شده:

✅ **TelegramServiceProvider** ایجاد شد  
✅ ثبت در **CommonServiceProvider**  
✅ افزودن به **config/filesystems.php**  
✅ **Validation** در StorageCredentialsValidator  
✅ **Conditional registration** (فقط اگر انتخاب شده باشد)  
✅ **Singleton** برای TelegramFileManager  

---

### حالا می‌توانید:

```php
// مثل هر driver دیگر Laravel استفاده کنید
Storage::disk('telegram')->put('file.pdf', $contents);
Storage::disk('telegram')->get('file.pdf');
Storage::disk('telegram')->delete('file.pdf');
Storage::disk('telegram')->files('directory');

// یا به عنوان default
config(['filesystems.default' => 'telegram']);
Storage::put('file.pdf', $contents); // به telegram می‌رود
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

## ⏭️ آماده برای Phase 6:

**Phase 6** شامل موارد زیر است:
- یکپارچه‌سازی با Admin Panel
- افزودن UI برای تنظیمات تلگرام
- نمایش آمار و اطلاعات
- مدیریت فایل‌های تلگرام از dashboard

**Backend کامل شده است! حالا فقط UI باقی مانده** 🎨

**بفرمایید برای شروع Phase 6!**
