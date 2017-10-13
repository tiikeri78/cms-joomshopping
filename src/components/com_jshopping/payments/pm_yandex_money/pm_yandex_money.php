<?php

/**
 * @package JoomShopping for Joomla!
 * @subpackage payment
 * @author YandexMoney
 * @copyright Copyright (C) 2012-2017 YandexMoney. All rights reserved.
 */

defined('_JEXEC') or die('Restricted access');

if ((version_compare(JVERSION, '3.0', '<') > 0)) {
    include dirname(__FILE__) . '/lib/autoload.php';
} else {
    JLoader::registerNamespace('YaMoney', dirname(__FILE__) . '/lib/yandex-checkout-sdk/lib', false, false, 'psr4');
    JLoader::registerNamespace('Psr\\Log', dirname(__FILE__) . '/lib/yandex-checkout-sdk/vendor/psr-log', false, false, 'psr4');
}

class pm_yandex_money extends PaymentRoot
{
    const MODE_OFF = 0;
    const MODE_KASSA = 1;
    const MODE_MONEY = 2;
    const MODE_PAYMENTS = 3;

    private $mode = -1;
    private $joomlaVersion;

    public $existentcheckform = true;
    public $ym_pay_mode, $ym_test_mode, $ym_password, $ym_shopid, $ym_scid;

    /**
     * @var YandexMoney\Model\OrderModel
     */
    private $orderModel;

    public function __construct()
    {
        $this->joomlaVersion = (version_compare(JVERSION, '3.0', '<') == 1) ? 2 : 3;
    }

    function showPaymentForm($params, $pmConfigs)
    {
        $this->loadLanguageFile();
        $this->mode = $this->getMode($pmConfigs);
        if ($this->mode === self::MODE_KASSA) {
            include(dirname(__FILE__) . "/payment_form_kassa.php");
        } else {
            include(dirname(__FILE__) . "/paymentform.php");
        }
    }

    private function getArticlesList()
    {
        JModelLegacy::addIncludePath(JPATH_SITE.'/components/com_content/models', 'ContentModel');
        $model = JModelLegacy::getInstance('Articles', 'ContentModel', array('ignore_request' => true));
        $params = JComponentHelper::getParams('com_content');
        $model->setState('params', $params);
        $model->setState('list.limit', 0);
        $articles = $model->getItems();
        $list = array();
        foreach ($articles as $page) {
            $list[$page->id] = $page->title;
        }
        return $list;
    }

    public function getDisplayNameParams()
    {
        $names = array();
        $this->mode = $this->getMode($this->getParams());
        if ($this->mode == self::MODE_PAYMENTS) {
            $names = array(
                'ya_payments_fio' => _JSHOP_YM_PAYMENTS_FIO_LABEL,
            );
        }
        return $names;
    }

    /**
     * Проверяет параметры указанные пользователем на странице выбора способа оплаты
     * @param array $params Массив параметров, указанных в params[pm_yandex_money] на странице выбора способа оплаты
     * @param array $pmConfigs Настройки модуля оплаты
     * @return bool True если все параметры вылидны, false если нет
     */
    public function checkPaymentInfo($params, $pmConfigs)
    {
        $this->mode = $this->getMode($pmConfigs);
        if ($this->mode == self::MODE_PAYMENTS) {
            // если платёжка, то проверяем ФИО указанные пользователем
            if (empty($params) || empty($params['ya_payments_fio'])) {
                return 0;
            }
            $name = trim($params['ya_payments_fio']);
            if (empty($name)) {
                return false;
            }
        } elseif ($this->mode === self::MODE_KASSA) {
            // если оплата через кассу, то должен быть указан способ оплаты
            if (!isset($params['payment_type'])) {
                return false;
            } else {
                $paymentType = $params['payment_type'];
                if (empty($paymentType) && $pmConfigs['paymode'] == '1') {
                    return true;
                } else {
                    return \YaMoney\Model\PaymentMethodType::valueExists($paymentType);
                }
            }
        }
        return true;
    }

    /**
     * function call in admin
     */
    public function showAdminFormParams($params)
    {
        $array_params = array(
            'kassa_send_check', 'testmode', 'paymode', 'moneymode', 'kassamode', 'paymentsmode', 'method_ym',
            'method_cards', 'method_ym2', 'method_cards2', 'method_cash', 'method_phone', 'method_wm',
            'method_ab', 'method_sb', 'method_ma', 'method_pb', 'method_qw', 'method_qp', 'password',
            'shoppassword', 'shopid', 'scid', 'account', 'transaction_end_status', 'ym_pay_id', 'ym_pay_desc',
            'ya_payments_fio'
        );
        $taxes = $taxes = JSFactory::getAllTaxes();

        foreach ($taxes as $k => $tax) {
            $array_params[] = 'kassa_tax_' . $k;
        }

        foreach ($array_params as $key) {
            if (!isset($params[$key])) {
                $params[$key] = '';
            }
        }
        if (!isset($params['use_ssl'])) {
            $params['use_ssl'] = 0;
        }
        $this->loadLanguageFile();
        $orders = JModelLegacy::getInstance('orders', 'JshoppingModel'); //admin model
        $filename = $this->joomlaVersion === 2 ? '2x' : '';
        include(dirname(__FILE__)."/adminparamsform".$filename.".php");
    }

    private function loadLanguageFile()
    {
        $lang = JFactory::getLanguage();
        $langTag = $lang->getTag();
        if (file_exists(JPATH_ROOT.'/components/com_jshopping/payments/pm_yandex_money/lang/'.$langTag.'.php')) {
            require_once(JPATH_ROOT.'/components/com_jshopping/payments/pm_yandex_money/lang/'.$langTag.'.php');
        } else {
            require_once(JPATH_ROOT.'/components/com_jshopping/payments/pm_yandex_money/lang/ru-RU.php');
        }
    }

    public function checkSign($callbackParams)
    {
        if ($this->mode == self::MODE_MONEY) {
            $string = $callbackParams['notification_type'].'&'.$callbackParams['operation_id'].'&'
                .$callbackParams['amount'].'&'.$callbackParams['currency'].'&'.$callbackParams['datetime'].'&'
                .$callbackParams['sender'].'&'.$callbackParams['codepro'].'&'.$this->ym_password.'&'
                .$callbackParams['label'];
            $check = (sha1($string) == $callbackParams['sha1_hash']);
            if (!$check) {
                header('HTTP/1.0 401 Unauthorized');
                return false;
            }
        }
        return true;
    }

    function checkTransaction($pmConfigs, $order, $act)
    {
        $this->mode = $this->getMode($pmConfigs);

        if ($this->mode != self::MODE_PAYMENTS) {
            $this->ym_pay_mode = ($pmConfigs['paymode'] == '1');
            $this->ym_shopid = $pmConfigs['shopid'];
            $this->ym_scid = $pmConfigs['scid'];

            $order->order_total = floatval($order->order_total);

            $callbackParams = JRequest::get('post');
            $this->loadLanguageFile();
            $check = $this->checkSign($callbackParams);
        } else {
            $check = true;
        }
        //
        if ($check) {
            if ($this->mode == self::MODE_KASSA) {
                return array(1, '');
            } else {
                return array(1, '');
            }
        } elseif($this->mode == self::MODE_KASSA) {
            return array(1, '');
        } else {
            return array(0, 'hash error');
        }
    }

    public function getFormUrl()
    {
        if ($this->mode == self::MODE_MONEY) {
            return $this->individualGetFormUrl();
        } else {
            return 'https://money.yandex.ru/fastpay/confirm';
        }
    }

    public function individualGetFormUrl()
    {
        if ($this->ym_test_mode) {
            return 'https://demomoney.yandex.ru/quickpay/confirm.xml';
        } else {
            return 'https://money.yandex.ru/quickpay/confirm.xml';
        }
    }

    function showEndForm($pmConfigs, $order)
    {
        $this->ym_test_mode = isset($pmConfigs['testmode']) ? $pmConfigs['testmode'] : false;
        $this->mode = $this->getMode($pmConfigs);
        if ($this->mode === self::MODE_KASSA) {
            $this->processKassaPayment($pmConfigs, $order);
            // если произошла ошибка, редиректим на шаг выбора метода оплаты
            $redirectUrl = JRoute::_(JURI::root().'index.php?option=com_jshopping&controller=checkout&task=step5');
            $app = JFactory::getApplication();
            $app->redirect($redirectUrl);
        }
        $this->ym_pay_mode = ($pmConfigs['paymode'] == '1');

        $uri = JURI::getInstance();
        $liveUrlHost = $uri->toString(array("scheme",'host', 'port'));

        $ym_params = unserialize($order->payment_params_data);

        $item_name = $liveUrlHost." ".sprintf(_JSHOP_PAYMENT_NUMBER, $order->order_number);
        $this->loadLanguageFile();

        $return = $liveUrlHost.SEFLink("index.php?option=com_jshopping&controller=checkout&task=step7&act=return&js_paymentclass=pm_yandexmoney&order_id=" . $order['id']);

        $order->order_total = $this->fixOrderTotal($order);
        if ($ym_params['ym-payment-type']=='MP'){
            $app = JFactory::getApplication();
            $app->redirect(JRoute::_(JURI::root().'index.php?option=com_content&view=article&id='.$pmConfigs['page_mpos']));
        }
?>
        <html>
        <head>
            <meta http-equiv="content-type" content="text/html; charset=utf-8" />
            <script src="/media/jui/js/jquery.min.js"></script>
        </head>
        <body>
    <?php if ($this->mode == self::MODE_MONEY) { ?>
        <form method="POST" action="<?php echo $this->getFormUrl(); ?>" id="paymentform" name = "paymentform">
            <input type="hidden" name="receiver" value="<?php echo $pmConfigs['account']; ?>">
            <input type="hidden" name="formcomment" value="<?php echo $item_name;?>">
            <input type="hidden" name="short-dest" value="<?php echo $item_name;?>">
            <input type="hidden" name="writable-targets" value="false">
            <input type="hidden" name="comment-needed" value="true">
            <input type="hidden" name="label" value="<?php echo $order->order_id;?>">
            <input type="hidden" name="quickpay-form" value="shop">
            <input type="hidden" name="paymentType" value="<?php echo $ym_params['ym-payment-type']?>" />
            <input type="hidden" name="targets" value="<?php echo $item_name;?>">
            <input type="hidden" name="sum" value="<?php echo $order->order_total;?>" data-type="number" >
            <input type="hidden" name="comment" value="<?php echo $order->order_add_info; ?>" >
            <input type="hidden" name="need-fio" value="true">
            <input type="hidden" name="need-email" value="true" >
            <input type="hidden" name="need-phone" value="false">
            <input type="hidden" name="need-address" value="false">
            <input type="hidden" name="successURL" value="<?php echo $return; ?>" >
            <?php echo _JSHOP_REDIRECT_TO_PAYMENT_PAGE; ?>
        </form>
    <?php } elseif ($this->mode == self::MODE_PAYMENTS) {
        $this->finishOrder($order, $pmConfigs['transaction_end_status']);
        $narrative = $this->parseTemplate($pmConfigs['ym_pay_desc'], $order);
        ?>
        <form method="POST" action="<?php echo $this->getFormUrl(); ?>" id="paymentform" name="paymentform">
            <input type="hidden" name="formId" value="<?php echo htmlspecialchars($pmConfigs['ym_pay_id']); ?>" />
            <input type="hidden" name="narrative" value="<?php echo htmlspecialchars($narrative); ?>" />
            <input type="hidden" name="fio" value="<?php echo htmlspecialchars($ym_params['ya_payments_fio']); ?>" />
            <input type="hidden" name="sum" value="<?php echo $order->order_total; ?>" />
            <input type="hidden" name="quickPayVersion" value="2" />
            <input type="hidden" name="cms_name" value="joomla" />
            <?php echo _JSHOP_REDIRECT_TO_PAYMENT_PAGE; ?>
        </form>
    <?php } ?>

    </body>
    <script type="text/javascript">document.getElementById('paymentform').submit();</script>
</html>
<?php
        die();
    }

    public function processKassaPayment($pmConfigs, $order)
    {
        $uri = JURI::getInstance();
        $redirectUrl = $uri->toString(array('scheme', 'host', 'port'))
            . SEFLink("index.php?option=com_jshopping&controller=checkout&task=step7&act=return&js_paymentclass=pm_yandex_money&no_lang=1&order_id=" . $order->order_id);
        try {
            $builder = \YaMoney\Request\Payments\CreatePaymentRequest::builder();
            $builder->setAmount($order->order_total)
                ->setCapture(false)
                ->setClientIp($_SERVER['REMOTE_ADDR'])
                ->setConfirmation(array(
                    'type' => \YaMoney\Model\ConfirmationType::REDIRECT,
                    'returnUrl' => $redirectUrl,
                ))
                ->setMetadata(array(
                    'order_id' => $order->order_id,
                ));

            $cart = JSFactory::getModel('cart', 'jshop');
            if ($this->joomlaVersion === 2) {
                $cart->load('cart');
            } else {
                $cart->init('cart', 1);
            }

            $params = unserialize($order->payment_params_data);
            if (!empty($params['payment_type'])) {
                $builder->setPaymentMethodData(array(
                    'type' => $params['payment_type'],
                ));
            }

            $receipt = null;
            if (count($cart->products) && isset($pmConfigs['ya_kassa_send_check']) && $pmConfigs['ya_kassa_send_check']) {
                $this->factoryReceipt($builder, $pmConfigs, $cart, $order);
            }

            $request = $builder->build();
            if ($request->hasReceipt()) {
                $request->getReceipt()->normalize($request->getAmount());
            }
        } catch (\Exception $e) {
            $this->log('error', 'Failed to build request: ' . $e->getMessage());
            return null;
        }

        try {
            $tries = 0;
            $key = $order->order_id . '-' . microtime(true);
            do {
                $payment = $this->getApiClient($pmConfigs)->createPayment($request, $key);
                if ($payment === null) {
                    $tries++;
                    if ($tries > 3) {
                        break;
                    }
                    sleep(2);
                }
            } while ($payment === null);

            $redirect = $redirectUrl;
            if ($payment !== null) {
                $confirmation = $payment->getConfirmation();
                if ($confirmation instanceof \YaMoney\Model\Confirmation\ConfirmationRedirect) {
                    $redirect = $confirmation->getConfirmationUrl();
                }
            }
        } catch (\Exception $e) {
            $this->log('error', 'Failed to create payment: ' . $e->getMessage());
            return null;
        }

        $this->getOrderModel()->savePayment($order->order_id, $payment);

        $app = JFactory::getApplication();
        $app->redirect($redirect);
    }

    /**
     * @param \YaMoney\Request\Payments\CreatePaymentRequestBuilder $builder
     * @param $pmConfigs
     * @param $cart
     * @param $order
     */
    private function factoryReceipt($builder, $pmConfigs, $cart, $order)
    {
        $shippingModel = JSFactory::getTable('shippingMethod', 'jshop');
        $shippingMethods = $shippingModel->getAllShippingMethodsCountry($order->d_country, $order->payment_method_id);

        if (isset($pmConfigs['ya_kassa_tax_'.$pmConfigs['tax_id']])) {
            $taxId = $pmConfigs['ya_kassa_tax_'.$pmConfigs['tax_id']];
        } else {
            $taxId = 1;
        }
        $builder->setTaxSystemCode($taxId);
        $builder->setReceiptEmail($order->email);

        $shipping = false;
        foreach ($shippingMethods as $tmp) {
            if ($tmp->shipping_id == $order->shipping_method_id) {
                $shipping = $tmp;
            }
        }

        foreach ($cart->products as $product) {
            if (isset($product['tax_id']) && isset($pmConfigs['ya_kassa_tax_'.$product['tax_id']])) {
                $taxId = $pmConfigs['ya_kassa_tax_'.$product['tax_id']];
                $builder->addReceiptItem($product['product_name'], $product['price'], $product['quantity'], $taxId);
            } else {
                $builder->addReceiptItem($product['product_name'], $product['price'], $product['quantity']);
            }
        }

        if ($order->shipping_method_id && $shipping) {
            if (isset($pmConfigs['ya_kassa_tax_'.$shipping->shipping_tax_id])) {
                $taxId = $pmConfigs['ya_kassa_tax_'.$shipping->shipping_tax_id];
                $builder->addReceiptShipping($shipping->name, $shipping->shipping_stand_price, $taxId);
            } else {
                $builder->addReceiptShipping($shipping->name, $shipping->shipping_stand_price);
            }
        }
    }

    /**
     * Инициализирует параметры для обработки процессором заказа из URL запроса при возврате на сайт
     * @param array $pmConfigs Настройки модуля оплаты
     * @return array Массив параметров, который будет использоваться в процессоре заказа
     */
    public function getUrlParams($pmConfigs)
    {
        $this->mode = $this->getMode($pmConfigs);
        $params = array();
        if ($this->mode == self::MODE_KASSA) {
            echo '0';
            if (!isset($_GET['order_id'])) {
                echo '1';
                $source = file_get_contents('php://input');
                if (empty($source)) {
                    header('HTTP/1.1 400 Body is empty');
                    die();
                }
                $json = json_decode($source, true);
                if (empty($json)) {
                    header('HTTP/1.1 400 Invalid body');
                    die();
                }
                $notification = new \YaMoney\Model\Notification\NotificationWaitingForCapture($json);
                $params['order_id'] = $notification->getObject()->getMetadata()->offsetGet('order_id');
                $params['hash'] = '';
                $params['checkHash'] = false;
                $params['checkReturnParams'] = false;

                $payment = $this->capturePayment($notification->getObject(), $pmConfigs);
                if ($payment === null) {
                    header('HTTP/1.1 404 Payment not exists');
                    die();
                } elseif ($payment->getStatus() !== \YaMoney\Model\PaymentStatus::SUCCEEDED) {
                    header('HTTP/1.1 401 Payment not exists');
                    die();
                }
                echo '{"success":true,"payment_status":"'.$payment->getStatus().'"}';
                die();

            } else {
                $params['order_id'] = (int)$_GET['order_id'];
                $paymentId = $this->getOrderModel()->getPaymentIdByOrderId($params['order_id']);
                if (empty($paymentId)) {
                    header('HTTP/1.1 404 Order not exists');
                    die();
                }
                $payment = $this->fetchPayment($paymentId, $pmConfigs);
                if ($payment === null) {
                    header('HTTP/1.1 404 Order not exists');
                    die();
                }
                if (!$payment->getPaid()) {
                    $redirectUrl = JRoute::_(JURI::root().'index.php?option=com_jshopping&controller=checkout&task=step3');
                    $app = JFactory::getApplication();
                    $app->redirect($redirectUrl);
                }
                if ($payment->getStatus() === \YaMoney\Model\PaymentStatus::WAITING_FOR_CAPTURE) {
                    $this->capturePayment($payment, $pmConfigs);
                }
            }
        } else {
            $params['order_id'] = (int)$_POST['label'];
            $params['hash'] = "";
            $params['checkHash'] = 0;
        }
        return $params;
    }

    private function capturePayment($notificationPayment, $pmConfigs, $fetchPayment = true)
    {
        if ($fetchPayment) {
            $payment = $this->fetchPayment($notificationPayment->getId(), $pmConfigs);
        } else {
            $payment = $notificationPayment;
        }
        if ($payment->getStatus() !== \YaMoney\Model\PaymentStatus::WAITING_FOR_CAPTURE) {
            return $payment;
        }

        $builder = \YaMoney\Request\Payments\Payment\CreateCaptureRequest::builder();
        $builder->setAmount($payment->getAmount());
        $request = $builder->build();

        $tries = 0;
        do {
            $response = $this->getApiClient($pmConfigs)->capturePayment($request, $payment->getId());
            if ($response === null) {
                $tries++;
                if ($tries > 3) {
                    break;
                }
                sleep(2);
            }
        } while ($response === null);

        return $response;
    }

    private function fetchPayment($paymentId, $pmConfigs)
    {
        return $this->getApiClient($pmConfigs)->getPaymentInfo($paymentId);
    }

    private $client;

    private function getApiClient($pmConfigs)
    {
        if ($this->client === null) {
            $this->client = new \YaMoney\Client\YandexMoneyApi();
            $this->client->setAuth($pmConfigs['shop_id'], $pmConfigs['shop_password']);
            $this->client->setLogger($this);
        }
        return $this->client;
    }
    
    private function fixOrderTotal($order)
    {
        $total = $order->order_total;
        if ($order->currency_code_iso == 'HUF') {
            $total = round($total);
        } else {
            $total = number_format($total, 2, '.', '');
        }
        return $total;
    }

    private function getMode($paymentConfig)
    {
        if ($this->mode == -1) {
            $this->mode = self::MODE_OFF;
            if ($paymentConfig['kassamode'] == '1') {
                $this->mode = self::MODE_KASSA;
                $this->ym_password = $paymentConfig['shoppassword'];
            } elseif ($paymentConfig['moneymode'] == '1') {
                $this->mode = self::MODE_MONEY;
                $this->ym_password = $paymentConfig['password'];
            } elseif ($paymentConfig['paymentsmode'] == '1') {
                $this->mode = self::MODE_PAYMENTS;
            }
        }
        return $this->mode;
    }

    /**
     * @param string $tpl
     * @param jshopOrder $order
     * @return string
     */
    private function parseTemplate($tpl, $order)
    {
        $replace = array();
        foreach ($order as $property => $value) {
            $replace['%' . $property . '%'] = $value;
        }
        return strtr($tpl, $replace);
    }

    /**
     * @param jshopOrder $order
     * @param int $endStatus
     * @return int
     */
    private function finishOrder($order, $endStatus)
    {
        $act = 'finish';
        $payment_method = 'pm_yandex_money';
        $no_lang = '1';

        if ($this->joomlaVersion === 2) {
            // joomla 2.x order finish
            $jshopConfig = JSFactory::getConfig();

            /** @var jshopCheckout $checkout */
            $checkout = JSFactory::getModel('checkout', 'jshop');

            $order->order_created = 1;
            $order->order_status = $endStatus;
            $order->store();
            if ($jshopConfig->send_order_email){
                $checkout->sendOrderEmail($order->order_id);
            }
            if ($jshopConfig->order_stock_removed_only_paid_status){
                $product_stock_removed = (in_array($endStatus, $jshopConfig->payment_status_enable_download_sale_file));
            } else {
                $product_stock_removed = 1;
            }
            if ($product_stock_removed){
                $order->changeProductQTYinStock("-");
            }
            $checkout->changeStatusOrder($order->order_id, $endStatus, 0);

            $checkout->deleteSession();
        } else {
            // joomla 3.x order finish
            /** @var jshopCheckoutBuy $checkout */
            $checkout = JSFactory::getModel('checkoutBuy', 'jshop');

            $checkout->saveToLogPaymentData();
            $checkout->setSendEndForm(0);

            $checkout->setAct($act);
            $checkout->setPaymentMethodClass($payment_method);
            $checkout->setNoLang($no_lang);
            $checkout->loadUrlParams();
            $checkout->setOrderId($order->order_id);

            $codebuy = $checkout->buy();
            if ($codebuy == 0) {
                JError::raiseWarning('', $checkout->getError());
                return 0;
            }

            /** @var jshopCheckoutFinish $checkout */
            $checkout = JSFactory::getModel('checkoutFinish', 'jshop');
            $order_id = $checkout->getEndOrderId();
            $text = $checkout->getFinishStaticText();
            if ($order_id) {
                $checkout->paymentComplete($order_id, $text);
            }
            $checkout->clearAllDataCheckout();
        }
    }

    public function log($level, $message, $context = array())
    {
        $fileName = realpath(dirname(__FILE__) . DS . '..' . DS . '..') . DS . 'log' . DS . 'pm_yandex_money.log';
        $fd = @fopen($fileName, 'a');
        if ($fd) {
            flock($fd, LOCK_EX);
            fwrite($fd, date(DATE_ATOM) . ' [' . $level . '] ' . $message . "\r\n");
            flock($fd, LOCK_UN);
            fclose($fd);
        }
    }

    /**
     * @return \YandexMoney\Model\OrderModel
     */
    private function getOrderModel()
    {
        if ($this->orderModel === null) {
            $this->orderModel = new \YandexMoney\Model\OrderModel();
        }
        return $this->orderModel;
    }
}
