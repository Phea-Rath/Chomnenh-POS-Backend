<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    public static function sendMessage($message)
    {
        $token = "8324321383:AAFB4jzi-c3gf0wJBstw1N2KERsiRvqdyqA";
        $chatId = "-1003201101831";

        if(!$token||!$chatId){return "Error";}

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        return Http::post($url, [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }
}
