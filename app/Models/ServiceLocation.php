<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property string|null $label
 * @property string $address
 * @property string|null $type
 * @property string|null $access_notes
 * @property int|null $contact_id
 */
class ServiceLocation extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['site_requirements' => 'array'];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Local contact at this site (design notes §3). @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
