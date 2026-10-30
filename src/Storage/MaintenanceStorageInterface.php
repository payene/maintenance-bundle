<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Storage;

use Payene\MaintenanceBundle\Model\MaintenanceState;

interface MaintenanceStorageInterface
{
    public function load(): MaintenanceState;

    public function save(MaintenanceState $state): void;
}