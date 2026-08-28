<?php
namespace App\Domain\Questionnaires;
use InvalidArgumentException;
class PercentageService {
    public function parseToBasisPoints(string|int|null $value): ?int {
        if ($value === null || trim((string)$value)==='') return null;
        $raw=trim((string)$value);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/',$raw)) throw new InvalidArgumentException('Percentage must be a non-negative number with at most two decimal places.');
        [$w,$f]=array_pad(explode('.',$raw,2),2,''); $f=str_pad($f,2,'0');
        $bps=((int)$w*100)+(int)$f;
        if ($bps>100000) throw new InvalidArgumentException('Percentage cannot exceed 1000%.');
        return $bps;
    }
    public function display(?int $bps): string { if ($bps===null) return ''; return rtrim(rtrim(number_format($bps/100,2,'.',''),'0'),'.'); }
}
