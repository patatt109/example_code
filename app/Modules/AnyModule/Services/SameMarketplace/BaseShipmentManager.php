<?php

declare(strict_types=1);

namespace Modules\AnyModule\Services\SameMarketplace;

use Libs\Traits\Logger;
use Modules\AnyModule\Models\SameMarketplace\Shipment;
use Modules\AnyModule\Models\SameMarketplace\ShipmentItem;
use Modules\AnyModule\Repository\SameMarketplace\ShipmentItemRepository;
use Modules\AnyModule\Repository\SameMarketplace\ShipmentRepository;
use Phact\Helpers\ClassNames;
use Phact\Helpers\SmartProperties;
use Psr\Log\LoggerInterface;

abstract class BaseShipmentManager
{
    use SmartProperties, ClassNames, Logger;

    protected int $schemeId;
    protected ShipmentRepository $shipmentRepository;
    protected ShipmentItemRepository $shipmentItemRepository;

    public function __construct(
        int $schemeId,
        ShipmentRepository $shipmentRepository,
        ShipmentItemRepository $shipmentItemRepository,
        LoggerInterface $logger
    ) {
        $this->schemeId = $schemeId;
        $this->shipmentRepository = $shipmentRepository;
        $this->shipmentItemRepository = $shipmentItemRepository;
        $this->logger = $logger;
    }

    /**
     * @throws \Exception
     */
    public function saveOrUpdateShipments(array $orderList): void
    {
        foreach ($orderList as $order) {
            $order['scheme'] = $this->schemeId;
            $this->saveOrUpdateShipment($order);
        }
    }

    /**
     * @throws \Exception
     */
    public function saveOrUpdateShipment(array $shipmentData): void
    {
        if (empty($shipmentData['scheme'])) {
            $shipmentData['scheme'] = $this->schemeId;
        }
        $model = $this->shipmentRepository->findOneOrNew([
            'shipment_id' => $shipmentData['shipmentId'],
            'scheme' => $this->schemeId,
        ]);
        /** @var Shipment $model */
        $model = $this->shipmentRepository->populate($model, $shipmentData);
        $this->shipmentRepository->save($model);
        foreach ($shipmentData['items'] as $item) {
            if ($item['offer_id'] === 'delivery') {
                continue;
            }
            $item['shipment'] = $model;
            $this->saveOrUpdateShipmentItem($item);
        }
    }

    /**
     * @throws \Exception
     */
    public function saveOrUpdateShipmentItem(array $shipmentItemData): void
    {
        /** @var ShipmentItem $model */
        $model = $this->shipmentItemRepository->findOneOrNew([
            'shipment_id' => $shipmentItemData['shipment']->shipment_id,
            'item_index' => $shipmentItemData['itemIndex'],
        ]);
        $model = $this->shipmentItemRepository->populate($model, $shipmentItemData);
        $this->shipmentItemRepository->save($model);
    }

    public function findShipmentByShipmentId(string $shipmentId): ?Shipment
    {
        return $this->shipmentRepository->findByShipmentIdAndScheme($shipmentId, $this->schemeId);
    }

    public function findNewShipments(): array
    {
        return $this->shipmentRepository->findNewByScheme($this->schemeId);
    }

    public function findActualItemsByShipment(Shipment $shipment): array
    {
        return $this->shipmentItemRepository->findActualItemsByShipment($shipment);
    }

    public function findNewShipmentsWithOrderCode(): array
    {
        return $this->shipmentRepository->findNewShipmentsWithOrderCodeByScheme($this->schemeId);
    }

    public function setShipmentAttribute(Shipment $shipment, string $attributeName, $value): Shipment
    {
        return $this->shipmentRepository->setAttribute($shipment, $attributeName, $value);
    }

    public function saveShipment(Shipment $shipment): bool
    {
        return $this->shipmentRepository->save($shipment);
    }

    public function setAndSaveShipmentAttribute(Shipment $shipment, string $attributeName, $value): bool
    {
        /** @var Shipment $shipment */
        $shipment = $this->shipmentRepository->setAttribute($shipment, $attributeName, $value);
        return $this->shipmentRepository->save($shipment);
    }

    public function isChangedShipmentStatus(Shipment $shipment): bool
    {
        return $this->shipmentRepository->isChangedAttribute($shipment, 'status');
    }

    public static function getServiceName(): string
    {
        return 'SAME_MARKETPLACE_' . strtoupper(static::classNameShort());
    }
}