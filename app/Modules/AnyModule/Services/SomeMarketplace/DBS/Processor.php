<?php

declare(strict_types=1);

namespace Modules\AnyModule\Services\SomeMarketplace\DBS;

use GuzzleHttp\Exception\GuzzleException;
use Modules\AnyModule\Models\SomeMarketplace\Shipment;
use Modules\AnyModule\Services\SomeMarketplace\BaseProcessor;
use Psr\SimpleCache\InvalidArgumentException;

class Processor extends BaseProcessor
{
    /**
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function processShipments(): void
    {
        $this->startLog('checkAndSaveNewShipments');
        $this->checkAndSaveNewShipments();
        $this->finishLog('checkAndSaveNewShipments');

        $this->startLog('createOrders');
        $this->createOrders();
        $this->finishLog('createOrders');

        $this->startLog('confirmShipments');
        $this->confirmShipments();
        $this->finishLog('confirmShipments');

        $this->startLog('editConfirmedOrders');
        $this->editConfirmedOrders();
        $this->finishLog('editConfirmedOrders');

        $this->startLog('editCustomerCanceledOrders');
        $this->editCustomerCanceledOrders();
        $this->finishLog('editCustomerCanceledOrders');

        $this->startLog('checkFinishedOrders');
        $this->checkFinishedOrders();
        $this->finishLog('checkFinishedOrders');
    }

    /**
     * @throws GuzzleException
     */
    public function checkFinishedOrders(): void
    {
        $shipments = $this->shipmentManager->findNonFinishedShipments();
        if (empty($shipments)) {
            return;
        }
        $orderCodes = array_map(static fn(Shipment $shipment) => $shipment->order_code, $shipments);
        $orders = $this->orderManager->getFinishedOrdersByNumberList($orderCodes);
        if (empty($orders)) {
            return;
        }
        foreach ($orders as $order) {
            $shipmentResult = array_filter($shipments, static fn($shipment) => $shipment->order_code === $order['number']);
            $shipment = array_shift($shipmentResult);
            $this->closeShipment($shipment, $order);
            $shipmentData = $this->client->getOrder($shipment->shipment_id);
            $this->shipmentManager->setAndSaveShipmentAttribute($shipment, 'status', $shipmentData['status']);
        }
    }

    public function closeShipment(Shipment $shipment, array $order): void
    {
        $params = $this->createCloseShipmentParams($shipment, $order);
        $confirm = $this->client->closeOrder($params);
        if ((int)$confirm['success'] === 1) {
            $this->logInfo(sprintf('Заказ №%s для %s закрыт на маркете', $shipment->order_code, $shipment->shipment_id));
            return;
        }
        $this->logError('Ошибка закрытия заказа на маркете' . $shipment->shipment_id, $params);
    }

    public function createCloseShipmentParams(Shipment $shipment, array $order): array
    {
        $shipmentItems = $this->shipmentManager->findActualItemsByShipment($shipment);
        $handoverResult = ($order['status'] === 'cancel-other') ? FALSE : TRUE;
        return [
            'shipmentId' => $shipment->shipment_id,
            'closeDate' => '',
            'items' => array_map(static fn($item) => [
                'itemIndex' => $item->item_index,
                'handoverResult' => $handoverResult,
            ], $shipmentItems),
        ];
    }

    public function createShipmentConfirmParams(Shipment $shipment): array
    {
        $shipmentItems = $this->shipmentManager->findActualItemsByShipment($shipment);
        return [
            'shipmentId' => $shipment->shipment_id,
            'orderCode' => $shipment->order_code,
            'items' => array_map(static fn($item) => [
                'itemIndex' => $item->item_index,
                'offerId' => $item->offer_id,
                'shippingDate' => $item->shipping_time_limit,
            ], $shipmentItems),
        ];
    }

    /**
     * @throws GuzzleException
     * @throws \Exception
     */
    public function editCustomerCanceledOrders(): void
    {
        $someMarketplaceOrderIdList = $this->client->getCustomerCanceledOrderIdList();
        if (empty($someMarketplaceOrderIdList)) {
            return;
        }
        $someMarketplaceOrderList = $this->client->getOrderList($someMarketplaceOrderIdList);
        foreach ($someMarketplaceOrderList as $item) {
            $shipment = $this->shipmentManager->findShipmentByShipmentId((string)$item['shipmentId']);
            if (!$shipment) {
                continue;
            }
            $this->editOrderStatus($shipment, $item['status']);
        }
    }

    public static function getServiceName(): string
    {
        return 'SOME_MARKETPLACE_DBS_' . strtoupper(static::classNameShort());
    }
}