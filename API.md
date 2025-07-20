# مستندات API - BeDrive Enhanced

این مستندات، راهنمای کاملی برای استفاده از API پلتفرم BeDrive Enhanced است.

## احراز هویت

تمامی درخواست‌ها به API باید با یک توکن معتبر احراز هویت شوند. توکن را در هدر `Authorization` به صورت `Bearer YOUR_API_TOKEN` ارسال کنید.

### تولید توکن API

- **Endpoint**: `POST /api/v1/user/generate-api-token`
- **توضیحات**: یک توکن API جدید برای کاربر احراز هویت شده ایجاد می‌کند.

### ابطال توکن API

- **Endpoint**: `DELETE /api/v1/user/revoke-api-token`
- **توضیحات**: توکن API کاربر را باطل می‌کند.

## مدیریت فایل‌ها

### آپلود فایل

- **Endpoint**: `POST /api/v1/files/upload`
- **نوع محتوا**: `multipart/form-data`
- **پارامترها**:
    - `file`: فایل مورد نظر برای آپلود (required)
    - `parent_id`: شناسه پوشه والد (optional)
    - `send_to_telegram`: `true` یا `false` (optional)
    - `telegram_chat_id`: شناسه چت تلگرام (optional)

### آپلود از URL

- **Endpoint**: `POST /api/v1/files/upload-from-url`
- **نوع محتوا**: `application/json`
- **پارامترها**:
    - `url`: آدرس اینترنتی فایل (required)
    - `filename`: نام فایل (optional)
    - `parent_id`: شناسه پوشه والد (optional)

### لیست فایل‌ها

- **Endpoint**: `GET /api/v1/files`
- **پارامترها**:
    - `parent_id`: شناسه پوشه برای فیلتر کردن (optional)
    - `per_page`: تعداد نتایج در هر صفحه (default: 20)
    - `page`: شماره صفحه (default: 1)

### دانلود فایل

- **Endpoint**: `GET /api/v1/files/{id}/download`
- **توضیحات**: فایل با شناسه مشخص شده را دانلود می‌کند.

### حذف فایل

- **Endpoint**: `DELETE /api/v1/files/{id}`
- **توضیحات**: فایل با شناسه مشخص شده را حذف می‌کند.

## مدیریت تنظیمات تلگرام کاربر

### دریافت تنظیمات

- **Endpoint**: `GET /api/v1/user/telegram-settings`
- **توضیحات**: تنظیمات تلگرام کاربر را برمی‌گرداند.

### به‌روزرسانی تنظیمات

- **Endpoint**: `PUT /api/v1/user/telegram-settings`
- **نوع محتوا**: `application/json`
- **پارامترها**:
    - `telegram_chat_id`: شناسه چت تلگرام
    - `auto_send_to_telegram`: `true` یا `false`

## لینک‌های کوتاه

### ایجاد لینک کوتاه

- **Endpoint**: `POST /api/v1/short-links`
- **نوع محتوا**: `application/json`
- **پارامترها**:
    - `file_id`: شناسه فایل (required)
    - `password`: رمز عبور (optional)
    - `expires_at`: تاریخ انقضا (ISO 8601 format, optional)
    - `max_downloads`: حداکثر تعداد دانلود (optional)

### لیست لینک‌های کوتاه

- **Endpoint**: `GET /api/v1/short-links`

### به‌روزرسانی لینک کوتاه

- **Endpoint**: `PUT /api/v1/short-links/{id}`

### حذف لینک کوتاه

- **Endpoint**: `DELETE /api/v1/short-links/{id}`
