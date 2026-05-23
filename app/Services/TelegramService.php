<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\Profile;

class TelegramService
{

    public function testTelegram(){
        $message = "Hello, this is a test message from TelegramService.";
        $keyboard = [
            [
                [
                    'text' => 'Visit Website',
                    'url' => 'https://www.chomnenhapp.com'
                ]
            ]
        ];
        $chat_id = '1531201806'; // Replace with your chat
        // dd(env('TELEGRAM_BOT_TOKEN'));
        return self::sendMessage($message, 1, $keyboard, $chat_id);
    }

    public static function sendMessage($message, $pro_id, $keyboard, $chat_id)
    {

        $profile = Profile::find($pro_id);

        // return $profile;
        $token = env('TELEGRAM_BOT_TOKEN')??$profile->bot_token;
        $chatId = $chat_id ?? $profile->chat_id;

        if(!$token||!$chatId){return "Error";}

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        return Http::post($url, [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
            'inline_keyboard' => $keyboard
        ])
        ]);
    }

    public static function sendPhoto($caption, $photoUrl, $pro_id)
    {
        $profile = Profile::find($pro_id);
        $token = $profile->bot_token;
        $chatId = $profile->chat_id;

        if (!$token || !$chatId) {
            return "Error";
        }

        $url = "https://api.telegram.org/bot{$token}/sendPhoto";

        return Http::post($url, [
            'chat_id' => $chatId,
            'photo' => $photoUrl,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ]);
    }
}
