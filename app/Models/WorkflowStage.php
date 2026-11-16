<?php

namespace App\Models;

use App\Enums\StageKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $workflow_id
 * @property string $name
 * @property StageKind $kind
 * @property string|null $description
 * @property bool $notify_student
 * @property int $sort_order
 * @property-read Workflow $workflow
 */
#[Fillable(['workflow_id', 'name', 'kind', 'description', 'notify_student', 'sort_order'])]
class WorkflowStage extends Model
{
    use SoftDeletes;

    protected $attributes = ['kind' => 'standard', 'notify_student' => true];

    protected function casts(): array
    {
        return ['kind' => StageKind::class, 'notify_student' => 'boolean'];
    }

    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
