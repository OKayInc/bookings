<?php

namespace App\Observers;

use App\Domain\Configuration\ConfigurationCache;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentQuestionNumericConstraint;
use App\Models\AppointmentQuestionResourceRule;
use App\Models\AppointmentQuestionVisibilityCondition;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationTax;
use App\Models\QuestionOption;
use App\Models\Resource;
use App\Models\ReusableQuestion;
use App\Models\ReusableQuestionOption;
use App\Models\ShortNoticeFeeRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ConfigurationObserver
{
    public function saved(Model $model): void { $this->changed($model); }
    public function deleted(Model $model): void { $this->changed($model); }

    // Resource sharing rows may be cascade-deleted before the deleted event.
    public function deleting(Model $model): void
    {
        if ($model instanceof Resource) {
            $this->changed($model);
        }
    }

    private function changed(Model $model): void
    {
        $ids = [];
        $slug = null;
        $oldSlug = null;
        $add = static function (mixed $id) use (&$ids): void {
            if (is_string($id) && $id !== '') {
                $ids[bin2hex($id)] = $id;
            }
        };

        if ($model instanceof Organization) {
            $add($model->getKey());
            $slug = (string) $model->slug;
            $oldSlug = (string) ($model->getRawOriginal('slug') ?? $slug);
        } elseif ($model instanceof Resource) {
            $add($model->organization_id);
            $add($model->getRawOriginal('organization_id'));
            foreach (DB::table('organization_resources')->where('resource_id', $model->getKey())->pluck('organization_id') as $id) {
                $add($id);
            }
            foreach (DB::table('appointment_type_resources')->join('appointment_types', 'appointment_types.id', '=', 'appointment_type_resources.appointment_type_id')
                ->where('appointment_type_resources.resource_id', $model->getKey())->pluck('appointment_types.organization_id') as $id) {
                $add($id);
            }
        } elseif ($model instanceof AppointmentType || $model instanceof OrganizationTax || $model instanceof ReusableQuestion) {
            $add($model->organization_id);
            $add($model->getRawOriginal('organization_id'));
        } elseif ($model instanceof ReusableQuestionOption) {
            $this->fromParent($add, 'reusable_questions', 'organization_id', $model, 'reusable_question_id');
        } elseif ($model instanceof AppointmentQuestion || $model instanceof ShortNoticeFeeRule) {
            $this->fromParent($add, 'appointment_types', 'organization_id', $model, 'appointment_type_id');
        } elseif ($model instanceof QuestionOption || $model instanceof AppointmentQuestionVisibilityCondition
            || $model instanceof AppointmentQuestionNumericConstraint || $model instanceof AppointmentQuestionResourceRule) {
            foreach (array_unique(array_filter([$model->getAttribute('appointment_question_id'), $model->getRawOriginal('appointment_question_id')])) as $questionId) {
                $typeId = DB::table('appointment_questions')->where('id', $questionId)->value('appointment_type_id');
                if ($typeId !== null) {
                    $add(DB::table('appointment_types')->where('id', $typeId)->value('organization_id'));
                }
            }
        }

        if ($ids === []) {
            return;
        }

        $invalidate = static function () use ($ids, $slug, $oldSlug, $model): void {
            $cache = app(ConfigurationCache::class);
            foreach ($ids as $id) {
                $cache->invalidate($id, $model instanceof Organization ? $slug : null, $model instanceof Organization ? $oldSlug : null);
            }
        };

        if (DB::transactionLevel() > 0) {
            // Keep reads in the current transaction (including transaction-wrapped tests)
            // from seeing an old entry; the second bump protects other nodes after commit.
            $invalidate();
            DB::afterCommit($invalidate);
        } else {
            $invalidate();
        }
    }

    private function fromParent(callable $add, string $table, string $column, Model $model, string $foreignKey): void
    {
        foreach (array_unique(array_filter([$model->getAttribute($foreignKey), $model->getRawOriginal($foreignKey)])) as $id) {
            $add(DB::table($table)->where('id', $id)->value($column));
        }
    }
}
