<?php

namespace YooMoney\Helpers;

use YooKassa\Model\NotificationEventType;
use YooKassa\Model\PaymentStatus;
use YooKassa\Model\PaymentMethodType;
use YooMoney\Model\KassaPaymentMethod;

class TransactionHelper
{
    const CANCELED_STATUS_ID = 3;
    const REFUNDED_STATUS_ID = 4;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var ReceiptHelper
     */
    private $receiptHelper;

    /**
     * @var YoomoneyNotificationHelper
     */
    private $yooNotificationHelper;

    public function __construct()
    {
        $this->logger = new Logger();
        $this->yooNotificationHelper = new YoomoneyNotificationHelper();
        $this->orderHelper = new OrderHelper();
        $this->receiptHelper = new ReceiptHelper();
    }

    public function processNotification($kassa, $pmConfigs, $order)
    {
        $notificationObj = $this->yooNotificationHelper->getNotificationObject();
        $paymentId = $notificationObj->getObject()->getId();

        if ($notificationObj->getEvent() === NotificationEventType::REFUND_SUCCEEDED) {
            $paymentId = $notificationObj->getObject()->getPaymentId();
        }

        $payment = $kassa->fetchPayment($paymentId);
        if (!$payment) {
            $this->logger->log('debug', 'Notification error: payment not exist');
            header('HTTP/1.1 404 Payment not exists');
            die();
        }

        if (
            $notificationObj->getEvent() === NotificationEventType::PAYMENT_SUCCEEDED
            && $payment->getStatus() === PaymentStatus::SUCCEEDED
        ) {
            $this->processSucceedNotification($pmConfigs, $order, $payment, $kassa);
            return true;
        }

        if (
            $notificationObj->getEvent() === NotificationEventType::PAYMENT_WAITING_FOR_CAPTURE
            && $payment->getStatus() === PaymentStatus::WAITING_FOR_CAPTURE
        ) {
            $this->processWaitingForCaptureNtfctn($pmConfigs, $order, $payment, $kassa, $notificationObj);
            return true;
        }

        if (
            $notificationObj->getEvent() === NotificationEventType::PAYMENT_CANCELED
            && $payment->getStatus() === PaymentStatus::CANCELED
            && $kassa->isEnableHoldMode()
        ) {
            $this->processCanceledHoldPaymentNtfctn($pmConfigs, $order, $payment);
            return true;
        }

        if (
            $notificationObj->getEvent() === NotificationEventType::PAYMENT_CANCELED
            && $payment->getStatus() === PaymentStatus::CANCELED
            && !$kassa->isEnableHoldMode()
        ) {
            $this->logger->log('info', 'Canceled payment ' . $payment->getId());
            $this->processCanceledPaymentNtfctn($order);
            return true;
        }

        if (
            $notificationObj->getEvent() === NotificationEventType::REFUND_SUCCEEDED
            && $payment->getStatus() === PaymentStatus::SUCCEEDED
        ) {
            $this->logger->log('info', 'Canceled payment ' . $payment->getId());
            $this->processRefundNtfctn($order);
            return true;
        }

        if (
            $notificationObj->getEvent() === NotificationEventType::DEAL_CLOSED
            || $notificationObj->getEvent() === NotificationEventType::PAYOUT_CANCELED
            || $notificationObj->getEvent() === NotificationEventType::PAYOUT_SUCCEEDED
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param $pmConfigs
     * @param $order
     * @param $payment
     * @param KassaPaymentMethod $kassa
     */
    private function processSucceedNotification($pmConfigs, $order, $payment, $kassa)
    {
        $jshopConfig = \JSFactory::getConfig();

        /** @var jshopCheckout $checkout */
        $checkout             = \JSFactory::getModel('checkout', 'jshop');
        $endStatus            = $pmConfigs['transaction_end_status'];
        $order->order_created = 1;
        $order->order_status  = $endStatus;
        $order->store();

        try {
            if ($jshopConfig->send_order_email) {
                $checkout->sendOrderEmail($order->order_id);
            }
        } catch (\Exception $exception) {
            $this->logger->log('debug', $exception->getMessage());
        }

        $product_stock_removed = true;
        if ($jshopConfig->order_stock_removed_only_paid_status) {
            $product_stock_removed = in_array($endStatus, $jshopConfig->payment_status_enable_download_sale_file);
        }

        if ($product_stock_removed) {
            $order->changeProductQTYinStock("-");
        }

        $this->receiptHelper->sendSecondReceipt($order->order_id, $kassa, $endStatus);

        $checkout->changeStatusOrder($order->order_id, $endStatus, 0);

        $paymentMethod = $payment->getPaymentMethod();
        if($paymentMethod->getType() == PaymentMethodType::B2B_SBERBANK) {
            $message = $this->getSuccessOrderHistoryMessageForB2B($paymentMethod);
        }

        if (!empty($message)) {
            $this->orderHelper->saveOrderHistory($order, $message);
        }
    }

    /**
     * @param $paymentMethod
     * @return string
     */
    private function getSuccessOrderHistoryMessageForB2B($paymentMethod)
    {
        $payerBankDetails = $paymentMethod->getPayerBankDetails();

        $fields  = array(
            'fullName'   => 'Полное наименование организации',
            'shortName'  => 'Сокращенное наименование организации',
            'adress'     => 'Адрес организации',
            'inn'        => 'ИНН организации',
            'kpp'        => 'КПП организации',
            'bankName'   => 'Наименование банка организации',
            'bankBranch' => 'Отделение банка организации',
            'bankBik'    => 'БИК банка организации',
            'account'    => 'Номер счета организации',
        );
        $message = '';
        foreach ($fields as $field => $caption) {
            // TODO: $requestData - ???
            if (isset($requestData[$field])) {
                $message .= $caption.': '.$payerBankDetails->offsetGet($field).'\n';
            }
        }
        return $message;
    }

    private function processWaitingForCaptureNtfctn($pmConfigs, $order, $payment, $kassa, $notificationObj)
    {
        if ($kassa->isEnableHoldMode()) {
            $this->logger->log('info', 'Hold payment '.$payment->getId());

            /** @var jshopCheckout $checkout */
            $checkout             = \JSFactory::getModel('checkout', 'jshop');
            $onHoldStatus         = $pmConfigs['yookassa_hold_mode_on_hold_status'];
            $order->order_created = 1;
            $order->order_status  = $onHoldStatus;
            $order->store();
            $checkout->changeStatusOrder($order->order_id, $onHoldStatus, 0);
            $this->orderHelper->saveOrderHistory(
                $order,
                sprintf(_JSHOP_YOO_HOLD_MODE_COMMENT_ON_HOLD,
                $payment->getExpiresAt()->format('d.m.Y H:i'))
            );

        } else {
            $payment = $kassa->capturePayment($notificationObj->getObject());
            if (!$payment || $payment->getStatus() !== PaymentStatus::SUCCEEDED) {
                $this->logger->log('debug', 'Capture payment error');
                header('HTTP/1.1 400 Bad Request');
            }
        }
    }

    private function processCanceledHoldPaymentNtfctn($pmConfigs, $order, $payment)
    {
        $this->logger->log('info', 'Canceled hold payment ' . $payment->getId());

        /** @var jshopCheckout $checkout */
        $checkout             = \JSFactory::getModel('checkout', 'jshop');
        $cancelHoldStatus         = $pmConfigs['yookassa_hold_mode_cancel_status'];
        $order->order_created = 1;
        $order->order_status  = $cancelHoldStatus;
        $order->store();
        $checkout->changeStatusOrder($order->order_id, $cancelHoldStatus, 0);
    }

    private function processCanceledPaymentNtfctn($order)
    {
        /** @var jshopCheckout $checkout */
        $checkout             = \JSFactory::getModel('checkout', 'jshop');

        $order->order_created = 1;
        $order->order_status  = self::CANCELED_STATUS_ID;
        $order->store();
        $checkout->changeStatusOrder($order->order_id, self::CANCELED_STATUS_ID, 0);
    }

    private function processRefundNtfctn($order)
    {
        /** @var jshopCheckout $checkout */
        $checkout             = \JSFactory::getModel('checkout', 'jshop');

        $order->order_created = 1;
        $order->order_status  = self::REFUNDED_STATUS_ID;
        $order->store();
        $checkout->changeStatusOrder($order->order_id, self::REFUNDED_STATUS_ID, 0);
    }
}