<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\Profile;

class TelegramService
{
    public static function sendMessage($message, $pro_id)
    {

        $profile = Profile::find($pro_id);

        // return $profile;
        $token = $profile->bot_token;
        $chatId = $profile->chat_id;

        if(!$token||!$chatId){return "Error";}

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        return Http::post($url, [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
            'inline_keyboard' => [
                [
                    [
                        'text' => '🌐 View Order',
                        'url'  => 'http://www.chomnenhapp.com/dashboard/order-tracking'
                    ]
                ]
            ]
        ])
        ]);
    }
}
