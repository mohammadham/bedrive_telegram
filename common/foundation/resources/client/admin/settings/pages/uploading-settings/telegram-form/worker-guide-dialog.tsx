import {Dialog} from '@ui/overlays/dialog/dialog';
import {DialogHeader} from '@ui/overlays/dialog/dialog-header';
import {DialogBody} from '@ui/overlays/dialog/dialog-body';
import {Trans} from '@ui/i18n/trans';
import {Button} from '@ui/buttons/button';
import {useState} from 'react';
import {ContentCopyIcon} from '@ui/icons/material/ContentCopy';
import {CheckIcon} from '@ui/icons/material/Check';
import {toast} from '@ui/toast/toast';

interface WorkerGuideDialogProps {
  isOpen: boolean;
  onClose: () => void;
}

const WORKER_CODE = `export default {
  /**
   * Cloudflare Worker for Telegram URL Upload Fallback
   * این Worker به عنوان یک proxy عمل می‌کند تا دسترسی به URLهایی که 
   * از IP سرور شما block شده‌اند را فراهم کند
   * 
   * @param {Request} request
   * @param {any} env
   * @param {any} ctx
   */
  async fetch(request, env, ctx) {
    // تنظیم CORS
    const corsHeaders = {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, HEAD, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type',
    };

    // مدیریت OPTIONS (preflight)
    if (request.method === 'OPTIONS') {
      return new Response(null, { headers: corsHeaders });
    }

    try {
      let targetUrl;

      if (request.method === 'GET' || request.method === 'HEAD') {
        // دریافت URL از query param
        const url = new URL(request.url);
        targetUrl = url.searchParams.get('url');
      } else if (request.method === 'POST') {
        // دریافت URL از body (JSON)
        const body = await request.json();
        targetUrl = body.url;
      }

      if (!targetUrl) {
        return new Response('Missing URL parameter', { 
          status: 400, 
          headers: corsHeaders 
        });
      }

      // اطمینان از اینکه URL معتبر است
      try {
        new URL(targetUrl);
      } catch (e) {
        return new Response('Invalid URL', { 
          status: 400, 
          headers: corsHeaders 
        });
      }

      // اتصال به URL مبدأ
      const originResponse = await fetch(targetUrl, {
        method: request.method,
      });

      if (!originResponse.ok) {
        return new Response(\`Error from origin: \${originResponse.status}\`, { 
          status: originResponse.status, 
          headers: corsHeaders 
        });
      }

      // تهیه هدرها از پاسخ اصلی
      const headers = new Headers(originResponse.headers);
      headers.set('Access-Control-Allow-Origin', '*');

      // ایجاد پاسخ با استریم بدنه
      return new Response(originResponse.body, {
        status: originResponse.status,
        statusText: originResponse.statusText,
        headers: headers,
      });
    } catch (err) {
      console.error('Error in proxy:', err);
      return new Response('Proxy error: ' + err.message, { 
        status: 502, 
        headers: corsHeaders 
      });
    }
  }
};`;

export function WorkerGuideDialog({isOpen, onClose}: WorkerGuideDialogProps) {
  const [copied, setCopied] = useState(false);

  const handleCopy = () => {
    navigator.clipboard.writeText(WORKER_CODE);
    setCopied(true);
    toast.positive('کد Worker کپی شد!');
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <Dialog isOpen={isOpen} onClose={onClose} size="fullscreenTakeover">
      <DialogHeader>
        <Trans message="راهنمای نصب Cloudflare Worker" />
      </DialogHeader>
      <DialogBody>
        <div className="space-y-24">
          {/* توضیحات */}
          <div className="bg-primary/5 p-16 rounded-lg">
            <h3 className="text-lg font-semibold mb-12">
              <Trans message="چرا Worker نیاز است؟" />
            </h3>
            <p className="text-sm mb-8">
              <Trans message="برخی سایت‌ها ممکن است IP سرور شما را مسدود کرده باشند یا محدودیت‌های جغرافیایی داشته باشند. با استفاده از Cloudflare Worker، شما می‌توانید این محدودیت‌ها را دور بزنید." />
            </p>
            <p className="text-sm text-muted">
              <Trans message="Worker به عنوان یک proxy میانی عمل می‌کند و فایل را از منبع دانلود کرده و به سرور شما ارسال می‌کند." />
            </p>
          </div>

          {/* مراحل نصب */}
          <div>
            <h3 className="text-lg font-semibold mb-16">
              <Trans message="مراحل نصب" />
            </h3>
            <ol className="list-decimal list-inside space-y-12 text-sm">
              <li>
                <Trans message="به داشبورد Cloudflare خود بروید:" />{' '}
                <a
                  href="https://dash.cloudflare.com"
                  target="_blank"
                  rel="noreferrer"
                  className="text-primary underline"
                >
                  dash.cloudflare.com
                </a>
              </li>
              <li>
                <Trans message="از منوی سمت چپ، گزینه Workers & Pages را انتخاب کنید" />
              </li>
              <li>
                <Trans message='روی دکمه "Create" کلیک کنید' />
              </li>
              <li>
                <Trans message='گزینه "Create Worker" را انتخاب کنید' />
              </li>
              <li>
                <Trans message="یک نام برای Worker خود انتخاب کنید (مثلاً: telegram-proxy)" />
              </li>
              <li>
                <Trans message='روی "Deploy" کلیک کنید' />
              </li>
              <li>
                <Trans message='سپس روی "Edit code" کلیک کنید' />
              </li>
              <li>
                <Trans message="کد زیر را کپی کرده و جایگزین کد پیش‌فرض کنید" />
              </li>
              <li>
                <Trans message='روی "Save and Deploy" کلیک کنید' />
              </li>
              <li>
                <Trans message="URL Worker خود را کپی کنید (مثال: https://telegram-proxy.your-subdomain.workers.dev)" />
              </li>
              <li>
                <Trans message="URL را در فیلد زیر وارد کنید" />
              </li>
            </ol>
          </div>

          {/* کد Worker */}
          <div>
            <div className="flex items-center justify-between mb-12">
              <h3 className="text-lg font-semibold">
                <Trans message="کد Worker" />
              </h3>
              <Button
                variant="outline"
                size="xs"
                startIcon={copied ? <CheckIcon /> : <ContentCopyIcon />}
                onClick={handleCopy}
                color={copied ? 'positive' : 'primary'}
              >
                {copied ? <Trans message="کپی شد!" /> : <Trans message="کپی کد" />}
              </Button>
            </div>
            <div className="relative">
              <pre className="bg-black text-white p-16 rounded-lg overflow-x-auto text-xs max-h-400 overflow-y-auto">
                <code>{WORKER_CODE}</code>
              </pre>
            </div>
          </div>

          {/* نکات مهم */}
          <div className="bg-warning/10 p-16 rounded-lg">
            <h3 className="text-lg font-semibold mb-12">
              <Trans message="⚠️ نکات مهم" />
            </h3>
            <ul className="list-disc list-inside space-y-8 text-sm">
              <li>
                <Trans message="Worker رایگان Cloudflare محدودیت 100,000 درخواست در روز دارد" />
              </li>
              <li>
                <Trans message="برای استفاده بیشتر، می‌توانید پلن Workers Paid را خریداری کنید" />
              </li>
              <li>
                <Trans message="Worker فقط برای validation استفاده می‌شود، نه برای دانلود کامل فایل" />
              </li>
              <li>
                <Trans message="در صورت عدم تنظیم Worker، سیستم مستقیماً به URL متصل می‌شود" />
              </li>
              <li>
                <Trans message="پس از تنظیم Worker، حتماً آن را تست کنید" />
              </li>
            </ul>
          </div>

          {/* دکمه بستن */}
          <div className="flex justify-end">
            <Button onClick={onClose} variant="flat">
              <Trans message="بستن" />
            </Button>
          </div>
        </div>
      </DialogBody>
    </Dialog>
  );
}
