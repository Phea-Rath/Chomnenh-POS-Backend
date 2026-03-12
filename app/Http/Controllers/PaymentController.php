<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DB;
use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;
use App\Services\AbaPayWayService;

class PaymentController extends Controller
{
    public function getQrCode(Request $request)
    {
        $user = auth()->user();
        $proId = $user->profile_id;
        $profile = DB::table('profiles')->find($proId);

        $amount = $request->query('amount', '0');
        $currency = strtoupper($request->query('currency', 'KHR'));
        $currencyCode = $currency === 'USD' ? KHQRData::CURRENCY_USD : KHQRData::CURRENCY_KHR;

        if (!is_numeric($amount) || $amount <= 0) {
            return response()->json(['message' => 'Invalid amount'], 422);
        }

        $merchant = new IndividualInfo(
            bakongAccountID: env('ABA_BAKONG_ID', 'abaakhppxxx@abaa'),
            merchantName: 'RATHA YEN',
            merchantCity: env('ABA_MERCHANT_CITY', 'Phnom Penh'),
            currency: $currencyCode,
            // amount: (string) $amount
            amount: "100"
        );

        $qrResponse = BakongKHQR::generateIndividual($merchant);

        return response()->json([
            'qr' => $qrResponse->data['qr'] ?? null,
            'md5' => $qrResponse->data['md5'] ?? null,
        ]);
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


    public function checkout(Request $request) {
        $transactionId = time(); // លេខសម្គាល់ប្រតិបត្តិការ
        $amount = "10.00";
        $firstName = "Phearat";
        $lastName = "Tep";
        $phone = "012345678";
        $email = "customer@email.com";
        $items = base64_encode(json_encode([['name' => 'Item 1', 'quantity' => '1', 'price' => '10.00']]));

        // បង្កើត Hash តាមលំដាប់លំដោយរបស់ ABA
        $req_time = date('YmdHis');
        $hashStr = env('ABA_PAYWAY_MERCHANT_ID') . $transactionId . $amount . $items . $firstName . $lastName . $email . $phone . "purchase" . $req_time;
        $hash = AbaPayWayService::getHash($hashStr);

        return response()->json([
            'api_url' => env('ABA_PAYWAY_API_URL'),
            'merchant_id' => env('ABA_PAYWAY_MERCHANT_ID'),
            'tran_id' => $transactionId,
            'amount' => $amount,
            'hash' => $hash,
            'req_time' => $req_time,
            // ... ទិន្នន័យផ្សេងទៀត
        ]);
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

