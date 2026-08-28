<?php

namespace App\Domain\Availability;

class HolidayRegionCatalog
{
    /** @var array<string, string>|null */
    private ?array $cachedOptions = null;

    /** @return array<string, string> */
    public function options(): array
    {
        if ($this->cachedOptions !== null) {
            return $this->cachedOptions;
        }

        $options = [
            'AD' => 'Andorra',
            'AR' => 'Argentina',
            'AU' => 'Australia — national',
            'AU-ACT' => 'Australia — Australian Capital Territory',
            'AU-NSW' => 'Australia — New South Wales',
            'AU-NT' => 'Australia — Northern Territory',
            'AU-QLD' => 'Australia — Queensland',
            'AU-SA' => 'Australia — South Australia',
            'AU-TAS' => 'Australia — Tasmania',
            'AU-VIC' => 'Australia — Victoria',
            'AU-WA' => 'Australia — Western Australia',
            'AT' => 'Austria',
            'BE' => 'Belgium',
            'BA' => 'Bosnia and Herzegovina',
            'BR' => 'Brazil',
            'BG' => 'Bulgaria',
            'CA' => 'Canada — federal',
            'CA-AB' => 'Canada — Alberta',
            'CA-BC' => 'Canada — British Columbia',
            'CA-MB' => 'Canada — Manitoba',
            'CA-NB' => 'Canada — New Brunswick',
            'CA-NL' => 'Canada — Newfoundland and Labrador',
            'CA-NS' => 'Canada — Nova Scotia',
            'CA-NT' => 'Canada — Northwest Territories',
            'CA-NU' => 'Canada — Nunavut',
            'CA-ON' => 'Canada — Ontario',
            'CA-PE' => 'Canada — Prince Edward Island',
            'CA-QC' => 'Canada — Quebec',
            'CA-SK' => 'Canada — Saskatchewan',
            'CA-YT' => 'Canada — Yukon',
            'HR' => 'Croatia',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'EE' => 'Estonia',
            'FI' => 'Finland',
            'FR' => 'France',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GR' => 'Greece',
            'HU' => 'Hungary',
            'IR' => 'Iran',
            'IE' => 'Ireland',
            'IT' => 'Italy',
            'JP' => 'Japan',
            'LV' => 'Latvia',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MX' => 'Mexico',
            'NL' => 'Netherlands',
            'NZ' => 'New Zealand',
            'NO' => 'Norway',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'RO' => 'Romania',
            'RU' => 'Russia',
            'SM' => 'San Marino',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'ZA' => 'South Africa',
            'KR' => 'South Korea',
            'ES' => 'Spain',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'TR' => 'Türkiye',
            'UA' => 'Ukraine',
            'GB' => 'United Kingdom',
            'GB-ENG' => 'United Kingdom — England',
            'GB-NIR' => 'United Kingdom — Northern Ireland',
            'GB-SCT' => 'United Kingdom — Scotland',
            'GB-WLS' => 'United Kingdom — Wales',
            'US' => 'United States',
            'VE' => 'Venezuela',
        ];

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $this->cachedOptions = $options;
    }

    public function has(?string $region): bool
    {
        return $region !== null && array_key_exists($region, $this->options());
    }

    public function label(?string $region): string
    {
        return $this->options()[$region ?? ''] ?? ($region ?: 'No region selected');
    }

    public function detect(string $timezone): ?string
    {
        return $this->timezoneRegions()[$timezone] ?? null;
    }

    /** @return array<string, string> */
    private function timezoneRegions(): array
    {
        return [
            'America/St_Johns' => 'CA-NL',
            'America/Halifax' => 'CA-NS',
            'America/Glace_Bay' => 'CA-NS',
            'America/Moncton' => 'CA-NB',
            'America/Goose_Bay' => 'CA-NL',
            'America/Charlottetown' => 'CA-PE',
            'America/Toronto' => 'CA-ON',
            'America/Thunder_Bay' => 'CA-ON',
            'America/Nipigon' => 'CA-ON',
            'America/Atikokan' => 'CA-ON',
            'America/Montreal' => 'CA-QC',
            'America/Blanc-Sablon' => 'CA-QC',
            'America/Winnipeg' => 'CA-MB',
            'America/Regina' => 'CA-SK',
            'America/Swift_Current' => 'CA-SK',
            'America/Edmonton' => 'CA-AB',
            'America/Yellowknife' => 'CA-NT',
            'America/Inuvik' => 'CA-NT',
            'America/Iqaluit' => 'CA-NU',
            'America/Rankin_Inlet' => 'CA-NU',
            'America/Resolute' => 'CA-NU',
            'America/Cambridge_Bay' => 'CA-NU',
            'America/Vancouver' => 'CA-BC',
            'America/Dawson_Creek' => 'CA-BC',
            'America/Fort_Nelson' => 'CA-BC',
            'America/Creston' => 'CA-BC',
            'America/Whitehorse' => 'CA-YT',
            'America/Dawson' => 'CA-YT',
            'America/Mexico_City' => 'MX',
            'America/Cancun' => 'MX',
            'America/Chihuahua' => 'MX',
            'America/Ciudad_Juarez' => 'MX',
            'America/Hermosillo' => 'MX',
            'America/Matamoros' => 'MX',
            'America/Mazatlan' => 'MX',
            'America/Merida' => 'MX',
            'America/Monterrey' => 'MX',
            'America/Ojinaga' => 'MX',
            'America/Tijuana' => 'MX',
            'America/Bahia_Banderas' => 'MX',
            'America/New_York' => 'US',
            'America/Chicago' => 'US',
            'America/Denver' => 'US',
            'America/Los_Angeles' => 'US',
            'America/Phoenix' => 'US',
            'America/Anchorage' => 'US',
            'Pacific/Honolulu' => 'US',
            'Europe/London' => 'GB-ENG',
            'Europe/Dublin' => 'IE',
            'Europe/Paris' => 'FR',
            'Europe/Berlin' => 'DE',
            'Europe/Madrid' => 'ES',
            'Europe/Rome' => 'IT',
            'Europe/Lisbon' => 'PT',
            'Europe/Amsterdam' => 'NL',
            'Europe/Brussels' => 'BE',
            'Europe/Vienna' => 'AT',
            'Europe/Zurich' => 'CH',
            'Europe/Stockholm' => 'SE',
            'Europe/Oslo' => 'NO',
            'Europe/Copenhagen' => 'DK',
            'Europe/Helsinki' => 'FI',
            'Europe/Warsaw' => 'PL',
            'Europe/Prague' => 'CZ',
            'Europe/Bratislava' => 'SK',
            'Europe/Ljubljana' => 'SI',
            'Europe/Zagreb' => 'HR',
            'Europe/Sarajevo' => 'BA',
            'Europe/Sofia' => 'BG',
            'Europe/Bucharest' => 'RO',
            'Europe/Budapest' => 'HU',
            'Europe/Tallinn' => 'EE',
            'Europe/Riga' => 'LV',
            'Europe/Vilnius' => 'LT',
            'Europe/Luxembourg' => 'LU',
            'Europe/Athens' => 'GR',
            'Europe/Istanbul' => 'TR',
            'Europe/Kyiv' => 'UA',
            'Europe/Moscow' => 'RU',
            'Asia/Tbilisi' => 'GE',
            'Asia/Tehran' => 'IR',
            'Asia/Tokyo' => 'JP',
            'Asia/Seoul' => 'KR',
            'Africa/Johannesburg' => 'ZA',
            'America/Argentina/Buenos_Aires' => 'AR',
            'America/Sao_Paulo' => 'BR',
            'America/Caracas' => 'VE',
            'Pacific/Auckland' => 'NZ',
            'Australia/Sydney' => 'AU-NSW',
            'Australia/Melbourne' => 'AU-VIC',
            'Australia/Brisbane' => 'AU-QLD',
            'Australia/Adelaide' => 'AU-SA',
            'Australia/Perth' => 'AU-WA',
            'Australia/Hobart' => 'AU-TAS',
            'Australia/Darwin' => 'AU-NT',
        ];
    }
}
