<?php

declare(strict_types=1);

namespace App\States\WorkOrder;

final class Completed extends WorkOrderState
{
    public static string $name = 'complete';

    public function label(): string
    {
        return 'Complete';
    }

    public function color(): string
    {
        return 'green';
    }
}
