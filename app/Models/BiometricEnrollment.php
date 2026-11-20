<?php

namespace App\Models;

use App\Enums\BiometricStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $provider
 * @property string $collection_id
 * @property string|null $external_face_id
 * @property BiometricStatus $status
 * @property Carbon|null $consent_given_at
 * @property string|null $consent_version
 * @property Carbon|null $enrolled_at
 * @property Carbon|null $revoked_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 */
#[Fillable([
    'employee_id',
    'provider',
    'collection_id',
    'external_face_id',
    'status',
    'consent_given_at',
    'consent_version',
    'enrolled_at',
    'revoked_at',
    'last_error',
])]
class BiometricEnrollment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BiometricStatus::class,
            'consent_given_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
