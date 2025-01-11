<?php

declare(strict_types=1);

namespace Modules\AnyModule\Services\SomeMarketplace;

use Libs\Helpers\Uuid;
use Libs\Services\SomeMarketplace\BaseApiClient as SomeMarketplaceApiClient;
use Libs\Services\RetailCrm\ApiClient as RetailCrmApiClient;
use Libs\Traits\Logger;
use Modules\AnyModule\Models\SomeMarketplace\Shipment;
use Modules\AnyModule\Repository\SomeMarketplace\ShipmentItemRepository;
use Modules\AnyModule\Repository\SomeMarketplace\ShipmentRepository;
use Modules\AnyModule\Services\ProductSet;
use Phact\Helpers\ClassNames;
use Phact\Helpers\SmartProperties;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;

abstract class BaseOrderManager
{
    use SmartProperties, ClassNames, Logger;

    private SomeMarketplaceApiClient $someMarketplaceApiClient;
    protected RetailCrmApiClient $retailCrmApiClient;
    private ShipmentRepository $shipmentRepository;
    private ShipmentItemRepository $shipmentItemRepository;
    private ProductSet $productSet;

    public function __construct(
        RetailCrmApiClient $retailCrmApiClient,
        ProductSet $productSet,
        LoggerInterface $logger
    ) {
        $this->retailCrmApiClient = $retailCrmApiClient;
        $this->productSet = $productSet;
        $this->logger = $logger;
    }

    /**
     * @throws InvalidArgumentException
     * @throws \Exception
     */
    public function create(Shipment $shipment): array
    {
        $params = $this->createNewOrderParams($shipment);
        try {
            $order = $this->retailCrmApiClient->createOrder(
                $params,
                RetailCrmApiClient::DEFAULT_SITE_CODE,
                static::getServiceName()
            );
            $this->logInfo(sprintf('Заказ № %s для %s создан в CRM', $order['number'], $shipment->shipment_id), $params);
        } catch (RuntimeException $exception) {
            $this->logError('Ошибка создания заказа в CRM для ' . $shipment->shipment_id, $params);
            throw $exception;
        }
        try {
            $this->productSet->replaceProductSetsIfExists($order);
        } catch (\Exception $exception) {
            $this->logError('Ошибка разбивки комплексного заказа %s в СРМ' . $order['number'], $order);
        }
        return $order;
    }

    /**
     * @throws \Exception
     */
    public function createNewOrderParams(Shipment $shipment): array
    {
        $externalId = !PHACT_DEBUG ? $shipment->shipment_id : Uuid::V4();
        $externalId = "some-{$shipment->getSchemeName()}-$externalId";
        $items = $this->createProductItems($shipment);
        $amount = array_reduce($items, static fn($carry, $item) => $carry + $item['initialPrice'] * $item['quantity']);
        $params = [
            'number' => "{$shipment->shipment_id}SOME",
            'items' => $items,
            'createdAt' => $shipment->creation_date,
            'source' => ['source' => 'sber'],
            'status' => !PHACT_DEBUG ? $shipment->getCrmStatus() : 'test-status',
            'orderMethod' => "some-marketplace-{$shipment->getSchemeName()}",
            'externalId' => $externalId,
            'delivery' => [
                'address' => [
                    'text' => $shipment->customer_address,
                ],
            ],
            'managerId' => 83,
            'customerComment' => "заказ маркет {$shipment->getSchemeName()} $shipment->shipment_id" . PHP_EOL
                . "сумма $amount P (цена без скидки)" . PHP_EOL
                . 'оплачен заказ',
            'customFields' => [
                'prepay' => true,
            ],
            'payments' => [
                [
                    'externalId' => "$externalId-cash",
                    'amount' => $amount,
                    'paidAt' => $shipment->creation_date,
                    'type' => 'cash',
                    'status' => 'paid',
                ],
            ],
        ];
        if ($shipment->customer_full_name !== null) {
            $fa = new \Mihanentalpo\FioAnalyzer\FioAnalyzer();
            $names = $fa->break_apart($shipment->customer_full_name);
            if (!empty($names['last_name']['src'])) {
                $params['lastName'] = $names['last_name']['src'];
            }
            if (!empty($names['first_name']['src'])) {
                $params['firstName'] = $names['first_name']['src'];
            }
            if (!empty($names['second_name']['src'])) {
                $params['patronymic'] = $names['second_name']['src'];
            }
            if (
                empty($params['lastName'])
                && empty($params['firstName'])
                && empty($params['patronymic'])
            ) {
                $params['firstName'] = $shipment->customer_full_name;
            }
        }
        return $params;
    }

    public function createProductItems(Shipment $shipment): array
    {
        $items = [];
        $shipmentItems = $shipment->getActualItems();
        $orderItems = [];
        foreach ($shipmentItems as $shipmentItem) {
            $orderItems[$shipmentItem->offer_id]['amount'] =
                ($orderItems[$shipmentItem->offer_id]['amount'] ?? 0) + $shipmentItem->price;
            $orderItems[$shipmentItem->offer_id]['quantity'] =
                ($orderItems[$shipmentItem->offer_id]['quantity'] ?? 0) + 1;
            $orderItems[$shipmentItem->offer_id]['name'] = $shipmentItem->goods_data['name'];
        }
        foreach ($orderItems as $offerId => $orderItem) {
            $crmProduct = $this->retailCrmApiClient->getProducts([
                'sites' => [$this->retailCrmApiClient::DEFAULT_SITE_CODE],
                'properties' => ['some_markeplace_offer_id' => $offerId],
            ], 1, 100, static::getServiceName())[0] ?? [];
            $crmProductOfferId = $crmProduct['offers'][0]['id'] ?? null;
            if ($crmProductOfferId === null) {
                $this->logWarning('Не найден товар', [
                    'shipment' => $shipment->getAttributes(),
                    'shipment_item' => $orderItem,
                    'crm_product' => $crmProduct,
                ]);
            }
            $item = [
                'quantity' => $orderItem['quantity'],
                'initialPrice' => $orderItem['amount'] / $orderItem['quantity'],
                'discountManualAmount' => 0,
            ];
            if ($crmProductOfferId) {
                $item['offer'] = ['id' => $crmProductOfferId];
            } else {
                $item['productName'] = $orderItem['name'];
                $item['properties'] = [
                    [
                        'code' => 'some_markeplace_offer_id',
                        'name' => 'Артикул на маркете',
                        'value' => $offerId,
                    ],
                ];
            }
            $items[] = $item;
        }
        return $items;
    }

    public function editStatus(Shipment $shipment): array
    {
        $params = $this->createEditStatusParams($shipment);
        try {
            $order = $this->retailCrmApiClient->editOrder(
                $params,
                RetailCrmApiClient::DEFAULT_SITE_CODE,
                static::getServiceName()
            );
            $this->logInfo(sprintf('Статус заказа № %s для %s изменён в CRM на %s', $order['number'], $shipment->shipment_id, $order['status']), $params);
        } catch (RuntimeException $exception) {
            $this->logError('Ошибка изменения статуса заказа в CRM для ' . $shipment->shipment_id, $params);
            throw $exception;
        }
        return $order;
    }

    public function createEditStatusParams(Shipment $shipment): array
    {
        $orders = $this->retailCrmApiClient->getOrders([
            'numbers' => [$shipment->order_code],
            'sites' => [$this->retailCrmApiClient::DEFAULT_SITE_CODE],
        ]);
        return [
            'id' => $orders[0]['id'],
            'status' => $shipment->getCrmStatus(),
        ];
    }

    public function getRetailCrmApiClient(): RetailCrmApiClient
    {
        return $this->retailCrmApiClient;
    }

    public static function getServiceName(): string
    {
        return 'SOME_MARKETPLACE_' . strtoupper(static::classNameShort());
    }
}