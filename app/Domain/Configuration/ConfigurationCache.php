<?php

namespace App\Domain\Configuration;

use App\Enums\AppointmentVisibility;
use App\Models\AppointmentType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Public, rarely changing configuration only. Availability and booking state stay in SQL. */
class ConfigurationCache
{
    private const GRAPH = [
        'organization.taxes',
        'resources',
        'questions.options',
        'questions.visibilityConditions.sourceQuestion',
        'questions.visibilityConditions.expectedOption',
        'questions.visibilityConditions.expectedOptions',
        'questions.numericConstraints.sourceQuestion',
        'questions.resourceRequirementRule.triggerOption',
        'questions.resourceRequirementRule.unavailableDefaultOption',
        'questions.resourceRequirementRule.resources',
        'shortNoticeFeeRules',
    ];

    public function organization(string $slug): Organization
    {
        return $this->remember('organization:'.hash('sha256', $slug),
            fn (): Organization => Organization::where('slug', $slug)->firstOrFail());
    }

    /** @return Collection<int, AppointmentType> */
    public function publicTypes(Organization $organization): Collection
    {
        return $this->remember($this->key($organization, 'public-types'),
            fn (): Collection => $organization->appointmentTypes()
                ->with(['organization', 'resources'])
                ->where('is_active', true)
                ->where('visibility', AppointmentVisibility::Public->value)
                ->orderBy('name')->get());
    }

    public function publicType(Organization $organization, string $slug): AppointmentType
    {
        return $this->remember($this->key($organization, 'public-type:'.hash('sha256', $slug)),
            fn (): AppointmentType => $organization->appointmentTypes()
                ->with(['organization', 'resources'])
                ->where('slug', $slug)->where('is_active', true)->firstOrFail());
    }

    /** Prime only static definitions; never use this inside a booking write transaction. */
    public function prime(AppointmentType $type): void
    {
        if (DB::transactionLevel() > 0) {
            $type->loadMissing(self::GRAPH);

            return;
        }

        $graph = $this->remember($this->keyForType($type, 'graph'),
            fn (): AppointmentType => AppointmentType::query()->with(self::GRAPH)->findOrFail($type->getKey()));

        foreach ($graph->getRelations() as $name => $relation) {
            $type->setRelation($name, $relation);
        }
    }

    public function invalidate(string $organizationId, ?string $slug = null, ?string $oldSlug = null): void
    {
        // A new generation makes every cached type and its nested definitions unreachable.
        // Old generations expire after the bounded TTL; no key scans or Redis tag flushes.
        $this->store()->forever('configuration:generation:'.bin2hex($organizationId), (string) Str::uuid());
        foreach (array_unique(array_filter([$slug, $oldSlug])) as $value) {
            $this->store()->forget('organization:'.hash('sha256', $value));
        }
    }

    public function invalidateAfterCommit(string $organizationId): void
    {
        if (DB::transactionLevel() > 0) {
            $this->invalidate($organizationId);
            DB::afterCommit(fn () => $this->invalidate($organizationId));
        } else {
            $this->invalidate($organizationId);
        }
    }

    private function keyForType(AppointmentType $type, string $suffix): string
    {
        return $this->keyFromId($type->organization_id, $suffix.':'.bin2hex($type->getKey()));
    }

    private function key(Organization $organization, string $suffix): string
    {
        return $this->keyFromId($organization->getKey(), $suffix);
    }

    private function keyFromId(string $id, string $suffix): string
    {
        $hex = bin2hex($id);
        $generation = $this->store()->get('configuration:generation:'.$hex, 'initial');

        return 'configuration:'.$hex.':'.$generation.':'.$suffix;
    }

    private function remember(string $key, callable $query): mixed
    {
        if (DB::transactionLevel() > 0 || (int) config('configuration-cache.ttl') <= 0) {
            return $query();
        }

        $result = $this->store()->remember($key, (int) config('configuration-cache.ttl'), $query);

        // The array cache driver retains object references; callers must never mutate its entry.
        return unserialize(serialize($result));
    }

    private function store(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store((string) config('configuration-cache.store', 'redis'));
    }
}
