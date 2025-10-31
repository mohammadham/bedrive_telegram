import {BaseBackendSettings} from '@common/core/settings/base-backend-settings';

export interface Settings extends Omit<BaseBackendSettings, 'html_base_uri'> {
  base_url: string;
  asset_url?: string;
  locale?: {
    default?: string;
  };
  dates: {
    format: string;
    default_timezone: string;
  };
}
