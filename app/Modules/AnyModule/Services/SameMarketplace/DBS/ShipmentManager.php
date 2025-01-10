<?php

declare(strict_types=1);

namespace Modules\AnyModule\Services\SameMarketplace\DBS;

use Modules\AnyModule\Services\SameMarketplace\BaseShipmentManager;

class ShipmentManager extends BaseShipmentManager
{
    public static function getServiceName(): string
    {
        return 'SAME_MARKETPLACE_DBS_' . strtoupper(static::classNameShort());
    }

    public function findNonFinishedShipments(): array
    {
        return $this->shipmentRepository->findNonFinishedByScheme($this->schemeId);
    }
}