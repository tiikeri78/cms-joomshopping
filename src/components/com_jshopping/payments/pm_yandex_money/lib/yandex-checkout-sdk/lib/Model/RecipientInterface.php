<?php

namespace YaMoney\Model;

/**
 * Interface RecipientInterface
 *
 * @package YaMoney\Model
 *
 * @property-read string $shopId Идентификатор магазина
 * @property-read string $productGroupId Идентификатор товара
 */
interface RecipientInterface
{
    /**
     * @return string Идентификатор магазина
     */
    function getShopId();

    /**
     * @return string Идентификатор товара
     */
    function getProductGroupId();
}