import {Button} from '@ui/buttons/button';
import {useState} from 'react';
import {Trans} from '@ui/i18n/trans';
import {CheckCircleIcon} from '@ui/icons/material/CheckCircle';
import {ErrorIcon} from '@ui/icons/material/Error';
import {CloudUploadIcon} from '@ui/icons/material/CloudUpload';
import {apiClient} from '@common/http/query-client';
import {toast} from '@ui/toast/toast';
import {useFormContext} from 'react-hook-form';
import {Dialog} from '@ui/overlays/dialog/dialog';
import {DialogHeader} from '@ui/overlays/dialog/dialog-header';
import {DialogBody} from '@ui/overlays/dialog/dialog-body';
import {DialogFooter} from '@ui/overlays/dialog/dialog-footer';
import {DialogTrigger} from '@ui/overlays/dialog/dialog-trigger';
import {useDialogContext} from '@ui/overlays/dialog/dialog-context';
import {FormTextField} from '@ui/forms/input-field/text-field/text-field';
import {useForm} from 'react-hook-form';
import {Form} from '@ui/forms/form';
import {LogoutIcon} from '@ui/icons/material/Logout';
import {ConfirmationDialog} from '@ui/overlays/dialog/confirmation-dialog';

interface TelegramTestResponse {
  success: boolean;
  message: string;
  data?: any;
  needs_password?: boolean;
  error?: {
    type: string;
    suggestion: string;
  };
}

export function TelegramTestButtons() {
  const {watch} = useFormContext();
  const [botTesting, setBotTesting] = useState(false);
  const [userTesting, setUserTesting] = useState(false);
  const [userLogout, setUserLogout] = useState(false);
  const [botTestResult, setBotTestResult] = useState<TelegramTestResponse | null>(null);
  const [userTestResult, setUserTestResult] = useState<TelegramTestResponse | null>(null);

  const botToken = watch('server.storage_telegram_bot_token');
  const channelId = watch('server.storage_telegram_channel_id');
  const apiId = watch('server.storage_telegram_api_id');
  const apiHash = watch('server.storage_telegram_api_hash');
  const phone = watch('server.storage_telegram_phone');

  const canTestBot = botToken && channelId;
  const canTestUser = apiId && apiHash && phone;

  const handleTestBot = async () => {
    if (!canTestBot) {
      toast.danger('Please fill Bot Token and Channel ID first');
      return;
    }

    setBotTesting(true);
    setBotTestResult(null);

    try {
      const response = await apiClient.post<TelegramTestResponse>('admin/telegram/test-bot', {
        bot_token: botToken,
        channel_id: channelId,
      });

      setBotTestResult(response.data);
      
      if (response.data.success) {
        toast.positive(response.data.message);
      } else {
        toast.danger(response.data.message);
      }
    } catch (error: any) {
      const errorData = error.response?.data;
      setBotTestResult(errorData);
      toast.danger(errorData?.message || 'Bot test failed');
    } finally {
      setBotTesting(false);
    }
  };

  const handleTestUser = async () => {
    if (!canTestUser) {
      toast.danger('Please fill API ID, API Hash, and Phone Number first');
      return;
    }

    setUserTesting(true);
    setUserTestResult(null);

    try {
      const response = await apiClient.post<TelegramTestResponse>('admin/telegram/test-user', {
        api_id: apiId,
        api_hash: apiHash,
        phone: phone,
      });

      setUserTestResult(response.data);
      
      if (response.data.success) {
        toast.positive(response.data.message);
      } else {
        toast.danger(response.data.message);
      }
    } catch (error: any) {
      const errorData = error.response?.data;
      setUserTestResult(errorData);
      toast.danger(errorData?.message || 'User test failed');
    } finally {
      setUserTesting(false);
    }
  };
const handleLogoutUser = async () => {
    if (!canTestUser) {
      toast.danger('Please fill API ID, API Hash, and Phone Number first');
      return;
    }

    setUserLogout(true);

    try {
      const response = await apiClient.post<TelegramTestResponse>('admin/telegram/logout-user', {
        api_id: apiId,
        api_hash: apiHash,
        phone: phone,
      });

      if (response.data.success) {
        toast.positive(response.data.message);
        // Clear user test result
        setUserTestResult(null);
      } else {
        toast.danger(response.data.message);
      }
    } catch (error: any) {
      const errorData = error.response?.data;
      toast.danger(errorData?.message || 'Logout failed');
    } finally {
      setUserLogout(false);
    }
  };
  return (
    <div className="space-y-20 mt-30">
      {/* Bot Test Button */}
      <div className="border border-divider rounded-panel p-20">
        <div className="flex items-start justify-between gap-16">
          <div className="flex-1">
            <h3 className="text-base font-semibold mb-4">
              <Trans message="Test Bot Connection" />
            </h3>
            <p className="text-sm text-muted mb-12">
              <Trans message="Verify that bot token is valid and bot has access to the channel." />
            </p>
            
            {botTestResult && (
              <div className={`flex items-start gap-8 p-12 rounded ${
                botTestResult.success ? 'bg-positive/10 text-positive' : 'bg-danger/10 text-danger'
              }`}>
                {botTestResult.success ? (
                  <CheckCircleIcon size="sm" />
                ) : (
                  <ErrorIcon size="sm" />
                )}
                <div className="flex-1 text-sm">
                  <p className="font-medium">{botTestResult.message}</p>
                  {botTestResult.error?.suggestion && (
                    <p className="mt-4 text-xs opacity-80">
                      {botTestResult.error.suggestion}
                    </p>
                  )}
                  {botTestResult.data?.webhook && (
                    <div className="mt-8 pt-8 border-t border-current opacity-70">
                      <p className="text-xs font-semibold mb-4">Webhook Status:</p>
                      {botTestResult.data.webhook.configured ? (
                        <div className="text-xs space-y-2">
                          <p>✓ Webhook configured</p>
                          {botTestResult.data.webhook.url && (
                            <p className="opacity-70 break-all">URL: {botTestResult.data.webhook.url}</p>
                          )}
                          {botTestResult.data.webhook.pending_updates > 0 && (
                            <p>⚠️ Pending updates: {botTestResult.data.webhook.pending_updates}</p>
                          )}
                          {botTestResult.data.webhook.just_set && (
                            <p className="text-warning">✓ Just configured automatically</p>
                          )}
                        </div>
                      ) : (
                        <p className="text-xs">⚠️ Webhook not configured</p>
                      )}
                      {botTestResult.data.webhook.error && (
                        <p className="text-xs text-danger mt-4">
                          Webhook error: {botTestResult.data.webhook.error}
                        </p>
                      )}
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
          
          <Button
            variant="outline"
            color="primary"
            size="sm"
            onClick={handleTestBot}
            disabled={!canTestBot || botTesting}
          >
            {botTesting ? (
              <Trans message="Testing..." />
            ) : (
              <Trans message="Test Bot" />
            )}
          </Button>
        </div>
      </div>

      {/* User Test & Login */}
      <div className="border border-divider rounded-panel p-20">
        <div className="flex items-start justify-between gap-16">
          <div className="flex-1">
            <h3 className="text-base font-semibold mb-4">
              <Trans message="Test User Account & Login" />
            </h3>
            <p className="text-sm text-muted mb-12">
              <Trans message="Test user account credentials and create session for large file uploads (50MB-2GB)." />
            </p>
            
            {userTestResult && (
              <div className={`flex items-start gap-8 p-12 rounded ${
                userTestResult.success ? 'bg-positive/10 text-positive' : 'bg-danger/10 text-danger'
              }`}>
                {userTestResult.success ? (
                  <CheckCircleIcon size="sm" />
                ) : (
                  <ErrorIcon size="sm" />
                )}
                <div className="flex-1 text-sm">
                  <p className="font-medium">{userTestResult.message}</p>
                  {userTestResult.error?.suggestion && (
                    <p className="mt-4 text-xs opacity-80">
                      {userTestResult.error.suggestion}
                    </p>
                  )}
                  {userTestResult.data?.needs_login && (
                    <p className="mt-8 text-xs font-semibold">
                      <Trans message="Click 'Login' button below to create session" />
                    </p>
                  )}
                </div>
              </div>
            )}
          </div>
          
          <div className="flex gap-8">
            <Button
              variant="outline"
              color="primary"
              size="sm"
              onClick={handleTestUser}
              disabled={!canTestUser || userTesting}
            >
              {userTesting ? (
                <Trans message="Testing..." />
              ) : (
                <Trans message="Test" />
              )}
            </Button>
            
            <DialogTrigger type="modal">
              <Button
                variant="flat"
                color="primary"
                size="sm"
                disabled={!canTestUser}
              >
                <Trans message="Login" />
              </Button>
              <TelegramLoginDialog apiId={apiId} apiHash={apiHash} phone={phone} />
            </DialogTrigger>
            
            <DialogTrigger type="modal">
              <Button
                variant="outline"
                color="danger"
                size="sm"
                disabled={!canTestUser || userLogout}
                startIcon={<LogoutIcon />}
              >
                {userLogout ? (
                  <Trans message="Logging out..." />
                ) : (
                  <Trans message="Logout" />
                )}
              </Button>
              <ConfirmationDialog
                isDanger
                title={<Trans message="Logout from Telegram" />}
                body={
                  <Trans message="Are you sure you want to logout? This will delete the session file and you'll need to login again for large file uploads." />
                }
                confirm={<Trans message="Logout" />}
                onConfirm={handleLogoutUser}
              />
            </DialogTrigger>
          </div>
        </div>
      </div>

      {/* Session File Upload */}
      <div className="border border-divider rounded-panel p-20">
        <div className="flex items-start justify-between gap-16">
          <div className="flex-1">
            <h3 className="text-base font-semibold mb-4">
              <Trans message="Upload Existing Session" />
            </h3>
            <p className="text-sm text-muted">
              <Trans message="If you already have a Telegram session file, upload it here to skip the login process." />
            </p>
          </div>
          
          <DialogTrigger type="modal">
            <Button
              variant="outline"
              color="primary"
              size="sm"
              disabled={!apiId || !phone}
              startIcon={<CloudUploadIcon />}
            >
              <Trans message="Upload Session" />
            </Button>
            <SessionUploadDialog apiId={apiId} apiHash={apiHash} phone={phone} />
          </DialogTrigger>
        </div>
      </div>
    </div>
  );
}

function TelegramLoginDialog({apiId, apiHash, phone}: {apiId: string; apiHash: string; phone: string}) {
  const {close} = useDialogContext();
  const [step, setStep] = useState<'init' | 'code' | 'password'>('init');
  const [phoneCodeHash, setPhoneCodeHash] = useState('');
  const [loading, setLoading] = useState(false);
  
  const form = useForm({
    defaultValues: {
      code: '',
      password: '',
    },
  });

  const handleStartLogin = async () => {
    setLoading(true);
    try {
      const response = await apiClient.post<TelegramTestResponse>('admin/telegram/login-user', {
        api_id: apiId,
        api_hash: apiHash,
        phone: phone,
      });

      if (response.data.success && response.data.data?.needs_code) {
        setPhoneCodeHash(response.data.data.phone_code_hash || '');
        setStep('code');
        toast.positive('Verification code sent to your Telegram!');
      } else {
        toast.positive(response.data.message);
      }
    } catch (error: any) {
      toast.danger(error.response?.data?.message || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyCode = async (values: {code: string; password: string}) => {
    setLoading(true);
    try {
      const response = await apiClient.post<TelegramTestResponse>('admin/telegram/verify-code', {
        api_id: apiId,
        api_hash: apiHash,
        phone: phone,
        code: values.code,
        phone_code_hash: phoneCodeHash,
      });

      if (response.data.success) {
        toast.positive(response.data.message);
        close();
      } else if (response.data.needs_password) {
        // 2FA password required
        setStep('password');
        toast('Please enter your two-factor authentication password');
      } else {
        toast.danger(response.data.message);
      }
    } catch (error: any) {
      toast.danger(error.response?.data?.message || 'Verification failed');
    } finally {
      setLoading(false);
    }
  };

  const handleComplete2FA = async (values: {password: string}) => {
    setLoading(true);
    try {
      const response = await apiClient.post<TelegramTestResponse>('admin/telegram/complete-2fa', {
        api_id: apiId,
        api_hash: apiHash,
        phone: phone,
        password: values.password,
      });

      if (response.data.success) {
        toast.positive(response.data.message);
        close();
      } else {
        toast.danger(response.data.message);
      }
    } catch (error: any) {
      toast.danger(error.response?.data?.message || '2FA verification failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog size="md">
      <DialogHeader>
        <Trans message="Login to Telegram User Account" />
      </DialogHeader>
      <DialogBody>
        {step === 'init' && (
          <div className="space-y-16">
            <p className="text-sm text-muted">
              <Trans message="A verification code will be sent to your Telegram account. Make sure you have access to:" />
            </p>
            <div className="bg-alt rounded p-12 space-y-8 text-sm">
              <div><strong>Phone:</strong> {phone}</div>
              <div><strong>API ID:</strong> {apiId}</div>
            </div>
            <p className="text-xs text-muted">
              <Trans message="The verification code will appear in your Telegram app. It will be sent by Telegram official." />
            </p>
          </div>
        )}

        {step === 'code' && (
          <Form form={form} onSubmit={handleVerifyCode}>
            <div className="space-y-16">
              <p className="text-sm text-positive">
                <Trans message="Verification code sent! Check your Telegram app." />
              </p>
              <FormTextField
                name="code"
                label={<Trans message="Verification Code" />}
                placeholder="12345"
                required
                autoFocus
                description={
                  <Trans message="Enter the code sent to your Telegram account" />
                }
              />
            </div>
          </Form>
        )}

        {step === 'password' && (
          <Form form={form} onSubmit={handleComplete2FA}>
            <div className="space-y-16">
              <div className="bg-warning/10 text-warning p-12 rounded text-sm">
                <p className="font-semibold mb-4">
                  <Trans message="Two-Factor Authentication Required" />
                </p>
                <Trans message="Your account has two-factor authentication (2FA) enabled. Please enter your cloud password." />
              </div>
              <FormTextField
                name="password"
                label={<Trans message="Cloud Password (2FA)" />}
                placeholder="••••••••"
                type="password"
                required
                autoFocus
                description={
                  <Trans message="Enter your Telegram cloud password (two-factor authentication)" />
                }
              />
              <p className="text-xs text-muted">
                <Trans message="This is the password you set up in Settings > Privacy and Security > Two-Step Verification" />
              </p>
            </div>
          </Form>
        )}
      </DialogBody>
      <DialogFooter>
        {step === 'init' && (
          <Button
            variant="flat"
            color="primary"
            onClick={handleStartLogin}
            disabled={loading}
          >
            {loading ? <Trans message="Sending..." /> : <Trans message="Send Code" />}
          </Button>
        )}
        
        {step === 'code' && (
          <Button
            variant="flat"
            color="primary"
            onClick={form.handleSubmit(handleVerifyCode)}
            disabled={loading}
          >
            {loading ? <Trans message="Verifying..." /> : <Trans message="Verify Code" />}
          </Button>
        )}
        
        {step === 'password' && (
          <Button
            variant="flat"
            color="primary"
            onClick={form.handleSubmit(handleComplete2FA)}
            disabled={loading}
          >
            {loading ? <Trans message="Verifying..." /> : <Trans message="Complete Login" />}
          </Button>
        )}
      </DialogFooter>
    </Dialog>
  );
}

function SessionUploadDialog({apiId, apiHash, phone}: {apiId: string; apiHash: string; phone: string}) {
  const {close} = useDialogContext();
  const [file, setFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);

  const handleUpload = async () => {
    if (!file) {
      toast.danger('Please select a session file');
      return;
    }

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('api_id', apiId);
      formData.append('api_hash', apiHash);
      formData.append('phone', phone);
      formData.append('session_file', file);

      const response = await apiClient.post<TelegramTestResponse>('admin/telegram/test-user', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      if (response.data.success) {
        toast.positive(response.data.message);
        close();
      } else {
        toast.danger(response.data.message);
      }
    } catch (error: any) {
      toast.danger(error.response?.data?.message || 'Upload failed');
    } finally {
      setUploading(false);
    }
  };

  return (
    <Dialog size="md">
      <DialogHeader>
        <Trans message="Upload Telegram Session File" />
      </DialogHeader>
      <DialogBody>
        <div className="space-y-16">
          <p className="text-sm text-muted">
            <Trans message="Upload an existing Telegram session file (JSON format) to activate user account without login." />
          </p>
          
          <div>
            <input
              type="file"
              accept=".json"
              onChange={(e) => setFile(e.target.files?.[0] || null)}
              className="block w-full text-sm text-muted
                file:mr-16 file:py-8 file:px-16
                file:rounded file:border-0
                file:text-sm file:font-medium
                file:bg-primary file:text-on-primary
                hover:file:bg-primary-dark"
            />
          </div>

          {file && (
            <div className="bg-positive/10 text-positive p-12 rounded text-sm">
              <Trans message="Selected: :filename" values={{filename: file.name}} />
            </div>
          )}

          <div className="bg-warning/10 text-warning p-12 rounded text-xs">
            <p className="font-semibold mb-4">
              <Trans message="Security Note:" />
            </p>
            <Trans message="Session files contain sensitive authentication data. Only upload session files you trust." />
          </div>
        </div>
      </DialogBody>
      <DialogFooter>
        <Button
          variant="flat"
          color="primary"
          onClick={handleUpload}
          disabled={!file || uploading}
        >
          {uploading ? <Trans message="Uploading..." /> : <Trans message="Upload & Test" />}
        </Button>
      </DialogFooter>
    </Dialog>
  );
}
