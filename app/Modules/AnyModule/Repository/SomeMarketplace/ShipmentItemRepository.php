<?php

declare(strict_types=1);

namespace Modules\AnyModule\Repository\SomeMarketplace;

use Libs\Repository\Base;
use Modules\AnyModule\Models\SomeMarketplace\Shipment;
use Modules\AnyModule\Models\SomeMarketplace\ShipmentItem;

class ShipmentItemRepository extends Base
{
    protected const MODEL_CLASS = ShipmentItem::class;

    public function findActualItemsByShipment(Shipment $shipment): array
    {
        return $shipment->items->filter(['is_deleted' => false])->order(['item_index'])->all();
    }
}