<?php

namespace YaMoney\Request;

class PaymentOptionsRequestSerializer
{
    public function serialize(PaymentOptionsRequestInterface $request)
    {
        $result = array(
            'shop_id' => $request->getShopId(),
        );
        if ($request->hasProductGroupId()) {
            $result['product_group_id'] = $request->getProductGroupId();
        }
        if ($request->hasAmount()) {
            $result['amount'] = $request->getAmount();
        }
        if ($request->hasCurrency()) {
            $result['currency'] = $request->getCurrency();
        }
        if ($request->hasConfirmationType()) {
            $result['confirmation_types'] = $request->getConfirmationType();
        }
        return $result;
    }
}