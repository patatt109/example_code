<?php

declare(strict_types=1);

namespace Modules\AnyModule\Models\SameMarketplace;

use Libs\Traits\DataPopulate;
use Modules\Main\ModelFields\JsonField;
use Phact\Orm\Fields\BooleanField;
use Phact\Orm\Fields\CharField;
use Phact\Orm\Fields\DateTimeField;
use Phact\Orm\Fields\ForeignField;
use Phact\Orm\Fields\IntField;
use Phact\Orm\Model;
use Phact\Orm\Q;

class ShipmentItem extends Model
{
    use DataPopulate;

    public const STATUS_MERCHANT_CANCELED = 'MERCHANT_CANCELED'; // отмена Мерчантом
    public const STATUS_NEW = 'NEW'; // новый заказ
    public const STATUS_PENDING = 'PENDING'; // обработка заказа со стороны маркета (справочная информация)
    public const STATUS_PENDING_CONFIRMATION = 'PENDING_CONFIRMATION'; // обработка подтверждения со стороны маркета (справочная информация)
    public const STATUS_CONFIRMED = 'CONFIRMED'; // подтверждено Мерчантом
    public const STATUS_PENDING_PACKING = 'PENDING_PACKING'; // обработка сообщения о комплектации со стороны маркета (справочная информация)
    public const STATUS_PACKED = 'PACKED'; // скомплектовано Мерчантом
    public const STATUS_PENDING_SHIPPING = 'PENDING_SHIPPING'; // обработка сообщения об отгрузке со стороны маркета (справочная информация)
    public const STATUS_SHIPPED = 'SHIPPED'; // отгружено Мерчантом
    public const STATUS_PACKING_EXPIRED = 'PACKING_EXPIRED'; // просрочка комплетации
    public const STATUS_SHIPPING_EXPIRED = 'SHIPPING_EXPIRED'; // просрочка отгрузки для C&D
    public const STATUS_DELIVERED = 'DELIVERED'; // исполнение заказа
    public const STATUS_CUSTOMER_CANCELED = 'CUSTOMER_CANCELED'; // отмена покупателем

    public const SUBSTATUS_LATE_REJECT = 'LATE_REJECT'; // отменено мерчантом после подтверждения
    public const SUBSTATUS_CONFIRMATION_REJECT = 'CONFIRMATION_REJECT'; // отмена на этапе подтверждения
    public const SUBSTATUS_CONFIRMATION_EXPIRED = 'CONFIRMATION_EXPIRED'; // просрочка подтверждения
    public const SUBSTATUS_PACKING_EXPIRED = 'PACKING_EXPIRED'; // просрочка на этапе комплектации

    public static function getFields(): array
    {
        return [
            'item_index' => [
                'class' => CharField::class,
                'label' => 'Порядковый номер лота',
                'null' => true,
            ],
            'status' => [
                'class' => CharField::class,
                'label' => 'Статус лота',
                'null' => true,
                'choices' => self::getStatusChoices(),
            ],
            'sub_status' => [
                'class' => CharField::class,
                'label' => 'Детализация этапы отмены',
                'null' => true,
                'choices' => [
                    self::SUBSTATUS_LATE_REJECT => null,
                    self::SUBSTATUS_CONFIRMATION_REJECT => null,
                    self::SUBSTATUS_CONFIRMATION_EXPIRED => null,
                    self::SUBSTATUS_PACKING_EXPIRED => null,
                ],
            ],
            'price' => [
                'class' => IntField::class,
                'label' => 'Цена лота',
                'null' => true,
            ],
            'final_price' => [
                'class' => IntField::class,
                'label' => 'Цена лота с учетом скидки',
                'null' => true,
            ],
            'discounts' => [
                'class' => JsonField::class,
                'label' => 'Массив содержащий набор параметров содержащих информацию о скидке',
                'null' => true,
            ],
            'quantity' => [
                'class' => IntField::class,
                'label' => 'Количество (всегда единица, количество определяется в рамках объектов массива items)',
                'default' => 1,
            ],
            'offer_id' => [
                'class' => CharField::class,
                'label' => 'Артикул',
                'null' => true,
            ],
            'goods_id' => [
                'class' => CharField::class,
                'label' => 'Идентификатор карточки товара маркета',
                'null' => true,
            ],
            'goods_data' => [
                'class' => JsonField::class,
                'label' => 'Объект, содержащий информацию о карточке товара',
                'null' => true,
            ],
            'box_index' => [
                'class' => CharField::class,
                'label' => 'Грузовое место',
                'null' => true,
            ],
            'is_deleted' => [
                'class' => BooleanField::class,
                'default' => false,
            ],
            'shipment' => [
                'class' => ForeignField::class,
                'modelClass' => Shipment::class,
                'label' => 'ID заказа',
                'to' => 'shipment_id',
                'from' => 'shipment_id',
                'null' => true,
            ],
            'row_created_at' => [
                'class' => DateTimeField::class,
                'autoNowAdd' => true,
            ],
            'row_updated_at' => [
                'class' => DateTimeField::class,
                'null' => true,
            ],
        ];
    }

    public static function getStatusChoices(): array
    {
        return [
            self::STATUS_MERCHANT_CANCELED => 'cancel-other',
            self::STATUS_NEW => 'new-same-marketplace',
            self::STATUS_PENDING => null,
            self::STATUS_PENDING_CONFIRMATION => null,
            self::STATUS_CONFIRMED => 'send-to-assembling',
            self::STATUS_PENDING_PACKING => null,
            self::STATUS_PACKED => 'assembling-complete',
            self::STATUS_PENDING_SHIPPING => null,
            self::STATUS_SHIPPED => 'send-to-delivery',
            self::STATUS_PACKING_EXPIRED => '',
            self::STATUS_SHIPPING_EXPIRED => '',
            self::STATUS_DELIVERED => 'complete',
            self::STATUS_CUSTOMER_CANCELED => 'cancel-other',
        ];
    }

    public static function getConditionFinished(): array
    {
        return [
            Q::orQ(
                ['status' => self::STATUS_MERCHANT_CANCELED],
                ['status' => self::STATUS_CUSTOMER_CANCELED],
                ['status' => self::STATUS_DELIVERED],
            ),
        ];
    }

    public static function getTableName()
    {
        $class = static::class;
        if (!isset(self::$_tableNames[$class])) {
            self::$_tableNames[$class] = 'svc_same_marketplace_shipment_item';
        }
        return self::$_tableNames[$class];
    }
}