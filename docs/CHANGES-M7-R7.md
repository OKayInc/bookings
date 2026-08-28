# M7-R7 changes

M7-R7 internationalizes holiday selection, prevents duplicate configured closures, and adds per-resource holiday enforcement.

## Country and region selection

- The organization IANA timezone suggests a country or subdivision, such as `America/Toronto` → Ontario or `America/Mexico_City` → Mexico.
- The selected `holiday_region` is saved explicitly and can be overridden. GeoIP is not used as scheduling truth because an administrator's current network location may differ from the business jurisdiction.
- The organization picker uses Yasumi 2.11 providers and offers official/bank holidays for supported countries and subdivisions.
- Organization holidays remain opt-in: changing the region does not automatically enable its full calendar.
- Existing custom fixed, Easter-relative, nth-weekday, and one-time rules remain supported.

## Duplicate prevention

- Provider selections use a stable region-and-provider preset key protected by the existing organization/preset unique constraint.
- A configured holiday is removed from the available select immediately.
- The UI and create action also compare the next three resolved dates. A legacy fixed Christmas rule, for example, suppresses and reactivates instead of duplicating the matching provider holiday.

## Resource calendars

- Each `organization_resources` relationship can opt into enforced official/bank holidays and select its own region.
- Settings are organization-specific even when the same resource is shared.
- Resource timezone is used for the local holiday date boundary and region suggestion.
- Required-resource holidays are unioned into mandatory availability. If any required resource is closed, the slot is unavailable.
- An optional resource that is closed does not remove the slot; it is omitted from the booking hold.
- Public slot generation, standard and group hold acquisition, final booking creation, and rescheduling all recheck applicable resource closures.
- Group-capacity holds now snapshot the existing appointment resources, closing a legacy gap in their final resource validation.

## Schema and dependency

Migration `2026_08_28_000051_add_regional_holiday_settings.php` adds the organization region, regional holiday provider keys, and organization-resource holiday settings. Composer now requires `azuyalabs/yasumi:^2.11`. No new environment variable, queue, or scheduled command is required.
