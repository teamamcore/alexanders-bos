<?php

declare(strict_types=1);

namespace App\States\WorkOrder;

final class Scheduled extends WorkOrderState
{
    public static string $name = 'scheduled';

    public function label(): string
    {
        return 'Scheduled';
    }

    public function color(): string
    {
        return 'indigo';
    }
}
