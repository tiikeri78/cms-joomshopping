<?php

namespace YandexMoney\Model;

use YandexCheckout\Client;
use YandexCheckout\Common\Exceptions\NotFoundException;
use YandexCheckout\Model\ConfirmationType;
use YandexCheckout\Model\Payment;
use YandexCheckout\Model\PaymentInterface;
use YandexCheckout\Model\PaymentMethodType;
use YandexCheckout\Model\PaymentStatus;
use YandexCheckout\Request\Payments\CreatePaymentRequest;
use YandexCheckout\Request\Payments\Payment\CreateCaptureRequest;

class KassaPaymentMethod
{
    private $module;
    private $client;
    private $shopId;
    private $password;
    private $defaultTaxRateId;
    private $taxRates;
    private $sendReceipt;
    private $descriptionTemplate;

    /**
     * KassaPaymentMethod constructor.
     * @param \pm_yandex_money $module
     * @param array $pmConfig
     */
    public function __construct($module, $pmConfig)
    {
        $this->module = $module;
        $this->shopId = $pmConfig['shop_id'];
        $this->password = $pmConfig['shop_password'];
        $this->descriptionTemplate = !empty($pmConfig['ya_kassa_description_template'])
            ? $pmConfig['ya_kassa_description_template']
            : _JSHOP_YM_DESCRIPTION_DEFAULT_PLACEHOLDER;

        $this->defaultTaxRateId = 1;
        if (!empty($pmConfig['ya_kassa_default_tax'])) {
            $this->defaultTaxRateId = $pmConfig['ya_kassa_default_tax'];
        }

        $this->taxRates = array();
        foreach ($pmConfig as $key => $value) {
            if (strncmp('ya_kassa_tax_', $key, 13) === 0) {
                $taxRateId = substr($key, 13);
                $this->taxRates[$taxRateId] = $value;
            }
        }

        $this->sendReceipt = isset($pmConfig['ya_kassa_send_check']) && $pmConfig['ya_kassa_send_check'] == '1';
    }

    public function getShopId()
    {
        return $this->shopId;
    }

    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param \jshopOrder $order
     * @param \jshopCart $cart
     * @param $returnUrl
     *
     * @return null|\YandexCheckout\Request\Payments\CreatePaymentResponse
     *
     * @since version
     */
    public function createPayment($order, $cart, $returnUrl)
    {
        try {
            $builder = CreatePaymentRequest::builder();
            $builder->setAmount($order->order_total)
                ->setCapture(true)
                ->setClientIp($_SERVER['REMOTE_ADDR'])
                ->setDescription($this->createDescription($order))
                ->setMetadata(array(
                    'order_id'       => $order->order_id,
                    'cms_name'       => 'ya_api_joomshopping',
                    'module_version' => _JSHOP_YM_VERSION,
                ));

            $confirmation = array(
                'type' => ConfirmationType::REDIRECT,
                'returnUrl' => $returnUrl,
            );
            $params = unserialize($order->payment_params_data);
            if (!empty($params['payment_type'])) {
                $paymentType = $params['payment_type'];
                if ($paymentType === PaymentMethodType::ALFABANK) {
                    $paymentType = array(
                        'type' => $paymentType,
                        'login' => trim($params['alfaLogin']),
                    );
                    $confirmation = ConfirmationType::EXTERNAL;
                } elseif ($paymentType === PaymentMethodType::QIWI) {
                    $paymentType = array(
                        'type' => $paymentType,
                        'phone' => preg_replace('/[^\d]+/', '', $params['qiwiPhone']),
                    );
                }
                $builder->setPaymentMethodData($paymentType);
            }
            $builder->setConfirmation($confirmation);

            $receipt = null;
            if (count($cart->products) && $this->sendReceipt) {
                $this->factoryReceipt($builder, $cart, $order);
            }

            $request = $builder->build();
            if ($request->hasReceipt()) {
                $request->getReceipt()->normalize($request->getAmount());
            }
        } catch (\Exception $e) {
            $this->module->log('error', 'Failed to build request: ' . $e->getMessage());
            return null;
        }

        try {
            $tries = 0;
            $key = uniqid('', true);
            do {
                $payment = $this->getClient()->createPayment($request, $key);
                if ($payment === null) {
                    $tries++;
                    if ($tries > 3) {
                        break;
                    }
                    sleep(2);
                }
            } while ($payment === null);
        } catch (\Exception $e) {
            $this->module->log('error', 'Failed to create payment: ' . $e->getMessage());
            return null;
        }
        return $payment;
    }

    /**
     * @param PaymentInterface $notificationPayment
     * @param bool $fetchPayment
     * @return PaymentInterface|null
     */
    public function capturePayment($notificationPayment, $fetchPayment = true)
    {
        if ($fetchPayment) {
            $payment = $this->fetchPayment($notificationPayment->getId());
        } else {
            $payment = $notificationPayment;
        }
        if ($payment->getStatus() !== PaymentStatus::WAITING_FOR_CAPTURE) {
            return $payment->getStatus() === PaymentStatus::SUCCEEDED ? $payment : null;
        }

        try {
            $builder = CreateCaptureRequest::builder();
            $builder->setAmount($payment->getAmount());
            $request = $builder->build();
        } catch (\Exception $e) {
            $this->module->log('error', 'Failed to create capture payment: ' . $e->getMessage());
            return null;
        }

        try {
            $tries = 0;
            $key = uniqid('', true);
            do {
                $response = $this->getClient()->capturePayment($request, $payment->getId(), $key);
                if ($response === null) {
                    $tries++;
                    if ($tries > 3) {
                        break;
                    }
                    sleep(2);
                }
            } while ($response === null);
        } catch (\Exception $e) {
            $this->module->log('error', 'Failed to capture payment: ' . $e->getMessage());
            return null;
        }

        return $response;
    }

    /**
     * @param string $paymentId
     * @return PaymentInterface|null
     */
    public function fetchPayment($paymentId)
    {
        $payment = null;
        try {
            $payment = $this->getClient()->getPaymentInfo($paymentId);
        } catch (\Exception $e) {
            $this->module->log('error', 'Failed to fetch payment information from API: ' . $e->getMessage());
        }
        return $payment;
    }

    /**
     * @param \YandexCheckout\Request\Payments\CreatePaymentRequestBuilder $builder
     * @param $cart
     * @param $order
     */
    private function factoryReceipt($builder, $cart, $order)
    {
        $shippingModel = \JSFactory::getTable('shippingMethod', 'jshop');
        $shippingMethods = $shippingModel->getAllShippingMethodsCountry($order->d_country, $order->payment_method_id);
        $defaultTaxRate = $this->defaultTaxRateId;
        if (!empty($order->email)) {
            $builder->setReceiptEmail($order->email);
        } else {
            $builder->setReceiptPhone($order->phone);
        }
        $shipping = false;
        foreach ($shippingMethods as $tmp) {
            if ($tmp->shipping_id == $order->shipping_method_id) {
                $shipping = $tmp;
            }
        }

        foreach ($cart->products as $product) {
            if (isset($product['tax_id']) && !empty($this->taxRates[$product['tax_id']])) {
                $taxId = $this->taxRates[$product['tax_id']];
                $builder->addReceiptItem($product['product_name'], $product['price'], $product['quantity'], $taxId);
            } else {
                $builder->addReceiptItem($product['product_name'], $product['price'], $product['quantity'], $defaultTaxRate);
            }
        }

        if ($order->shipping_method_id && $shipping) {
            if (!empty($this->taxRates[$shipping->shipping_tax_id])) {
                $taxId = $this->taxRates[$shipping->shipping_tax_id];
                $builder->addReceiptShipping($shipping->name, $shipping->shipping_stand_price, $taxId);
            } else {
                $builder->addReceiptShipping($shipping->name, $shipping->shipping_stand_price, $defaultTaxRate);
            }
        }
    }

    /**
     * @return Client
     */
    private function getClient()
    {
        if ($this->client === null) {
            $this->client = new Client();
            $this->client->setAuth($this->shopId, $this->password);
            $this->client->setLogger($this->module);
        }
        return $this->client;
    }

    /**
     * @return bool
     */
    public function checkConnection()
    {
        try {
            $payment = $this->getClient()->getPaymentInfo('00000000-0000-0000-0000-000000000001');
        } catch (NotFoundException $e) {
            return true;
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * @param \jshopOrder $order
     * @return string
     */
    private function createDescription($order)
    {
        $descriptionTemplate = $this->descriptionTemplate;

        $replace = array();
        foreach ($order as $property => $value) {
            $replace['%'.$property.'%'] = $value;
        }

        $description = strtr($descriptionTemplate, $replace);

        return (string)mb_substr($description, 0, Payment::MAX_LENGTH_DESCRIPTION);
    }
}