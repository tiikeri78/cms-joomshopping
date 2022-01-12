<?php

namespace YooMoney\Helpers;

class OrderHelper
{
    /**
     * @var JVersionDependenciesHelper
     */
    private $dependenciesHelper;

    public function __construct()
    {
        $this->dependenciesHelper = new JVersionDependenciesHelper();
    }

    public function saveOrderHistory($order, $comments)
    {
        $history                    = \JSFactory::getTable('orderHistory', 'jshop');
        $history->order_id          = $order->order_id;
        $history->order_status_id   = $order->order_status;
        $history->status_date_added = $this->dependenciesHelper->getJsDate();
        $history->customer_notify   = 0;
        $history->comments          = $comments;

        return $history->store();
    }
}