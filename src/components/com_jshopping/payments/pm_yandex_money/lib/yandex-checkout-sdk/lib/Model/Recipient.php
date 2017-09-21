<?php

namespace YaMoney\Model;

use YaMoney\Common\AbstractObject;
use YaMoney\Common\Exceptions\EmptyPropertyValueException;
use YaMoney\Common\Exceptions\InvalidPropertyValueTypeException;
use YaMoney\Helpers\TypeCast;

/**
 * Recipient - Получатель платежа
 *
 * @property string $shopId Идентификатор магазина
 * @property string $productGroupId Идентификатор товара
 */
class Recipient extends AbstractObject implements RecipientInterface
{
    /**
     * @var string Идентификатор магазина
     */
    private $_shopId;

    /**
     * @var string Идентификатор товара
     */
    private $_productGroupId;

    /**
     * Возвращает идентификатор магазина
     * @return string Идентификатор магазина
     */
    public function getShopId()
    {
        return $this->_shopId;
    }

    /**
     * Устанавливает идентификатор магазина
     * @param string $value Идентификатор магазина
     * @throws EmptyPropertyValueException Выбрасывается если было передано пустое значение
     * @throws InvalidPropertyValueTypeException Выбрасывается если было передано не строковое значение
     */
    public function setShopId($value)
    {
        if ($value === null || $value === '') {
            throw new EmptyPropertyValueException('Empty shopId value in Recipient', 0, 'Recipient.shopId');
        } elseif (TypeCast::canCastToString($value)) {
            $this->_shopId = (string)$value;
        } else {
            throw new InvalidPropertyValueTypeException(
                'Invalid shopId value type in Recipient', 0, 'Recipient.shopId', $value
            );
        }
    }

    /**
     * Возвращает идентификатор товара
     * @return string Идентификатор товара
     */
    public function getProductGroupId()
    {
        return $this->_productGroupId;
    }

    /**
     * Устанавливает идентификатор товара
     * @param string $value Идентификатор товара
     * @throws EmptyPropertyValueException Выбрасывается если было передано пустое значение
     * @throws InvalidPropertyValueTypeException Выбрасывается если было передано не строковое значение
     */
    public function setProductGroupId($value)
    {
        if ($value === null || $value === '') {
            throw new EmptyPropertyValueException(
                'Empty productGroupId value in Recipient', 0, 'Recipient.productGroupId'
            );
        } elseif (TypeCast::canCastToString($value)) {
            $this->_productGroupId = (string)$value;
        } else {
            throw new InvalidPropertyValueTypeException(
                'Invalid productGroupId value type in Recipient', 0, 'Recipient.productGroupId', $value
            );
        }
    }
}
