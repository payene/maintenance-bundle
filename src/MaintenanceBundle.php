<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class MaintenanceBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}