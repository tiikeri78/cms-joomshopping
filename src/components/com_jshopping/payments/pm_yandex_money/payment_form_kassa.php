<?php

/**
 * @package JoomShopping for Joomla!
 * @subpackage payment
 * @author YandexMoney
 * @copyright Copyright (C) 2012-2017 YandexMoney. All rights reserved.
 */

defined('_JEXEC') or die('Restricted access');

if ($pmConfigs['paymode'] != '1') : ?>
<table class="radio" style="margin-left: 50px;">
    <tbody>
    <?php
        $listMethods = array(
            \YaMoney\Model\PaymentMethodType::YANDEX_MONEY   => 'PC',
            \YaMoney\Model\PaymentMethodType::BANK_CARD      => 'AC',
            \YaMoney\Model\PaymentMethodType::CASH           => 'GP',
            \YaMoney\Model\PaymentMethodType::MOBILE_BALANCE => 'MC',
            \YaMoney\Model\PaymentMethodType::WEBMONEY       => 'WM',
            \YaMoney\Model\PaymentMethodType::SBERBANK       => 'SB',
            \YaMoney\Model\PaymentMethodType::ALFABANK       => 'AB',
            \YaMoney\Model\PaymentMethodType::QIWI           => 'QW',
        );
        $num += 0;
        foreach ($listMethods as $long => $short) :
            if (isset($pmConfigs['method_' . $long]) && $pmConfigs['method_' . $long] == '1') :
                $num += 1; ?>
                <tr class="highlight">
                    <td>
                        <input type="radio" name="params[pm_yandex_money][payment_type]" value="<?php echo $long; ?>"
                            <?php echo ($num == 1 ? ' checked' : ''); ?> id="ym<?php echo $num; ?>" />
                    </td>
                    <td><img src="<?php echo JURI::root(); ?>components/com_jshopping/images/yandex_money/<?php echo strtolower($short); ?>.png">
                    </td>
                    <td>
                        <label for="ym<?php echo $num; ?>"><?php echo constant('_JSHOP_YM_METHOD_' . strtoupper($long) . '_DESCRIPTION'); ?></label>
                    </td>
                </tr>
                <?php if ($long === \YaMoney\Model\PaymentMethodType::ALFABANK) : ?>
                <tr class="highlight additional-field" id="ym-alfa-login-block" style="display:none;">
                    <td colspan="3">
                        <label for="ym-alfa-login">Логин в Альфа-Клике</label>
                        <input type="text" name="params[pm_yandex_money][alfaLogin]" value="" id="ym-alfa-login" />
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($long === \YaMoney\Model\PaymentMethodType::QIWI) : ?>
                <tr class="highlight additional-field" id="ym-qiwi-phone-block" style="display:none;">
                    <td colspan="3">
                        <label for="ym-qiwi-phone">Логин в Альфа-Клике</label>
                        <input type="text" name="params[pm_yandex_money][alfaLogin]" value="" id="ym-alfa-login" />
                    </td>
                </tr>
            <?php endif; ?>
            <?php endif;
        endforeach;
    ?>
    </tbody>
</table>
<?php else : ?>
    <input type="hidden" name="params[pm_yandex_money][payment_type]" value="" />
<?php endif; ?>
<script type="text/javascript">
function check_pm_yandexmoney() {
    jQuery('#payment_form').submit();
}
</script>