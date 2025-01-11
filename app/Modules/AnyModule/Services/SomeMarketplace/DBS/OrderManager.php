<?php

declare(strict_types=1);

namespace Modules\AnyModule\Services\SomeMarketplace\DBS;

use DateTimeImmutable;
use Devmakis\ProdCalendar\Calendar;
use Devmakis\ProdCalendar\Clients\Exceptions\ClientException;
use Libs\Services\RetailCrm\ApiClient as RetailCrmApiClient;
use Modules\AnyModule\Models\SomeMarketplace\Shipment;
use Modules\AnyModule\Services\SomeMarketplace\BaseOrderManager;
use Modules\AnyModule\Services\ProductSet;
use Psr\Log\LoggerInterface;

class OrderManager extends BaseOrderManager
{
    public Calendar $prodCalendar;
    public function __construct(
        RetailCrmApiClient $retailCrmApiClient,
        ProductSet $productSet,
        Calendar $prodCalendar,
        LoggerInterface $logger
    ) {
        parent::__construct($retailCrmApiClient, $productSet, $logger);
        $this->prodCalendar = $prodCalendar;
    }

    public function createNewOrderParams(Shipment $shipment): array
    {
        $today = new DateTimeImmutable('today 10:00:00');
        $creationDate = new DateTimeImmutable($shipment->creation_date);
        $shipmentDate = $creationDate < $today ? $creationDate : $this->getNextWorkingDay();
        $params = parent::createNewOrderParams($shipment);
        $params['shipmentDate'] = $shipmentDate->format('Y-m-d');
        $params['customFields']['data_dostavki'] = $shipmentDate->modify('+7 day')->format('Y-m-d');
        $params['delivery']['code'] = 'dpd';
        $params['delivery']['data']['tariff'] = 'PCL';
        return $params;
    }

    public function getFinishedOrdersByNumberList(array $numberList): array
    {
        return $this->retailCrmApiClient->getOrdersAll([
            'numbers' => $numberList,
            'extendedStatus' => [
                'complete',
                'oplachen',
                'cancel-other',
            ],
        ]);
    }

    public static function getServiceName(): string
    {
        return 'SOME_MARKETPLACE_DBS_' . strtoupper(static::classNameShort());
    }

    /**
     * @throws ClientException
     */
    private function getNextWorkingDay(): DateTimeImmutable
    {
        $workingDay = new DateTimeImmutable('today');
        $max = $workingDay->modify('+10 day');
        do {
            $workingDay = $workingDay->modify('+1 day');
        } while ($this->prodCalendar->isNonWorking($workingDay) && $workingDay < $max);
        return $workingDay;
    }
}