<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class VendorPortalIntegration extends Model
{
    use BelongsToTenant;
    protected $guarded = [];
    protected $casts = ['go_live_at' => 'date', 'counts_toward_included' => 'boolean'];
}
