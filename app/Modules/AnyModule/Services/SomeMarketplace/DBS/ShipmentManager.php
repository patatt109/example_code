<?php

declare(strict_types=1);

namespace Modules\AnyModule\Services\SomeMarketplace\DBS;

use Modules\AnyModule\Services\SomeMarketplace\BaseShipmentManager;

class ShipmentManager extends BaseShipmentManager
{
    public static function getServiceName(): string
    {
        return 'SOME_MARKETPLACE_DBS_' . strtoupper(static::classNameShort());
    }

    public function findNonFinishedShipments(): array
    {
        return $this->shipmentRepository->findNonFinishedByScheme($this->schemeId);
    }
}