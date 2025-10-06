export interface Settings {
  base_url: string;
  asset_url?: string;
  locale?: {
    default?: string;
  };
  dates: {
    format: string;
    default_timezone: string;
  };
  server?: {
    uploads_disk_driver?: string;
    public_disk_driver?: string;
    [key: string]: any;
  };
}
