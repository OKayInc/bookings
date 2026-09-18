<?php

namespace App\Domain\Coupons;

use RuntimeException;

/** Dependency-free QR encoder (QR version 5-L, byte mode, mask 0). */
class CouponQrCodeService
{
    private const SIZE = 37;
    private const DATA_CODEWORDS = 108;
    private const ECC_CODEWORDS = 26;

    public function svg(string $url): string
    {
        $bytes = array_values(unpack('C*', $url));
        if (count($bytes) > 106) {
            throw new RuntimeException('The public application URL is too long to encode in the coupon QR code.');
        }
        $codewords = $this->dataCodewords($bytes);
        $codewords = [...$codewords, ...$this->reedSolomonRemainder($codewords, self::ECC_CODEWORDS)];
        $matrix = $this->matrix($codewords);
        $rects = [];
        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $rects[] = '<rect x="'.($x + 4).'" y="'.($y + 4).'" width="1" height="1"/>';
                }
            }
        }
        $view = self::SIZE + 8;

        return '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$view.' '.$view.'" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="white"/><g fill="black">'.implode('', $rects).'</g></svg>';
    }

    /** @param list<int> $bytes @return list<int> */
    private function dataCodewords(array $bytes): array
    {
        $bits = '0100'.str_pad(decbin(count($bytes)), 8, '0', STR_PAD_LEFT);
        foreach ($bytes as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $capacity = self::DATA_CODEWORDS * 8;
        $bits .= str_repeat('0', min(4, $capacity - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $result = [];
        foreach (str_split($bits, 8) as $byte) {
            $result[] = bindec($byte);
        }
        for ($pad = 0; count($result) < self::DATA_CODEWORDS; $pad++) {
            $result[] = $pad % 2 === 0 ? 0xEC : 0x11;
        }

        return $result;
    }

    /** @param list<int> $data @return list<int> */
    private function reedSolomonRemainder(array $data, int $degree): array
    {
        $divisor = array_fill(0, $degree, 0);
        $divisor[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $divisor[$j] = $this->multiply($divisor[$j], $root);
                if ($j + 1 < $degree) {
                    $divisor[$j] ^= $divisor[$j + 1];
                }
            }
            $root = $this->multiply($root, 2);
        }
        $result = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coefficient) {
                $result[$i] ^= $this->multiply($coefficient, $factor);
            }
        }

        return $result;
    }

    private function multiply(int $x, int $y): int
    {
        $result = 0;
        for ($i = 7; $i >= 0; $i--) {
            $result = (($result << 1) ^ (($result >> 7) * 0x11D)) & 0xFF;
            $result ^= (($y >> $i) & 1) * $x;
        }
        return $result;
    }

    /** @param list<int> $codewords @return array<int,array<int,bool>> */
    private function matrix(array $codewords): array
    {
        $modules = array_fill(0, self::SIZE, array_fill(0, self::SIZE, false));
        $function = array_fill(0, self::SIZE, array_fill(0, self::SIZE, false));
        foreach ([[3, 3], [self::SIZE - 4, 3], [3, self::SIZE - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx; $y = $cy + $dy;
                    if ($x >= 0 && $x < self::SIZE && $y >= 0 && $y < self::SIZE) {
                        $distance = max(abs($dx), abs($dy));
                        $this->set($modules, $function, $x, $y, $distance !== 2 && $distance !== 4);
                    }
                }
            }
        }
        for ($i = 8; $i < self::SIZE - 8; $i++) {
            $this->set($modules, $function, 6, $i, $i % 2 === 0);
            $this->set($modules, $function, $i, 6, $i % 2 === 0);
        }
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->set($modules, $function, 30 + $dx, 30 + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
        $this->drawFormat($modules, $function, 0);

        $bits = '';
        foreach ($codewords as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $bit = 0;
        for ($right = self::SIZE - 1; $right >= 1; $right -= 2) {
            if ($right === 6) { $right--; }
            for ($vertical = 0; $vertical < self::SIZE; $vertical++) {
                $upward = (($right + 1) & 2) === 0;
                $y = $upward ? self::SIZE - 1 - $vertical : $vertical;
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    if (! $function[$y][$x]) {
                        $dark = $bit < strlen($bits) && $bits[$bit] === '1';
                        $bit++;
                        if (($x + $y) % 2 === 0) { $dark = ! $dark; }
                        $modules[$y][$x] = $dark;
                    }
                }
            }
        }

        return $modules;
    }

    /** @param array<int,array<int,bool>> $modules @param array<int,array<int,bool>> $function */
    private function drawFormat(array &$modules, array &$function, int $mask): void
    {
        $data = (1 << 3) | $mask;
        $remainder = $data;
        for ($i = 0; $i < 10; $i++) {
            $remainder = ($remainder << 1) ^ (($remainder >> 9) * 0x537);
        }
        $bits = (($data << 10) | $remainder) ^ 0x5412;
        for ($i = 0; $i <= 5; $i++) { $this->set($modules, $function, 8, $i, (($bits >> $i) & 1) !== 0); }
        $this->set($modules, $function, 8, 7, (($bits >> 6) & 1) !== 0);
        $this->set($modules, $function, 8, 8, (($bits >> 7) & 1) !== 0);
        $this->set($modules, $function, 7, 8, (($bits >> 8) & 1) !== 0);
        for ($i = 9; $i < 15; $i++) { $this->set($modules, $function, 14 - $i, 8, (($bits >> $i) & 1) !== 0); }
        for ($i = 0; $i < 8; $i++) { $this->set($modules, $function, self::SIZE - 1 - $i, 8, (($bits >> $i) & 1) !== 0); }
        for ($i = 8; $i < 15; $i++) { $this->set($modules, $function, 8, self::SIZE - 15 + $i, (($bits >> $i) & 1) !== 0); }
        $this->set($modules, $function, 8, self::SIZE - 8, true);
    }

    /** @param array<int,array<int,bool>> $modules @param array<int,array<int,bool>> $function */
    private function set(array &$modules, array &$function, int $x, int $y, bool $dark): void
    {
        $modules[$y][$x] = $dark;
        $function[$y][$x] = true;
    }
}
