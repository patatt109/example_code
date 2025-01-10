<?php

declare(strict_types=1);

namespace Modules\AnyModule\Models\SameMarketplace;

use Libs\Traits\DataPopulate;
use Modules\Main\ModelFields\JsonField;
use Phact\Orm\Fields\BooleanField;
use Phact\Orm\Fields\CharField;
use Phact\Orm\Fields\DateTimeField;
use Phact\Orm\Fields\HasManyField;
use Phact\Orm\Fields\IntField;
use Phact\Orm\Model;
use Phact\Orm\QuerySet;

/**
 * @property QuerySet $items
 */
class Shipment extends Model
{
    use DataPopulate;

    public const SCHEME_DBS = 38059;
    public const SCHEME_FBS = 106486;

    public static function getFields(): array
    {
        return [
            'shipment_id' => [
                'class' => CharField::class,
                'label' => 'Номер отправления',
            ],
            'order_code' => [
                'class' => CharField::class,
                'label' => 'Номер заказа продавца',
                'null' => true,
            ],
            'confirmed_time_limit' => [
                'class' => DateTimeField::class,
                'label' => 'Крайняя дата подтверждения мерчантом',
                'null' => true,
            ],
            'packing_time_limit' => [
                'class' => DateTimeField::class,
                'label' => 'Крайняя дата комплектации',
                'null' => true,
            ],
            'shipping_time_limit' => [
                'class' => DateTimeField::class,
                'label' => 'Крайняя дата доставки',
                'null' => true,
            ],
            'shipment_date_from' => [
                'class' => DateTimeField::class,
                'label' => 'Отгрузка с',
                'null' => true,
            ],
            'shipment_date_to' => [
                'class' => DateTimeField::class,
                'label' => 'Отгрузка по',
                'null' => true,
            ],
            'packing_date' => [
                'class' => DateTimeField::class,
                'label' => 'Дата до которой должна быть произведена комплектация',
                'null' => true,
            ],
            'reserve_expiration_date' => [
                'class' => DateTimeField::class,
                'label' => 'Дата истечения срока резерва',
                'null' => true,
            ],
            'delivery_id' => [
                'class' => CharField::class,
                'label' => 'Номер доставки',
                'null' => true,
            ],
            'shipment_date_shift' => [
                'class' => BooleanField::class,
                'label' => 'Изменение даты отгрузки',
                'null' => true,
            ],
            'shipment_is_changeable' => [
                'class' => BooleanField::class,
                'label' => 'Перекомплектация',
                'null' => true,
            ],
            'customer_full_name' => [
                'class' => CharField::class,
                'label' => 'Имя клиента(вводится на чекауте)',
                'null' => true,
            ],
            'customer_address' => [
                'class' => CharField::class,
                'label' => 'Адрес торговой точки',
                'null' => true,
            ],
            'shipping_point' => [
                'class' => CharField::class,
                'label' => 'Идентификатор магазине по системе продава',
                'null' => true,
            ],
            'creation_date' => [
                'class' => DateTimeField::class,
                'label' => 'Дата создания отправления',
                'null' => true,
            ],
            'delivery_date' => [
                'class' => DateTimeField::class,
                'label' => 'Дата доставки до покупателя',
                'null' => true,
            ],
            'delivery_date_from' => [
                'class' => DateTimeField::class,
                'label' => 'Дата и время с которой клиент может выкупить товар',
                'null' => true,
            ],
            'delivery_date_to' => [
                'class' => DateTimeField::class,
                'label' => 'Дата и время до которой клиент должен выкупить товар',
                'null' => true,
            ],
            'delivery_method_id' => [
                'class' => CharField::class,
                'label' => 'Схема доставки',
                'null' => true,
            ],
            'service_scheme' => [
                'class' => CharField::class,
                'label' => 'Сервисная схема',
                'null' => true,
            ],
            'deposited_amount' => [
                'class' => IntField::class,
                'label' => 'Сумма, оплаченная Покупателем за товары на сайте маркета (если 0, то оплату нужно принять при выдаче, если = значению finalPrice, то дополнительно оплату при выдаче взимать не требуется)',
                'null' => true,
                ],
            'status' => [
                'class' => CharField::class,
                'label' => 'Общий статус отправления. Соответствует статусу лота с наименьшим из статусов. При работе с заказами рекомендуется ориентироваться на статус каждого отдельного лота, а не на данное поле.',
                'null' => true,
                'choices' => ShipmentItem::getStatusChoices(),
            ],
            'customer' => [
                'class' => JsonField::class,
                'label' => 'Информация о профиле покупателя',
                'null' => true,
            ],
            'scheme' => [
                'class' => IntField::class,
                'label' => 'Схема работы',
                'choices' => [
                    self::SCHEME_DBS => 'dbs',
                    self::SCHEME_FBS => 'fbs',
                ],
            ],
            'items' => [
                'class' => HasManyField::class,
                'modelClass' => ShipmentItem::class,
                'to' => 'shipment_id',
                'from' => 'shipment_id',
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

    public function getActualItems(): array
    {
        return $this->items->filter(['is_deleted' => false])->order(['item_index'])->all();
    }

    public static function getTableName()
    {
        $class = static::class;
        if (!isset(self::$_tableNames[$class])) {
            self::$_tableNames[$class] = 'svc_same_marketplace_shipment';
        }
        return self::$_tableNames[$class];
    }

    public function getCrmStatus(): ?string
    {
        return $this->status__display;
    }

    public static function getConditionFinished(): array
    {
        return ShipmentItem::getConditionFinished();
    }

    public function getSchemeName(): string
    {
        return $this->scheme__display;
    }
}