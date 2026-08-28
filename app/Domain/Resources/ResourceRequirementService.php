<?php

namespace App\Domain\Resources;

use App\Enums\ResourceRequirementMode;
use App\Models\AppointmentType;
use App\Models\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResourceRequirementService
{
    public function isRequired(Resource $resource, ?AppointmentType $type = null): bool
    {
        $mode = ResourceRequirementMode::tryFrom((string) ($resource->pivot?->requirement_mode ?? ResourceRequirementMode::Inherit->value))
            ?? ResourceRequirementMode::Inherit;

        return match ($mode) {
            ResourceRequirementMode::Required => true,
            ResourceRequirementMode::Replacement => true,
            ResourceRequirementMode::Optional => false,
            ResourceRequirementMode::Inherit => $type ? $resource->defaultRequiredForOrganization($type->organization) : (bool) $resource->is_required_by_default,
        };
    }

    /** @return Collection<int, Resource> */
    public function requiredResources(AppointmentType $type): Collection
    {
        $type->loadMissing('resources');

        return $type->resources->filter(
            fn (Resource $resource): bool => $this->isRequired($resource, $type) && ! $this->isReplacement($resource),
        )->values();
    }

    /** @return Collection<int, Resource> */
    public function optionalResources(AppointmentType $type): Collection
    {
        $type->loadMissing('resources');

        return $type->resources->reject(fn (Resource $resource): bool => $this->isRequired($resource, $type))->values();
    }

    /** @return Collection<string, Collection<int, Resource>> */
    public function replacementGroups(AppointmentType $type): Collection
    {
        $type->loadMissing('resources');

        return $type->resources
            ->filter(fn (Resource $resource): bool => $this->isReplacement($resource))
            ->groupBy(fn (Resource $resource): string => $this->replacementGroupKey($resource));
    }

    public function isReplacement(Resource $resource): bool
    {
        return $this->modeFor($resource) === ResourceRequirementMode::Replacement
            && $this->replacementGroup($resource) !== null;
    }

    public function replacementGroup(Resource $resource): ?string
    {
        $group = Str::squish((string) ($resource->pivot?->replacement_group ?? ''));

        return $group === '' ? null : $group;
    }

    public function replacementGroupKey(Resource $resource): string
    {
        return Str::lower($this->replacementGroup($resource) ?? '');
    }

    public function modeFor(Resource $resource): ResourceRequirementMode
    {
        return ResourceRequirementMode::tryFrom((string) ($resource->pivot?->requirement_mode ?? ResourceRequirementMode::Inherit->value))
            ?? ResourceRequirementMode::Inherit;
    }
}
