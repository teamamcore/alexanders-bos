<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderFile;
use Illuminate\Http\UploadedFile;
use Lorisleiva\Actions\Concerns\AsAction;

/** Photo / file / receipt onto the work order parent (public disk). */
final class UploadWorkOrderFile
{
    use AsAction;

    public function handle(WorkOrder $workOrder, User $user, UploadedFile $file, string $kind = 'photo', ?string $caption = null): WorkOrderFile
    {
        $kind = in_array($kind, ['photo', 'file', 'receipt'], true) ? $kind : 'file';
        $path = $file->store('work-orders/'.$workOrder->id, 'public');

        $record = $workOrder->files()->create([
            'tenant_id' => $workOrder->tenant_id,
            'user_id' => $user->id,
            'kind' => $kind,
            'path' => $path,
            'caption' => $caption ? trim($caption) : null,
        ]);

        AddWorkOrderNote::run($workOrder, $user, ucfirst($kind).' added'.($caption ? ": {$caption}" : ''), 'system');

        return $record;
    }
}
