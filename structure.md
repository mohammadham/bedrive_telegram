# Project Structure

This document provides a detailed overview of the project's file structure, with a focus on the `app`, `common`, and `routes` directories.

## System Diagram

```
+---------------------+      +-------------------------+      +-----------------------+
|   Frontend (React)  |----->|   Backend (Laravel)   |----->|  telegram-upload (py) |-----> Telegram API
+---------------------+      +-------------------------+      +-----------------------+
        |                                |
        |                                |
        v                                v
+---------------------+      +-------------------------+
| User Settings Panel |      | Admin Settings Panel    |
+---------------------+      +-------------------------+
```

## File Structure

### `app` Directory

The `app` directory contains the core code of the application, including controllers, models, and providers.

*   **`Http/Controllers`**: Contains the controllers that handle HTTP requests.
    *   `UrlUploadController.php`: This controller handles file uploads from a given URL. It can also send the uploaded file to Telegram if requested.
*   **`Http/Controllers/Api`**: Contains the API controllers.
    *   `FileApiController.php`: This controller provides an API for managing files, including uploading, downloading, and deleting files. It also supports sending files to Telegram.
    *   `TelegramController.php`: This controller handles all API requests related to Telegram, such as getting the status, configuring the session, and testing the upload.
    *   `UserApiController.php`: This controller provides an API for managing user-specific settings, including API tokens and Telegram settings.
*   **`Models`**: Contains the Eloquent models.
    *   `UserTelegramSettings.php`: This is the Eloquent model for the `user_telegram_settings` table. It defines the relationship between a user and their Telegram settings.
*   **`Providers`**: Contains the service providers.
    *   `TelegramStorageServiceProvider.php`: This service provider is intended to register the `telegram` storage disk. However, the code in the `boot` method is commented out, and the registration is actually handled by the `DynamicStorageDiskProvider`.
*   **`Services/Storage`**: Contains the storage-related services.
    *   `TelegramStorageDriver.php`: This is the core class that interacts with the `telegram-upload` python script. It handles the actual uploading, downloading, and other file operations.

### `common` Directory

The `common` directory contains code that is shared across different parts of the application.

*   **`foundation/resources/client/admin/settings`**: Contains the React components for the admin settings page.
    *   `admin-settings.ts`: This file defines the TypeScript interfaces for the admin settings, including the Telegram settings.
    *   `pages/uploading-settings/uploading-settings.tsx`: This file contains the React component for the admin uploading settings page. It includes the `TelegramForm` component, which has the "Test Connection" button.
*   **`foundation/resources/client/auth/ui/account-settings`**: Contains the React components for the user's account settings page.
    *   `account-settings-page.tsx`: This file contains the main React component for the user's account settings page. It renders the `AccountSettingsSidenav` and the different settings panels.
    *   `account-settings-sidenav.tsx`: This file contains the React component for the sidenav on the account settings page. It lists the different settings panels that the user can navigate to.
*   **`foundation/resources/client/ui/library/icons/social`**: Contains the social media icons.
    *   `telegram.tsx`: This file contains the SVG icon for Telegram.
*   **`foundation/src/Files/Providers`**: Contains the file-related service providers.
    *   `DynamicStorageDiskProvider.php`: This service provider is responsible for dynamically creating storage disks based on the configuration. It includes the logic to create the `telegram` disk.

### `routes` Directory

The `routes` directory contains all the route definitions for the application.

*   `api.php`: This file defines all the API routes for the application, including the routes for the `TelegramController` and `UserApiController`.

---

## Telegram Integration Details

### Backend (Laravel)

*   **`app/Http/Controllers/Api/TelegramController.php`**: This controller is the main entry point for all API requests related to Telegram. It handles tasks like checking the status of the Telegram integration, installing the `telegram-upload` script, configuring the Telegram session, and testing the upload functionality.
*   **`app/Http/Controllers/Api/UserApiController.php`**: This controller is responsible for managing user-specific settings, including their Telegram settings. It provides endpoints for getting and updating the user's Telegram chat ID and other settings.
*   **`app/Services/Storage/TelegramStorageDriver.php`**: This is the core of the Telegram integration. It's a custom storage driver that uses the `telegram-upload` Python script to interact with the Telegram API. It handles all the low-level tasks like uploading, downloading, and deleting files.
*   **`app/Services/Storage/TelegramFilesystemAdapter.php`**: This class is a Flysystem adapter that makes the `TelegramStorageDriver` compatible with Laravel's filesystem. It allows the application to use the Telegram storage driver just like any other filesystem disk.
*   **`common/foundation/src/Files/Providers/DynamicStorageDiskProvider.php`**: This service provider is responsible for dynamically creating the `telegram` storage disk based on the configuration in the `config/filesystems.php` file.
*   **`config/filesystems.php`**: This file contains the configuration for all the filesystem disks in the application. It now includes the configuration for the `telegram` disk, which tells the application how to connect to the Telegram API.
*   **`routes/api.php`**: This file defines all the API routes for the application, including the routes for the `TelegramController` and `UserApiController`.
*   **`app/Models/UserTelegramSettings.php`**: This is the Eloquent model for the `user_telegram_settings` table. It defines the relationship between a user and their Telegram settings, and it's used to store the user's Telegram chat ID and other settings.

### Frontend (React)

*   **`common/foundation/resources/client/admin/settings/pages/uploading-settings/uploading-settings.tsx`**: This file contains the React component for the admin uploading settings page. It includes the `TelegramForm` component, which allows the administrator to configure the Telegram API credentials and other settings. It also has the "Test Connection" button, which allows the administrator to test the Telegram connection.
*   **`resources/client/drive/share-dialog/share-dialog.tsx`**: This dialog is where a user can share a file. If the Telegram integration is enabled, the user can choose to share the file to their Telegram account.
*   **`common/foundation/resources/client/auth/ui/account-settings/account-settings-page.tsx`**: This is the main component for the user's account settings page. It's where the user can manage their account settings, including their Telegram settings.
*   **`common/foundation/resources/client/auth/ui/account-settings/account-settings-sidenav.tsx`**: This component is the sidenav on the account settings page. It should include a link to the Telegram settings panel, but it's currently missing.
