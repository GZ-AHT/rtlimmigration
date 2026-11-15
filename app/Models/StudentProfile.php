<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Personal, contact and passport details (REQ §10, §11.2).
 *
 * @property int $id
 * @property int $student_id
 * @property CarbonInterface|null $date_of_birth
 * @property Gender|null $gender
 * @property MaritalStatus|null $marital_status
 * @property string|null $nationality
 * @property string|null $place_of_birth
 * @property string|null $alternate_phone
 * @property string|null $address_line
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_relation
 * @property string|null $emergency_contact_phone
 * @property string|null $passport_number
 * @property CarbonInterface|null $passport_issue_date
 * @property CarbonInterface|null $passport_expiry_date
 * @property string|null $passport_issue_place
 * @property string|null $passport_issuing_country
 */
#[Fillable([
    'student_id', 'date_of_birth', 'gender', 'marital_status', 'nationality', 'place_of_birth',
    'alternate_phone', 'address_line', 'city', 'state', 'postal_code', 'country',
    'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
    'passport_number', 'passport_issue_date', 'passport_expiry_date', 'passport_issue_place', 'passport_issuing_country',
])]
#[Hidden(['passport_number'])]
class StudentProfile extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            // Sensitive identity data is encrypted at rest (REQ §26).
            'passport_number' => 'encrypted',
            'passport_issue_date' => 'date',
            'passport_expiry_date' => 'date',
        ];
    }

    /**
     * REQ §26 "Keep audit history for ... profile changes". The passport
     * number is logged as changed, never with its value.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('students')
            ->logFillable()
            ->logExcept(['passport_number', 'student_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function maskedPassportNumber(): ?string
    {
        $n = $this->passport_number;

        return $n ? str_repeat('•', max(0, strlen($n) - 3)).substr($n, -3) : null;
    }
}
