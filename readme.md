# BeDrive Enhanced - Complete Telegram Integration

BeDrive Enhanced is a powerful and flexible file storage and sharing platform, built on Laravel and React, with a deep integration with Telegram. It provides a robust and scalable solution for managing your files, with a focus on security, performance, and ease of use.

## 🚀 Features

### ✅ Core Features

*   **File Storage & Management**: A modern and intuitive interface for uploading, organizing, and managing your files and folders.
*   **User Management**: A complete user management system with roles and permissions, allowing you to control who has access to what.
*   **Responsive Design**: A fully responsive design that works on all devices, from desktops to mobile phones.
*   **Extensible**: A modular architecture that allows you to easily extend the application with new features and functionality.

### 📱 Telegram Integration

*   **Complete Storage Driver**: Use your Telegram account as a secure and reliable storage backend for your files.
*   **Automatic Uploads**: Automatically upload files to your Telegram channels or chats as soon as they are uploaded to the application.
*   **Session Management**: Manage multiple Telegram sessions, allowing you to use different Telegram accounts for different purposes.
*   **Easy Configuration**: A simple and intuitive interface for configuring your Telegram API credentials and settings.
*   **Bulk Uploads**: Upload multiple files to Telegram at once, saving you time and effort.

### 🔗 URL Upload

*   **Direct URL Upload**: Upload files directly from any URL, without having to download them to your computer first.
*   **Multi-Service Support**: Upload files from popular services like Google Drive, Dropbox, and OneDrive.
*   **Bulk URL Upload**: Process multiple URLs at once, making it easy to import large collections of files.
*   **Progress Tracking**: Real-time progress tracking for your URL uploads, so you always know the status of your uploads.

### 🔐 Advanced Sharing

*   **Short Links**: Generate short and memorable links for your files, making them easy to share.
*   **Password Protection**: Protect your shared links with a password, so only authorized users can access them.
*   **Expiration Dates**: Set an expiration date for your shared links, so they automatically become invalid after a certain period of time.
*   **Download Limits**: Limit the number of times a file can be downloaded from a shared link.
*   **Access Statistics**: Track who has accessed your shared links and when, so you can monitor the usage of your files.

### 🌐 API System

*   **RESTful API**: A complete and well-documented RESTful API that allows you to integrate the application with your own scripts and applications.
*   **Token Authentication**: Secure your API access with token-based authentication, so only authorized users can access the API.
*   **Rate Limiting**: Prevent abuse of the API with rate limiting, so you can control how often the API is accessed.
*   **Comprehensive Documentation**: A full API documentation that explains how to use the API and all its endpoints.

## 📋 Requirements

*   **PHP**: 8.1 or higher
*   **Database**: MySQL 5.7+ or PostgreSQL
*   **Framework**: Laravel 10.x
*   **Frontend**: Node.js & NPM
*   **Telegram**: Telegram API credentials (for Telegram integration)
*   **Web Server**: Apache or Nginx

## 🛠️ Installation

1.  **Clone the Repository**

    ```bash
    git clone https://github.com/mohammadham/bedrive_telegram.git
    cd bedrive-enhanced
    ```

2.  **Install Dependencies**

    ```bash
    composer install
    npm install && npm run build
    ```

3.  **Environment Configuration**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

4.  **Database Setup**

    ```bash
    php artisan migrate
    php artisan db:seed
    ```

5.  **Telegram Configuration**

    1.  Get your API credentials from [my.telegram.org](https://my.telegram.org/apps).
    2.  Configure the credentials in the admin panel: **Settings > Uploading**.
    3.  Set up your Telegram sessions for automatic uploads.

6.  **Storage Configuration**

    ```bash
    php artisan storage:link
    ```

## 🔧 Configuration

### Telegram Setup

1.  **Get API Credentials**:
    *   Visit [my.telegram.org/apps](https://my.telegram.org/apps).
    *   Create a new application.
    *   Note down your `api_id` and `api_hash`.

2.  **Configure in Admin Panel**:
    *   Go to **Admin Panel > Settings > Uploading**.
    *   Enter your API credentials in the **Telegram** section.
    *   Click the **Test Connection** button to verify your credentials.

3.  **Set Up Sessions**:
    *   Create new Telegram sessions to enable automatic uploads.
    *   Verify your phone numbers for each session.
    *   Configure the default upload channels for each session.

### URL Upload Configuration

*   Configure the supported URL patterns in the settings.
*   Set up download limits and timeouts to prevent abuse.
*   Configure the number of retry attempts for failed downloads.

### Short Links Configuration

*   Set the default expiration time for short links.
*   Configure the password requirements for protected links.
*   Set the default download limits for shared files.

## 📡 API Usage

### Authentication

To authenticate with the API, you need to obtain an API token by sending a `POST` request to the `/api/v1/auth/login` endpoint with your email and password.

### Uploading Files

*   **From a file:** Send a `POST` request to `/api/v1/files/upload` with the file as a `multipart/form-data` request.
*   **From a URL:** Send a `POST` request to `/api/v1/url-upload` with the URL of the file.

### Telegram Operations

*   **Check Status:** Send a `GET` request to `/api/v1/telegram/status` to check the status of the Telegram integration.
*   **Upload to Telegram:** Send a `POST` request to `/api/v1/telegram/upload` with the `file_id` and `chat_id` to upload a file to Telegram.

### Short Links

*   **Create a Short Link:** Send a `POST` request to `/api/v1/short-links/create` with the `file_id` and other options to create a short link.
*   **Access a Short Link:** Send a `GET` request to `/s/{short_code}` to access a short link.

## 🧪 Testing

### Running Tests

*   **Run all tests:** `php artisan test`
*   **Run a specific test:** `php artisan test tests/Feature/TelegramIntegrationTest.php`
*   **Run with coverage:** `php artisan test --coverage`

### Manual Testing

1.  **Telegram Integration**:
    *   Test the API connection from the admin panel.
    *   Upload test files to Telegram.
    *   Verify the session management functionality.

2.  **URL Upload**:
    *   Test various URL patterns to ensure they are all supported.
    *   Test bulk uploads to ensure they are processed correctly.
    *   Verify the integrity of the uploaded files.

3.  **Short Links**:
    *   Create test links with different options.
    *   Test the password protection functionality.
    *   Verify the expiration dates and download limits.

## 📊 Monitoring

### Logs

*   **Application Logs:** `storage/logs/laravel.log`
*   **Telegram Logs:** `storage/logs/telegram.log`
*   **API Logs:** `storage/logs/api.log`

### Performance

*   Monitor the upload speeds to ensure they are within acceptable limits.
*   Track the API response times to identify any performance bottlenecks.
*   Monitor the storage usage to ensure you have enough space for your files.

## 🔐 Security

### API Security

*   **Rate Limiting**: The API is protected by rate limiting to prevent abuse.
*   **Token-Based Authentication**: All API requests must be authenticated with a valid API token.
*   **Input Validation**: All input is validated to prevent common security vulnerabilities.
*   **SQL Injection Prevention**: The application uses prepared statements to prevent SQL injection attacks.

### File Security

*   **File Type Validation**: The application validates the file types to prevent users from uploading malicious files.
*   **Virus Scanning (Optional)**: You can integrate a virus scanner to scan all uploaded files for viruses.
*   **Access Control**: The application has a robust access control system that allows you to control who has access to what.
*   **Secure File Storage**: All files are stored securely on the server or in the cloud.

### Telegram Security

*   **Encrypted Session Storage**: All Telegram sessions are stored securely and encrypted.
*   **Secure API Communication**: All communication with the Telegram API is done over a secure connection.
*   **Access Token Rotation**: You can rotate your API tokens regularly to improve security.

## 🚀 Deployment

### Docker Deployment

You can use the provided `docker-compose.yml` file to easily deploy the application with Docker.

```bash
docker-compose up -d
```

### Production Setup

1.  Configure your production environment variables.
2.  Set up SSL certificates to secure your application.
3.  Configure a CDN to improve the performance of your application.
4.  Set up monitoring to track the health of your application.
5.  Configure backups to protect your data.

### Environment Variables

```bash
# Telegram
TELEGRAM_API_ID=your_api_id
TELEGRAM_API_HASH=your_api_hash
TELEGRAM_SESSION_PATH=/app/storage/telegram_sessions

# Storage
STORAGE_DRIVER=telegram
TELEGRAM_STORAGE_ENABLED=true

# API
API_RATE_LIMIT=60
API_TOKEN_EXPIRY=3600
```

## 📚 Documentation

### API Documentation

The full API documentation is available at `/api/docs`.

### User Guide

The user guide is available in the admin panel.

## 🤝 Contributing

1.  Fork the repository.
2.  Create a feature branch.
3.  Make your changes.
4.  Add tests for your changes.
5.  Submit a pull request.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

*   **Documentation**: [Wiki](https://github.com/mohammadham/bedrive_telegram/wiki)
*   **Issues**: [GitHub Issues](https://github.com/mohammadham/bedrive_telegram/issues)
*   **Discussions**: [GitHub Discussions](https://github.com/mohammadham/bedrive_telegram/discussions)

---

**Built with ❤️ by the BeDrive Team**
