<?php

declare(strict_types=1);

namespace TimeFrontiers;

/** Display-only currency symbol hints; ISO currency code remains authoritative. */
final class CurrencySymbols {
  private const MAP = [
    'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'CNY' => '¥',
    'INR' => '₹', 'RUB' => '₽', 'KRW' => '₩', 'BRL' => 'R$', 'CAD' => 'C$',
    'AUD' => 'A$', 'CHF' => 'CHF', 'SEK' => 'kr', 'NOK' => 'kr', 'DKK' => 'kr',
    'PLN' => 'zł', 'MXN' => 'MX$', 'SGD' => 'S$', 'HKD' => 'HK$', 'NZD' => 'NZ$',
    'TRY' => '₺', 'ZAR' => 'R', 'NGN' => '₦', 'GHS' => 'GH₵', 'KES' => 'KSh',
    'EGP' => 'E£', 'AED' => 'د.إ', 'SAR' => '﷼', 'ILS' => '₪', 'THB' => '฿',
    'IDR' => 'Rp', 'MYR' => 'RM', 'PHP' => '₱', 'VND' => '₫', 'UAH' => '₴',
    'ARS' => 'AR$', 'CLP' => 'CLP$', 'COP' => 'COL$', 'PEN' => 'S/', 'PKR' => '₨',
    'BDT' => '৳', 'LKR' => 'Rs', 'NPR' => '₨', 'CRC' => '₡', 'DOP' => 'RD$',
    'JMD' => 'J$', 'TTD' => 'TT$', 'XCD' => 'EC$', 'BBD' => 'Bds$', 'BMD' => 'BD$',
    'BZD' => 'BZ$', 'BSD' => 'B$', 'KYD' => 'CI$', 'FJD' => 'FJ$', 'XPF' => '₣',
    'XAF' => 'FCFA', 'XOF' => 'CFA', 'MAD' => 'د.م.', 'TND' => 'د.ت', 'DZD' => 'د.ج',
    'LYD' => 'ل.د', 'IQD' => 'ع.د', 'JOD' => 'د.ا', 'KWD' => 'د.ك', 'BHD' => '.د.ب',
    'OMR' => '﷼', 'QAR' => '﷼', 'YER' => '﷼', 'LBP' => 'ل.ل', 'SYP' => 'ل.س',
    'SDG' => 'ج.س.', 'ETB' => 'Br', 'TZS' => 'TSh', 'UGX' => 'USh', 'RWF' => 'FRw',
    'BIF' => 'FBu', 'MZN' => 'MT', 'ZMW' => 'ZK', 'MWK' => 'MK', 'AOA' => 'Kz',
    'NAD' => 'N$', 'SZL' => 'L', 'LSL' => 'M', 'BWP' => 'P', 'MUR' => '₨',
    'SCR' => '₨', 'MVR' => 'Rf',
  ];

  public static function get(string $currencyCode):string {
    $currencyCode = \strtoupper(\trim($currencyCode));
    return self::MAP[$currencyCode] ?? $currencyCode;
  }
}
