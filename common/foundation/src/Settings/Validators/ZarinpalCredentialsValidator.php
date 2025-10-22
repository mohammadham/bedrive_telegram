<?php

namespace Common\Settings\Validators;

use Common\Billing\Gateways\Zarinpal\Zarinpal;
use Common\Settings\Settings;
use Config;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Arr;

class ZarinpalCredentialsValidator implements SettingsValidator
{
    const KEYS = [
        'zarinpal_merchant_id',
        'billing.zarinpal_test_mode',
    ];

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @param Settings $settings
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function fails($values)
    {
        if (!isset($values['billing.zarinpal_test_mode'])) {
            $values['billing.zarinpal_test_mode'] = false;
        }

        $this->setConfigDynamically($values);

        // Test connection by attempting a simple request
        // For ZarinPal, we'll just validate that merchant_id is not empty
        try {
            $merchantId = config('services.zarinpal.merchant_id');
            
            if (empty($merchantId)) {
                return $this->getErrorMessage('Merchant ID is required');
            }

            // Validate merchant ID format (should be 36 characters)
            if (strlen($merchantId) !== 36) {
                return $this->getErrorMessage('Invalid Merchant ID format');
            }

            // Optional: Try to make a test request
            // For now, we just validate the format
            
        } catch (ClientException $e) {
            return $this->getDefaultError();
        } catch (\Exception $e) {
            return $this->getDefaultError();
        }
    }

    private function setConfigDynamically($settings)
    {
        foreach (self::KEYS as $key) {
            if (!Arr::has($settings, $key)) {
                continue;
            }

            if ($key === 'billing.zarinpal_test_mode') {
                $this->settings->set(
                    'billing.zarinpal_test_mode',
                    $settings[$key],
                );
            } else {
                // zarinpal_merchant_id => merchant_id
                $configKey = str_replace('zarinpal_', '', $key);
                Config::set("services.zarinpal.$configKey", $settings[$key]);
            }
        }
    }

    /**
     * @param string $message
     * @return array
     */
    private function getErrorMessage($message)
    {
        return [
            'zarinpal_group' => $message,
        ];
    }

    private function getDefaultError()
    {
        return ['zarinpal_group' => 'These ZarinPal credentials are not valid.'];
    }
}
