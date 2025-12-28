<?php

// app/Http/Controllers/Api/OtpController.php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Http;

use App\Http\Controllers\Controller;
use App\Models\OtpVerification;
use Illuminate\Http\Request;
use Infobip\Configuration;
use Infobip\ApiException;
use Infobip\Model\SmsRequest;
use Infobip\Model\SmsDestination;
use Infobip\Model\SmsMessage;
use Infobip\Api\SmsApi;
use Infobip\Model\SmsAdvancedTextualRequest;
use Infobip\Model\SmsTextContent;
use Infobip\Model\SmsTextualMessage;
use Twilio\Rest\Client;

class OtpController extends Controller
{
    protected function generateOtp()
    {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // public function sendOtp()
    // {
    //     // $request->validate([
    //     //     'phone_number' => 'required|string'
    //     // ]);

    //     // Clean and format phone number
    //     // $phoneNumber = preg_replace('/[^0-9]/', '', $request->phone_number);

    //     // Generate OTP
    //     // $otp = $this->generateOtp();
    //     // $expiresAt = now()->addMinutes(5); // OTP expires in 5 minutes

    //     // Save OTP to database
    //     // OtpVerification::updateOrCreate(
    //     //     ['phone_number' => $phoneNumber],
    //     //     ['otp' => $otp, 'expires_at' => $expiresAt]
    //     // );
    //     $configuration = new Configuration(
    //         host: 'nmjj9e.api.infobip.com',
    //         apiKey: '576b7a2f3d45809207996ba2d96aa0bf-82ab5af2-fd1f-4d61-acf1-27750ff94ba5'
    //     );

    //     $sendSmsApi = new SmsApi(config: $configuration);

    //     $message = new SmsTextualMessage(
    //         destinations: [
    //             new SmsDestination(
    //                 to: '+855979772133'
    //             )
    //         ],
    //         from: 'Chomnenh POS',
    //         text: `Your verification code is`

    //     );

    //     $request = new SmsAdvancedTextualRequest(messages: [$message]);

    //     try {
    //         $smsResponse = $sendSmsApi->sendSmsMessage($request);
    //         return response()->json([
    //             'message' => 'OTP sent successfully',
    //             'response' => $smsResponse
    //         ]);
    //     } catch (ApiException $e) {
    //         // HANDLE THE EXCEPTION
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }


public function sendOtp(Request $request)
{
    // Validate request
    $request->validate([
        'phone_number' => 'required|string'
    ]);

    // Clean and format phone number
    $phoneNumber = preg_replace('/[^0-9]/', '', $request->phone_number);
    if (str_starts_with($phoneNumber, '0')) {
        $phoneNumber = substr($phoneNumber, 1); // remove leading 0
    }
    if (!str_starts_with($phoneNumber, '855')) {
        $phoneNumber = '855' . $phoneNumber;
    }

    // Generate OTP
    $otp = rand(100000, 999999);
    $expiresAt = now()->addMinutes(5);

    // Save OTP
    OtpVerification::updateOrCreate(
        ['phone_number' => $phoneNumber],
        ['otp' => $otp, 'expires_at' => $expiresAt]
    );

    // Compose message
    $message = "Your verification code is {$otp}";

    try {
        // $mocean = new \Mocean\Client(
        // new \Mocean\Client\Credentials\Basic(['apiToken' => 'API_TOKEN_HERE'])
// );
        // Send SMS using MoceanAPI with Bearer token
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('MOCEAN_BEARER_TOKEN', 'apit-9MsHWwdEO2VOaZQDw5aLXO29FwuBuEbt-grZ0N'),
        ])->asForm()->post('https://rest.moceanapi.com/rest/2/sms', [
            'mocean-from' => 'ChomnenhApp',
            'mocean-to'   => $phoneNumber,
            'mocean-text' => $message,
            'mocean-resp-format' => 'json'
        ]);

        // $result = $mocean->message()->send([
        //     'mocean-to' => $phoneNumber,
        //     'mocean-from' => 'ChomnenhPOS',
        //     'mocean-text' => $message,
        //     'mocean-resp-format' => 'json'
        // ]);

        $responseData = $response;

        // Handle response
        if (isset($responseData['messages'][0]['status']) && $responseData['messages'][0]['status'] == '0') {
            return response()->json([
                'message' => 'OTP sent successfully',
                'otp' => $otp, // For dev/testing — remove in production
                'response' => $responseData
            ]);
        } else {
            return response()->json([
                'error' => 'SMS sending failed',
                'response' => $responseData
            ], 500);
        }

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}


    // public function sendOtp(Request $request)
    // {
    //     // Validate request
    //     $request->validate([
    //         'phone_number' => 'required|string'
    //     ]);

    //     // Clean and format phone number
    //     // $phoneNumber = '855979772133';
    //     $phoneNumber = preg_replace('/[^0-9]/', '', $request->phone_number);
    //     // return response()->json($phoneNumber);

    //     // Generate OTP
    //     $otp = rand(100000, 999999); // 6-digit OTP
    //     $expiresAt = now()->addMinutes(5);


    //     // Infobip config (better move these into .env)
    //     $configuration = new Configuration(
    //         host: 'https://nmjj9e.api.infobip.com',
    //         apiKey: env('INFOBIP_API_KEY', '576b7a2f3d45809207996ba2d96aa0bf-82ab5af2-fd1f-4d61-acf1-27750ff94ba5')
    //     );

    //     $sendSmsApi = new SmsApi(config: $configuration);

    //     $message = new SmsTextualMessage(
    //         destinations: [
    //             new SmsDestination(
    //                 to: '+' . $phoneNumber  // format as international
    //             )
    //         ],
    //         from: 'ChomnenhPOS',
    //         text: "Your verification code is {$otp}"
    //     );

    //     $smsRequest = new SmsAdvancedTextualRequest(messages: [$message]);
    //     // Save OTP to database
    //     OtpVerification::updateOrCreate(
    //         ['phone_number' => $phoneNumber],
    //         ['otp' => $otp, 'expires_at' => $expiresAt]
    //     );

    //     try {
    //         $smsResponse = $sendSmsApi->sendSmsMessage($smsRequest);

    //         return response()->json([
    //             'message' => 'OTP sent successfully',
    //             'response' => $smsResponse
    //         ]);
    //     } catch (ApiException $e) {
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'otp' => 'required|string'
        ]);

        $phoneNumber = preg_replace('/[^0-9]/', '', $request->phone_number);
        $otp = $request->otp;

        $verification = OtpVerification::where('phone_number', $phoneNumber)
            ->where('otp', $otp)
            ->where('expires_at', '>', now())
            ->first();

        if ($verification) {
            $verification->delete();
            return response()->json(['message' => 'OTP verified successfully']);
        }

        return response()->json(['error' => 'Invalid OTP or expired'], 400);
    }
}
