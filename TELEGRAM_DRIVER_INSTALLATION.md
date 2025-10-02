# راهنمای نصب درایور تلگرام (Telegram Driver Installation)

## Phase 1: نصب Dependencies

### کتابخانه‌های مورد نیاز:

1. **irazasyed/telegram-bot-sdk** (v3.14+)
   - برای Bot API تلگرام
   - مدیریت فایل‌های کمتر از 50MB
   - سازگار با Laravel

2. **danog/madelineproto** (v8.0+)
   - برای MTProto User Account
   - مدیریت فایل‌های تا 2GB
   - پشتیبانی از فایل‌های بزرگ

### دستورات نصب:

```bash
# اگر از Laravel Sail استفاده می‌کنید:
./vendor/bin/sail composer require irazasyed/telegram-bot-sdk
./vendor/bin/sail composer require danog/madelineproto

# یا اگر Composer به صورت مستقیم نصب است:
composer require irazasyed/telegram-bot-sdk
composer require danog/madelineproto
```

### تنظیمات محیطی (.env):

فایل `.env` خود را با اطلاعات زیر به‌روزرسانی کنید:

```env
# تنظیمات Bot API (برای فایل‌های < 50MB)
TELEGRAM_BOT_TOKEN=your_bot_token_here

# شناسه کانال (با @ یا عدد)
TELEGRAM_CHANNEL_ID=@your_channel_or_numeric_id

# تنظیمات User Account (برای فایل‌های > 50MB)
TELEGRAM_API_ID=your_api_id
TELEGRAM_API_HASH=your_api_hash
TELEGRAM_PHONE=+989123456789

# مسیر ذخیره session (اختیاری)
TELEGRAM_SESSION_FILE=storage/app/telegram/session.madeline
```

### نحوه دریافت اطلاعات:

#### 1. Bot Token:
- به [@BotFather](https://t.me/BotFather) در تلگرام پیام دهید
- دستور `/newbot` را ارسال کنید
- نام و username برای ربات انتخاب کنید
- Bot Token را دریافت کنید

#### 2. Channel ID:
- کانال خصوصی خود را بسازید
- ربات را به عنوان Admin اضافه کنید
- برای دریافت Channel ID از [@username_to_id_bot](https://t.me/username_to_id_bot) استفاده کنید

#### 3. API ID و API Hash:
- به [https://my.telegram.org](https://my.telegram.org) بروید
- وارد حساب خود شوید
- به API Development Tools بروید
- اپلیکیشن جدید ایجاد کنید
- API ID و API Hash را کپی کنید

### ساختار پوشه‌ها:

```bash
# ایجاد پوشه برای session files
mkdir -p storage/app/telegram
chmod 775 storage/app/telegram
```

### تست اتصال:

پس از نصب، می‌توانید اتصال را تست کنید:

```php
use Common\Files\Telegram\TelegramConnectionTest;

// تست کامل
$config = [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'channel_id' => env('TELEGRAM_CHANNEL_ID'),
    'api_id' => env('TELEGRAM_API_ID'),
    'api_hash' => env('TELEGRAM_API_HASH'),
    'phone' => env('TELEGRAM_PHONE'),
];

$results = TelegramConnectionTest::fullTest($config);
print_r($results);
```

## وضعیت نصب:

- ✅ composer.json به‌روزرسانی شد
- ✅ config/services.php تنظیمات تلگرام اضافه شد
- ✅ env.example متغیرهای محیطی اضافه شد
- ✅ TelegramConnectionTest.php ساخته شد
- ⏳ نصب packages با composer (نیاز به اجرای دستور composer)

## مراحل بعدی:

پس از نصب موفق packages:
1. فایل .env خود را تنظیم کنید
2. تست اتصال را اجرا کنید
3. به Phase 2 بروید (ایجاد Database Schema)

## نکات مهم:

1. **امنیت**: هیچ‌گاه اطلاعات Bot Token و API Hash را commit نکنید
2. **Session File**: فایل session.madeline حاوی اطلاعات لاگین است - آن را backup کنید
3. **مجوزها**: اطمینان حاصل کنید ربات Admin کانال است
4. **حجم فایل**: 
   - Bot API: حداکثر 50MB
   - User Account: حداکثر 2GB
5. **Rate Limiting**: از محدودیت‌های API تلگرام آگاه باشید

## پشتیبانی:

در صورت بروز مشکل:
- لاگ‌های `storage/logs/laravel.log` را بررسی کنید
- مستندات کتابخانه‌ها:
  - [Telegram Bot SDK](https://telegram-bot-sdk.com/docs/)
  - [MadelineProto](https://docs.madelineproto.xyz/)
