<?php

declare(strict_types=1);

namespace Modules\AnyModule\Commands;

use GuzzleHttp\Exception\GuzzleException;
use Modules\AnyModule\Services\SameMarketplace\DBS\Processor as DbsProcessor;
use Modules\AnyModule\Services\SameMarketplace\FBS\Processor as FbsProcessor;
use Phact\Commands\Command;
use Psr\SimpleCache\InvalidArgumentException;

class SameMarketplaceCommand extends Command
{
    private DbsProcessor $dbs;
    private FbsProcessor $fbs;

    public function __construct(DbsProcessor $dbs, FbsProcessor $fbs)
    {
        $this->dbs = $dbs;
        $this->fbs = $fbs;
    }

    /**
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function handle($arguments = []): void
    {
        $this->fbs->startLog('processShipments');
        $this->fbs->processShipments();
        $this->fbs->finishLog('processShipments');

        $this->dbs->startLog('processShipments');
        $this->dbs->processShipments();
        $this->dbs->finishLog('processShipments');
    }
}