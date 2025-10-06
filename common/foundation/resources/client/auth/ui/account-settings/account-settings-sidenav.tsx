import {List, ListItem} from '@ui/list/list';
import {PersonIcon} from '@ui/icons/material/Person';
import {Trans} from '@ui/i18n/trans';
import {LoginIcon} from '@ui/icons/material/Login';
import {LockIcon} from '@ui/icons/material/Lock';
import {PhonelinkLockIcon} from '@ui/icons/material/PhonelinkLock';
import {LanguageIcon} from '@ui/icons/material/Language';
import {ApiIcon} from '@ui/icons/material/Api';
import {DangerousIcon} from '@ui/icons/material/Dangerous';
import {ReactNode, useContext, useMemo} from 'react';
import {DevicesIcon} from '@ui/icons/material/Devices';
import {useAuth} from '@common/auth/use-auth';
import {useSettings} from '@ui/settings/use-settings';
import {SiteConfigContext} from '@common/core/settings/site-config-context';
import {useAllSocialLoginsDisabled} from '@common/auth/ui/use-all-social-logins-disabled';
import {TelegramIcon} from '@ui/icons/social/telegram';
import {useQuery} from '@tanstack/react-query';
import {apiClient} from '@common/http/query-client';

export enum AccountSettingsId {
  AccountDetails = 'account-details',
  SocialLogin = 'social-login',
  Password = 'password',
  TwoFactor = 'two-factor',
  LocationAndLanguage = 'location-and-language',
  TelegramSettings = 'telegram-settings',
  Developers = 'developers',
  DeleteAccount = 'delete-account',
  Sessions = 'sessions',
}

// Fetch real-time telegram driver status
async function fetchTelegramDriverStatus(): Promise<{driver_enabled: boolean}> {
  return apiClient.get('user/telegram/settings').then(r => r.data);
}

export function AccountSettingsSidenav() {
  const p = AccountSettingsId;

  const {hasPermission} = useAuth();
  const {api} = useSettings();
  const {auth} = useContext(SiteConfigContext);
  const allSocialsDisabled = useAllSocialLoginsDisabled();

  // Query telegram driver status in real-time
  const {data: telegramStatus} = useQuery({
    queryKey: ['telegram-driver-status'],
    queryFn: fetchTelegramDriverStatus,
    refetchInterval: 5000, // Re-check every 5 seconds
    retry: false,
    staleTime: 0, // Always consider data stale
  });

  const isTelegramDriver = useMemo(() => {
    return telegramStatus?.driver_enabled ?? false;
  }, [telegramStatus]);

  return (
    <aside className="sticky top-10 hidden flex-shrink-0 lg:block">
      <List padding="p-0">
        {auth.accountSettingsPanels?.map(panel => (
          <Item
            key={panel.id}
            icon={<panel.icon viewBox="0 0 50 50" />}
            panel={panel.id as AccountSettingsId}
          >
            <Trans {...panel.label} />
          </Item>
        ))}
        <Item icon={<PersonIcon />} panel={p.AccountDetails}>
          <Trans message="Account details" />
        </Item>
        {!allSocialsDisabled && (
          <Item icon={<LoginIcon />} panel={p.SocialLogin}>
            <Trans message="Social login" />
          </Item>
        )}
        <Item icon={<LockIcon />} panel={p.Password}>
          <Trans message="Password" />
        </Item>
        <Item icon={<PhonelinkLockIcon />} panel={p.TwoFactor}>
          <Trans message="Two factor authentication" />
        </Item>
        <Item icon={<DevicesIcon />} panel={p.Sessions}>
          <Trans message="Active sessions" />
        </Item>
        <Item icon={<LanguageIcon />} panel={p.LocationAndLanguage}>
          <Trans message="Location and language" />
        </Item>
        {isTelegramDriver && (
          <Item icon={<TelegramIcon />} panel={p.TelegramSettings}>
            <Trans message="Telegram" />
          </Item>
        )}
        {api?.integrated && hasPermission('api.access') ? (
          <Item icon={<ApiIcon />} panel={p.Developers}>
            <Trans message="Developers" />
          </Item>
        ) : null}
        <Item icon={<DangerousIcon />} panel={p.DeleteAccount}>
          <Trans message="Delete account" />
        </Item>
      </List>
    </aside>
  );
}

interface ItemProps {
  children: ReactNode;
  icon: ReactNode;
  isLast?: boolean;
  panel: AccountSettingsId;
}
function Item({children, icon, isLast, panel}: ItemProps) {
  return (
    <ListItem
      startIcon={icon}
      className={isLast ? undefined : 'mb-10'}
      onSelected={() => {
        const panelEl = document.querySelector(`#${panel}`);
        if (panelEl) {
          panelEl.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
          });
        }
      }}
    >
      {children}
    </ListItem>
  );
}