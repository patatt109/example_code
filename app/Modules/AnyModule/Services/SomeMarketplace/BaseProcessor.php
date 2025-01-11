<?php

declare(strict_types=1);

namespace Modules\AnyModule\Services\SomeMarketplace;

use GuzzleHttp\Exception\GuzzleException;
use Libs\Services\SomeMarketplace\BaseApiClient;
use Libs\Traits\Logger;
use Modules\AnyModule\Models\SomeMarketplace\Shipment;
use Phact\Helpers\ClassNames;
use Phact\Helpers\SmartProperties;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\InvalidArgumentException;

abstract class BaseProcessor
{
    use SmartProperties, ClassNames, Logger;

    protected BaseApiClient $client;
    protected BaseShipmentManager $shipmentManager;
    protected BaseOrderManager $orderManager;

    public function __construct(
        BaseApiClient $client,
        BaseShipmentManager $shipmentManager,
        BaseOrderManager $orderManager,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->shipmentManager = $shipmentManager;
        $this->orderManager = $orderManager;
        $this->logger = $logger;
    }

    abstract public function processShipments(): void;

    /**
     * @throws GuzzleException
     * @throws \Exception
     */
    public function checkAndSaveNewShipments(): void
    {
        $orderIdList = $this->client->getNewOrderIdList();
        if (!empty($orderIdList)) {
            $orderList = $this->client->getOrderList($orderIdList);
            $this->shipmentManager->saveOrUpdateShipments($orderList);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function createOrders(): void
    {
        $shipmentList = $this->shipmentManager->findNewShipments();
        foreach ($shipmentList as $shipment) {
            $this->createOrder($shipment);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function createOrder(Shipment $shipment): void
    {
        $order = $this->orderManager->create($shipment);
        $saved = $this->shipmentManager->setAndSaveShipmentAttribute($shipment, 'order_code', $order['number']);
        if ($saved) {
            $this->logInfo(sprintf('Номер заказа № %s для %s записан в базу', $shipment->order_code, $shipment->shipment_id));
            return;
        }
        $this->logWarning(sprintf('Ошибка записи в базу номера заказа №%s для %s', $order['number'], $shipment->shipment_id));
    }

    /**
     * @throws GuzzleException
     */
    public function confirmShipments(): void
    {
        $shipmentList = $this->shipmentManager->findNewShipmentsWithOrderCode();
        foreach ($shipmentList as $shipment) {
            $this->confirmShipment($shipment);
        }
    }

    /**
     * @throws GuzzleException
     */
    public function confirmShipment(Shipment $shipment): void
    {
        $confirmParams = $this->createShipmentConfirmParams($shipment);
        $confirm = $this->client->orderConfirm($confirmParams);
        if ((int)$confirm['success'] === 1) {
            $this->logInfo(sprintf('Заказ №%s для %s подтверждён на маркете', $shipment->order_code, $shipment->shipment_id));
            return;
        }
        $this->logError('Ошибка подтверждения заказа на маркете' . $shipment->shipment_id, $confirmParams);
    }

    abstract public function createShipmentConfirmParams(Shipment $shipment): array;

    /**
     * @throws GuzzleException
     * @throws \Exception
     */
    public function editConfirmedOrders(): void
    {
        $someMarketplaceOrderIdList = $this->client->getConfirmedOrderIdList();
        $shipmentList = $this->shipmentManager->findNewShipmentsWithOrderCode();
        $shipmentIdList = array_map(static fn($item) => $item->shipment_id, $shipmentList);
        $someMarketplaceOrderIdList = array_values(array_unique([...$someMarketplaceOrderIdList, ...$shipmentIdList]));
        if (empty($someMarketplaceOrderIdList)) {
            return;
        }
        $someMarketplaceOrderList = $this->client->getOrderList($someMarketplaceOrderIdList);
        foreach ($someMarketplaceOrderList as $item) {
            $shipment = $this->shipmentManager->findShipmentByShipmentId((string)$item['shipmentId']);
            if (!$shipment) {
                $this->shipmentManager->saveOrUpdateShipment($item);
                continue;
            }
            $this->editOrderStatus($shipment, $item['status']);
        }
    }

    public function editOrderStatus(Shipment $shipment, string $status): void
    {
        $shipment = $this->shipmentManager->setShipmentAttribute($shipment, 'status', $status);
        if (!$this->shipmentManager->isChangedShipmentStatus($shipment)) {
            return;
        }
        $order = $this->orderManager->editStatus($shipment);
        if ($order['status'] === $shipment->getCrmStatus()) {
            if ($this->shipmentManager->saveShipment($shipment)) {
                $this->logInfo(sprintf('Статус %s заказа № %s для %s записан в базу', $shipment->getCrmStatus(), $shipment->order_code, $shipment->shipment_id));
            } else {
                $this->logError(sprintf(' Ошибка записи в базу статуса %s заказа № %s для %s', $shipment->getCrmStatus(), $shipment->order_code, $shipment->shipment_id));
            }
        }
    }

    public static function getServiceName(): string
    {
        return 'SOME_MARKETPLACE_' . strtoupper(static::classNameShort());
    }
}