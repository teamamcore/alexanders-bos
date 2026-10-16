<?php

declare(strict_types=1);

namespace App\States\WorkOrder;

final class NewWorkOrder extends WorkOrderState
{
    public static string $name = 'new';

    public function label(): string
    {
        return 'New';
    }

    public function color(): string
    {
        return 'blue';
    }
}
