<?php
namespace App\Services;

class AbaPayWayService {
    public static function getHash($str) {
        $hash = hash_hmac('sha512', $str, env('ABA_PAYWAY_API_KEY'));
        return base64_encode($hash);
    }
}

