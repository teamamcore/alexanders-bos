<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property bool $sms_opt_in
 * @property string|null $preferred_channel
 * @property string|null $notes
 */
class Customer extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['sms_opt_in' => 'boolean'];

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function serviceLocations(): HasMany
    {
        return $this->hasMany(ServiceLocation::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /** @return HasMany<CustomerMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(CustomerMessage::class)->latest();
    }

    /** @return HasMany<CallTranscript, $this> */
    public function calls(): HasMany
    {
        return $this->hasMany(CallTranscript::class)->latest('called_at');
    }
}
