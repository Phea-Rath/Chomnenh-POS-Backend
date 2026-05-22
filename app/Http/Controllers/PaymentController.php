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

class PaymentController extends Controller
{

    public function getQrCode(Request $request)
    {
        $user = auth()->user();
        $proId = $user->profile_id;
        $profile = DB::table('profiles')->find($proId);

        $amount =1?? $request->input('amount', 1.00);
        $currency = $request->input('currency', 'USD') === 'KHR'
            ? KHQRData::CURRENCY_KHR
            : KHQRData::CURRENCY_USD;

        // 2. Build the KHQR individual/merchant details payload
        $individualInfo = new IndividualInfo(
            bakongAccountID: 'tep_phhearat@bkrt',
            merchantName: 'Ratha Yen',
            merchantCity: 'Phnom Penh',
            currency: $currency,
            amount: $amount
        );

        // 3. Generate the response string and its MD5 hash
        $khqrResponse = BakongKHQR::generateIndividual($individualInfo);
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
            $token = env('BAKONG_TOKEN');
            if (!$token) {
                return response()->json(['message' => 'Missing BAKONG_TOKEN'], 500);
            }

            $bakong = new BakongKHQR($token);
            $result = $bakong->checkTransactionByMD5($md5);

            $responseCode = $result['responseCode'] ?? null;
            $statusCode = $result['status']['code'] ?? null;
            $isPaid = ($responseCode === 0 || $responseCode === '0' || $statusCode === 0 || $statusCode === '0');

            return response()->json([
                'status' => $isPaid ? 'PAID' : 'PENDING',
                'detail' => $result['data'] ?? null,
                'raw' => $result,
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

