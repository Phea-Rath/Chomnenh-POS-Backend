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
                    'status' => 200,
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




    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'otp' => 'required|string'
        ]);

        $phoneNumber = preg_replace('/[^0-9]/', '', $request->phone_number);
        if (str_starts_with($phoneNumber, '0')) {
            $phoneNumber = substr($phoneNumber, 1); // remove leading 0
        }
        if (!str_starts_with($phoneNumber, '855')) {
            $phoneNumber = '855' . $phoneNumber;
        }
        $otp = $request->otp;

        $verification = OtpVerification::where('phone_number', $phoneNumber)
            ->where('otp', $otp)
            ->where('expires_at', '>', now())
            ->first();

        if ($verification) {
            $verification->delete();
            return response()->json([
                'message' => 'OTP verified successfully',
                'status' => 200
            ]);
        }

        return response()->json(['error' => 'Invalid OTP or expired'], 400);
    }
}
