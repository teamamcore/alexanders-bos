<?php

declare(strict_types=1);

namespace App\States\WorkOrder;

final class InProgress extends WorkOrderState
{
    public static string $name = 'in_progress';

    public function label(): string
    {
        return 'In progress';
    }

    public function color(): string
    {
        return 'amber';
    }
}
