<?php

namespace App\Domain\Availability;

use Carbon\CarbonImmutable;
use Throwable;
use Yasumi\Holiday;
use Yasumi\ProviderInterface;
use Yasumi\Yasumi;

class PublicHolidayCalendar
{
    /** @var array<string, array<int, ProviderInterface>> */
    private array $providers = [];

    public function __construct(private readonly HolidayRegionCatalog $regions)
    {
    }

    /** @return array<string, array{key:string,preset_key:string,name:string,type:string,dates:array<int,string>}> */
    public function available(string $region, int $firstYear, int $yearCount = 2): array
    {
        if (! $this->regions->has($region)) {
            return [];
        }

        $result = [];
        for ($year = $firstYear; $year < $firstYear + max(1, $yearCount); $year++) {
            try {
                $provider = $this->provider($region, $year);
            } catch (Throwable) {
                continue;
            }

            foreach ($provider as $holiday) {
                if (! $this->blocksAvailability($holiday)) {
                    continue;
                }

                $key = $holiday->getKey();
                $result[$key] ??= [
                    'key' => $key,
                    'preset_key' => $this->presetKey($region, $key),
                    'name' => $holiday->getName(),
                    'type' => $holiday->getType(),
                    'dates' => [],
                ];
                $result[$key]['dates'][$year] = $holiday->format('Y-m-d');
            }
        }

        uasort($result, function (array $left, array $right): int {
            $leftDate = reset($left['dates']) ?: '9999-12-31';
            $rightDate = reset($right['dates']) ?: '9999-12-31';

            return [$leftDate, $left['name']] <=> [$rightDate, $right['name']];
        });

        return $result;
    }

    public function definition(string $region, string $holidayKey, int $firstYear): ?array
    {
        return $this->available($region, $firstYear, 3)[$holidayKey] ?? null;
    }

    public function dateFor(string $region, string $holidayKey, int $year, string $timezone): ?CarbonImmutable
    {
        if (! $this->regions->has($region)) {
            return null;
        }

        try {
            $holiday = $this->provider($region, $year)->getHoliday($holidayKey);
        } catch (Throwable) {
            return null;
        }

        if ($holiday === null || ! $this->blocksAvailability($holiday)) {
            return null;
        }

        return CarbonImmutable::parse($holiday->format('Y-m-d'), $timezone)->startOfDay();
    }

    /** @return list<AvailabilityInterval> */
    public function closures(
        string $region,
        string $timezone,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
    ): array {
        if (! $this->regions->has($region) || $rangeEndUtc->lte($rangeStartUtc)) {
            return [];
        }

        $startYear = (int) $rangeStartUtc->setTimezone($timezone)->subDay()->format('Y');
        $endYear = (int) $rangeEndUtc->setTimezone($timezone)->addDay()->format('Y');
        $closures = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            try {
                $provider = $this->provider($region, $year);
            } catch (Throwable) {
                continue;
            }

            foreach ($provider as $holiday) {
                if (! $this->blocksAvailability($holiday)) {
                    continue;
                }

                $date = CarbonImmutable::parse($holiday->format('Y-m-d'), $timezone)->startOfDay();
                $start = $date->utc();
                $end = $date->addDay()->startOfDay()->utc();
                if ($end->lte($rangeStartUtc) || $start->gte($rangeEndUtc)) {
                    continue;
                }

                $closures[$date->format('Y-m-d')] = new AvailabilityInterval($start, $end);
            }
        }

        ksort($closures);

        return array_values($closures);
    }

    public function isClosed(
        string $region,
        string $timezone,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): bool {
        $candidate = new AvailabilityInterval($startsAtUtc, $endsAtUtc);
        foreach ($this->closures($region, $timezone, $startsAtUtc->subDay(), $endsAtUtc->addDay()) as $closure) {
            if ($candidate->overlaps($closure)) {
                return true;
            }
        }

        return false;
    }

    public function presetKey(string $region, string $holidayKey): string
    {
        return 'regional:'.substr(hash('sha256', $region.'|'.$holidayKey), 0, 40);
    }

    private function provider(string $region, int $year): ProviderInterface
    {
        return $this->providers[$region][$year] ??= Yasumi::createByISO3166_2($region, $year, 'en_US');
    }

    private function blocksAvailability(Holiday $holiday): bool
    {
        return in_array($holiday->getType(), [Holiday::TYPE_OFFICIAL, Holiday::TYPE_BANK], true);
    }
}
