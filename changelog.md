# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-07-22

### Added

*   **Telegram Integration:**
    *   Added a complete Telegram storage driver, allowing users to use their Telegram account as a storage backend.
    *   Implemented automatic uploads to Telegram channels or chats.
    *   Added a session management system for managing multiple Telegram accounts.
    *   Created an admin panel for configuring Telegram API credentials and settings.
    *   Added a "Test Connection" button to the admin panel to verify the Telegram API credentials.
    *   Added support for bulk uploads to Telegram.
*   **URL Upload:**
    *   Added the ability to upload files directly from a URL.
    *   Added support for uploading files from popular services like Google Drive, Dropbox, and OneDrive.
    *   Added support for bulk URL uploads.
    *   Added real-time progress tracking for URL uploads.
*   **Advanced Sharing:**
    *   Added the ability to generate short and memorable links for files.
    *   Added password protection for shared links.
    *   Added the ability to set an expiration date for shared links.
    *   Added the ability to limit the number of times a file can be downloaded from a shared link.
    *   Added access statistics for shared links.
*   **API System:**
    *   Added a complete and well-documented RESTful API for all operations.
    *   Added token-based authentication for securing API access.
    *   Added rate limiting to prevent abuse of the API.
    *   Added a full API documentation that explains how to use the API and all its endpoints.

### Changed

*   Updated the project to use Laravel 10.x.
*   Updated the frontend to use the latest version of React.
*   Improved the performance of the application by optimizing the database queries and caching frequently accessed data.
*   Improved the user interface to be more modern and intuitive.

### Fixed

*   Fixed a bug that caused the application to crash when uploading large files.
*   Fixed a security vulnerability that allowed users to bypass the file permissions.

## [1.0.0] - 2024-01-01

### Added

*   Basic file storage and management functionality.
*   User management system with roles and permissions.
*   Folder organization and management.
*   Basic file sharing functionality.
