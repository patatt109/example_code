<?php

declare(strict_types=1);

namespace Modules\AnyModule\Repository\SameMarketplace;

use Libs\Repository\Base;
use Modules\AnyModule\Models\SameMarketplace\Shipment;
use Modules\AnyModule\Models\SameMarketplace\ShipmentItem;
use Phact\Orm\Q;

class ShipmentRepository extends Base
{
    protected const MODEL_CLASS = Shipment::class;

    /**
     * @param int $scheme
     * @return Shipment[]
     */
    public function findNewByScheme(int $scheme): array
    {
        return $this->findBy(
            [
                'status' => ShipmentItem::STATUS_NEW,
                'scheme' => $scheme,
                'order_code__isnull' => true,
            ],
            ['shipment_id']
        );
    }

    /**
     * @param int $scheme
     * @return Shipment[]
     */
    public function findNewShipmentsWithOrderCodeByScheme(int $scheme): array
    {
        return $this->findBy(
            [
                'status' => ShipmentItem::STATUS_NEW,
                'scheme' => $scheme,
                'order_code__isnull' => false,
            ],
            ['shipment_id']
        );
    }

    public function findByShipmentIdAndScheme(string $shipmentId, int $scheme): ?Shipment
    {
        return $this->findOneBy([
            'shipment_id' => $shipmentId,
            'scheme' => $scheme,
        ]);
    }

    public function findNonFinishedByScheme(int $scheme): array
    {
        return $this->findBy(
            [
                Q::notQ(Shipment::getConditionFinished()),
                'scheme' => $scheme,
                'order_code__isnull' => false,
            ],
            ['shipment_id']
        );
    }
}