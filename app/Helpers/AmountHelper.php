<?php
namespace App\Helpers;

class AmountHelper{

    public static function format(mixed $value,string $thousandsSeparator = ','): string{
        $value = number_format($value, 8, '.', $thousandsSeparator);
        $dotIdx = strpos($value, '.');
        if ($dotIdx !== false) {
            $left = substr($value, 0, $dotIdx);
            $right = substr($value, $dotIdx + 1);

            $value = $left;
            if ((int) $right > 0) {
                $value .= '.' . rtrim($right, '0');
            }
        }
        return $value;
    }

    public static function formatCurrency(mixed $value,string $thousandsSeparator = ','): string{
        $dotIdx = strpos($value, '.');
        if ($dotIdx !== false) {
            $left = substr($value, 0, $dotIdx);
            $right = substr($value, $dotIdx + 1);

            $value = number_format($left, 0, '', $thousandsSeparator);
            if ((int) $right > 0) {
                if (strlen($right) === 1) {
                    $value .= '.' . $right . '0';
                } else {
                    $value .= '.' . substr($right, 0, 2);
                }
            }
        } else {
            $value = number_format($value, 2, '.', $thousandsSeparator);
        }
        return $value;
    }

}
