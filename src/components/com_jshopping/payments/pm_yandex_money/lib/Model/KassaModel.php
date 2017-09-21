<?php

namespace YandexMoneyModule\Model;

use YaMoney\Model\PaymentMethodType;

class KassaModel
{
    protected $enabled;
    protected $shopId;
    protected $password;
    protected $epl;
    protected $useYandexButton;
    protected $paymentMethods;
    protected $sendReceipt;
    protected $defaultTaxRate;
    protected $taxRates;
    protected $pendingOrderStatus;
    protected $successOrderStatus;
    protected $failureOrderStatus;
    protected $geoZone;
    protected $minPaymentAmount;
    protected $log;

    public function __construct(Config $config)
    {
        $this->enabled = (bool)$config->get('yandex_money_kassa_enabled');
        $this->shopId = $config->get('yandex_money_kassa_shop_id');
        $this->password = $config->get('yandex_money_kassa_password');
        $this->epl = $config->get('yandex_money_kassa_payment_mode') !== 'shop';
        $this->useYandexButton = (bool)$config->get('yandex_money_kassa_use_yandex_button');

        $this->paymentMethods = array();
        foreach (PaymentMethodType::getEnabledValues() as $value) {
            $property = 'yandex_money_kassa_payment_method_' . $value;
            $this->paymentMethods[$value] = (bool)$config->get($property);
        }

        $this->sendReceipt = (bool)$config->get('yandex_money_kassa_send_receipt');
        $this->defaultTaxRate = (int)$config->get('yandex_money_kassa_tax_rate_default');
        $this->pendingOrderStatus = (int)$config->get('yandex_money_kassa_pending_order_status');
        $this->successOrderStatus = (int)$config->get('yandex_money_kassa_success_order_status');
        $this->failureOrderStatus = (int)$config->get('yandex_money_kassa_failure_order_status');
        $this->minPaymentAmount = (int)$config->get('yandex_money_kassa_minimum_payment_amount');
        $this->geoZone = (int)$config->get('yandex_money_kassa_geo_zone');
        $this->log = (bool)$config->get('yandex_money_kassa_debug_log');

        $this->taxRates = array();
        $tmp = $config->get('yandex_money_kassa_tax_rates');
        if (!empty($tmp)) {
            if (is_array($tmp)) {
                foreach ($tmp as $shopTaxRateId => $kassaTaxRateId) {
                    $this->taxRates[$shopTaxRateId] = $kassaTaxRateId;
                }
            }
        }
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function getShopId()
    {
        return $this->shopId;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getEPL()
    {
        return $this->epl;
    }

    public function useYandexButton()
    {
        return $this->useYandexButton;
    }

    public function getPaymentMethods()
    {
        return $this->paymentMethods;
    }

    public function getEnabledPaymentMethods()
    {
        $result = array();
        foreach ($this->paymentMethods as $method => $enabled) {
            if ($enabled) {
                $result[] = $method;
            }
        }
        return $result;
    }

    public function isPaymentMethodEnabled($paymentMethod)
    {
        return isset($this->paymentMethods[$paymentMethod]) && $this->paymentMethods[$paymentMethod];
    }

    public function sendReceipt()
    {
        return $this->sendReceipt;
    }

    public function getTaxRateList()
    {
        return array(1, 2, 3, 4, 5, 6);
    }

    public function getDefaultTaxRate()
    {
        return $this->defaultTaxRate;
    }

    public function getTaxRateId($shopTaxRateId)
    {
        if (isset($this->taxRates[$shopTaxRateId])) {
            return $this->taxRates[$shopTaxRateId];
        }
        return $this->defaultTaxRate;
    }

    public function getTaxRates()
    {
        return $this->taxRates;
    }

    public function getPendingOrderStatusId()
    {
        return $this->pendingOrderStatus;
    }

    public function getSuccessOrderStatusId()
    {
        return $this->successOrderStatus;
    }

    public function getFailureOrderStatusId()
    {
        return $this->failureOrderStatus;
    }

    public function getMinPaymentAmount()
    {
        return $this->minPaymentAmount;
    }

    public function getGeoZoneId()
    {
        return $this->geoZone;
    }

    public function getDebugLog()
    {
        return $this->log;
    }
}