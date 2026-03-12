<?php

namespace App\Helpers;

class KhqrHelper
{
    public static function generate($bakongId, $name, $amount, $currency = 'USD')
    {
        $currencyCode = ($currency === 'USD') ? '840' : '116';

        $data = "000201"; // Payload Indicator
        $data .= "010212"; // 12 សម្រាប់ Dynamic QR (មានចំនួនទឹកប្រាក់)

        // --- Tag 30: ព័ត៌មានគណនី (សំខាន់ខ្លាំង) ---
        // ABA និង Bakong ត្រូវការ GUID "jp.org.jsf" ជាដាច់ខាត
        // $guid = "0009jp.org.jsf";
        $accId = "01" . str_pad(strlen($bakongId), 2, '0', STR_PAD_LEFT) . $bakongId;
        $merchantType = "0203001"; // បន្ថែម Merchant Type ដើម្បីឱ្យ ABA ស្គាល់ច្បាស់

        $merchantInfo = $accId . $merchantType;
        $data .= "30" . str_pad(strlen($merchantInfo), 2, '0', STR_PAD_LEFT) . $merchantInfo;

        $data .= "52045999";
        $data .= "5303" . $currencyCode;
        $data .= "54" . str_pad(strlen($amount), 2, '0', STR_PAD_LEFT) . $amount;
        $data .= "5802KH";
        $data .= "59" . str_pad(strlen($name), 2, '0', STR_PAD_LEFT) . $name;
        $data .= "6010Phnom Penh";
        $data .= "6304"; // ចាប់ផ្តើម CRC

        return $data . self::crc16($data);
    }

    private static function crc16($data)
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $x = (($crc >> 8) ^ ord($data[$i])) & 0xFF;
            $x ^= $x >> 4;
            $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
