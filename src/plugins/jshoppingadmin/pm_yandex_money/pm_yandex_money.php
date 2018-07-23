<?php

defined('_JEXEC') or die('Restricted access');

include dirname(__FILE__).'/../../../components/com_jshopping/payments/pm_yandex_money/pm_yandex_money.php';

class plgJshoppingAdminPm_yandex_money extends JPlugin
{
    public function onBeforeChangeOrderStatusAdmin($order_id, &$status)
    {
        $paymentmethod = JSFactory::getTable('paymentmethod', 'jshop');

        $all_payment_methods = $paymentmethod->getAllPaymentMethods();
        $pm_kassa            = null;
        foreach ($all_payment_methods as $pm) {
            $scriptname = ($pm->scriptname != '') ? $pm->scriptname : $pm->payment_class;
            if ($scriptname !== 'pm_yandex_money') {
                continue;
            }
            $pm_kassa = $pm;
            break;
        }
        if (!$pm_kassa) {
            return;
        }

        $paymentmethod->load($pm_kassa->payment_id);
        $parseString = new parseString($pm_kassa->payment_params);
        $pmconfig    = $parseString->parseStringToParams();

        $pm_yandex_money = new pm_yandex_money();
        $kassa = $pm_yandex_money->getKassaPaymentMethod($pmconfig);
        if (!$kassa->isEnableHoldMode()) {
            return;
        }

        $onHoldStatus   = $pmconfig['ya_kassa_hold_mode_on_hold_status'];
        $cancelStatus   = $pmconfig['ya_kassa_hold_mode_cancel_status'];
        $completeStatus = $pmconfig['kassa_transaction_end_status'];
        if (!in_array($status, array($completeStatus, $cancelStatus))) {
            return;
        }

        /** @var jshopOrder $order */
        $order = JSFactory::getTable('order', 'jshop');
        $order->load($order_id);

        if ($order->payment_method_id !== $pm_kassa->payment_id) {
            return;
        }

        if ($order->order_status !== $onHoldStatus) {
            return;
        }

        if ($status === $completeStatus) {
            $status = $onHoldStatus;
        }

        if ($status === $cancelStatus) {
            $status = $onHoldStatus;
        }
    }
}
