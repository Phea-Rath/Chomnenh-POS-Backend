<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DB;
use KHQR\BakongKHQR;
use KHQR\Models\IndividualInfo;
use KHQR\Config\Constants;
use KHQR\Helpers\KHQRData;
use App\Services\AbaPayWayService;
use Http;

class PaymentController extends Controller
{

    public function getQrCode(Request $request)
    {
        $user = auth()->user();
        $proId = $user->profile_id;
        $profile = DB::table('profiles')->find($proId);

        $apiUrl = env('BAKONG_GENERATE_QR_URL', 'https://api.bakongrelay.com/v1/generate_qr');
        // dd($apiUrl);

        $amount = 0.01 ?? $request->input('amount', 1.00);
        $response = Http::withHeaders([
            'content-type' => 'application/json',
        ])->post($apiUrl, [
            "account_id"=> "tep_phhearat@bkrt",
            "bank_account" => "tep_phhearat@bkrt",
            "merchant_name" => "TEP PHEARAT",
            "merchant_city" => "Phnom Penh",
            "amount" => $amount,
            "currency" => "USD",
            "expiration" => 300, // QR code validity in seconds
        ]);
        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to Bakong Server.'
            ], 500);
        }

        $result = $response->json();
        // 3. Verify status code (0 means success)
        if (isset($result['responseCode']) && $result['responseCode'] === 0) {
            // This will exactly produce the raw string you provided
            $qrString = $result['data']['qr'];

            return response()->json([
                'success' => true,
                'qr' => $qrString,
                'md5' => $result['data']['md5'], // For transaction tracking
                'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrString)
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to build standard KHQR structure.'
        ], 400);
        // dd($khqrResponse);
        if (($khqrResponse->status['code'] ?? null) === 0) {
            $qrString = $khqrResponse->data['qr'];
            $md5Hash = $khqrResponse->data['md5']; // Bakong tracks transaction status via this MD5

            return response()->json([
                'success' => true,
                'qr' => $qrString,
                'md5' => $md5Hash,
                'amount' => $amount
            ]);
        }
        // return response()->json([
        //     'qr' => $qrResponse->data['qr'] ?? null,
        //     'md5' => $qrResponse->data['md5'] ?? null,
        // ]);

    }

    public function sendQrToTelegram(Request $request)
    {
        $user = auth()->user();
        $proId = $user->profile_id;
        $qr = $request->qr_string;
        $amount = $request->amount;
        $currency = $request->currency;
        $qrImage = $request->qr_image; // Base64 image string

        $caption = "💰 <b>New QR Payment Request</b>\n\n" .
                   "💵 <b>Amount:</b> " . number_format($amount, 2) . " {$currency}\n" .
                   "<i>Please scan the QR code to pay.</i>";

        if ($qrImage) {
            // Handle base64 image
            $imageData = substr($qrImage, strpos($qrImage, ',') + 1);
            $imageData = base64_decode($imageData);

            // Temporary file to send to Telegram
            $tempFile = tempnam(sys_get_temp_dir(), 'qr_');
            file_put_contents($tempFile, $imageData);

            $profile = DB::table('profiles')->find($proId);
            $token = $profile->bot_token;
            $chatId = $profile->chat_id;

            if ($token && $chatId) {
                $url = "https://api.telegram.org/bot{$token}/sendPhoto";
                \Illuminate\Support\Facades\Http::attach(
                    'photo', $imageData, 'qrcode.png'
                )->post($url, [
                    'chat_id' => $chatId,
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                ]);
            }

            unlink($tempFile);
        } else {
            \App\Services\TelegramService::sendMessage($caption, $proId);
        }

        return response()->json(['message' => 'QR sent to Telegram successfully']);
    }

    public function verifyPayment($md5)
    {
        try {

            $token = env('BAKONG_TOKEN','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJkYXRhIjp7ImlkIjoiZWMyZGViMGU5YmYwNGMxMiJ9LCJpYXQiOjE3ODA0NjI0NjgsImV4cCI6MTc4ODIzODQ2OH0.R9ZxWBTpCbOlieFyzj8uk9ev04aKU01qy40hwLGh0uY');
            $apiUrl = env('BAKONG_API_URL', 'https://api-bakong.nbc.gov.kh');

            // Send an authorized request to Bakong API
            $response = Http::withHeaders([
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json'
            ])->post($apiUrl . '/v1/check_transaction_by_md5', [
                'md5' => $md5
            ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to connect to Bakong Server.'
                ], 500);
            }

            $result = $response->json();

            // Bakong API usually returns status code 0 for a successfully processed transaction
            if (isset($result['responseCode']) && $result['responseCode'] === 0) {
                return response()->json([
                    'paid' => true,
                    'message' => 'Payment received successfully!',
                    'data' => $result['data']
                ],200);
            }

            return response()->json([
                'paid' => false,
                'message' => 'Payment is still pending.'
            ]);
        } catch (\Exception $e) {
            Log::error("Bakong Verify Error: " . $e->getMessage());
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }



    public function getPaymentLink(Request $request)
    {
        $merchant_id = env('ABA_PAYWAY_MERCHANT_ID');
        $api_key = env('ABA_PAYWAY_API_KEY');

        $req_time = now()->format('YmdHis');
        $tran_id = time(); // លេខកូដប្រតិបត្តិការ
        $amount = $request->amount; // ឧទាហរណ៍: 10.00

        // បង្កើត Hash តាមលំដាប់លំដោយរបស់ ABA
        $hash_str = $req_time . $merchant_id . $tran_id . $amount;
        $hash = base64_encode(hash_hmac('sha256', $hash_str, $api_key, true));

        return response()->json([
            'hash' => $hash,
            'tran_id' => $tran_id,
            'req_time' => $req_time,
            'merchant_id' => $merchant_id,
            'amount' => $amount,
            'api_url' => env('ABA_PAYWAY_API_URL')]);
        }




    public function callback(Request $request) {
        // ១. ទទួលទិន្នន័យពី ABA
        $status = $request->input('status');
        $tran_id = $request->input('tran_id');

        // ២. ឆែកមើលថាតើការបង់ប្រាក់ជោគជ័យឬអត់
        if ($status == 0) { // 0 មានន័យថាជោគជ័យ
            // Update order status ក្នុង Database របស់អ្នក
            // $order = Order::where('transaction_id', $tran_id)->first();
            // $order->update(['is_paid' => true]);

            return response()->json(['status' => 'OK']);
        }

        return response()->json(['status' => 'FAILED']);
    }
}

