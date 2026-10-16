<?php

declare(strict_types=1);

namespace App\States\WorkOrder;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Work-order status pipeline (3+ statuses with transition rules → Model States
 * per the engineering standards). Field crews move an order new → scheduled →
 * in-progress → complete; it can also be rescheduled back a step.
 */
abstract class WorkOrderState extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(NewWorkOrder::class)
            ->allowTransition(NewWorkOrder::class, Scheduled::class)
            ->allowTransition(Scheduled::class, InProgress::class)
            ->allowTransition(InProgress::class, Completed::class)
            ->allowTransition(Scheduled::class, NewWorkOrder::class)   // unschedule
            ->allowTransition(InProgress::class, Scheduled::class);    // reschedule
    }
}
