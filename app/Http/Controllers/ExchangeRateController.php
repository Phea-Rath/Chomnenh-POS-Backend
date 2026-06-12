<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ExchangeRateController extends Controller
{
    /**
     * Show exchange rate by profile_id
     */
   public function show(Request $request, $profile_id)
    {
        $type = $request->get('type', 'custom');

        if ($type === 'auto') {
            $response = Http::get('https://www.nbc.gov.kh/api/exRate.php');

            if (! $response->successful()) {
                return response()->json([
                    'message' => 'Failed to fetch exchange rates from NBC.'
                ], 500);
            }

            $xml = simplexml_load_string(
                $response->body(),
                'SimpleXMLElement',
                LIBXML_NOCDATA
            );
            $data = json_decode(json_encode($xml), true);
            $usd = collect($data['ex'])->firstWhere('key','USD/KHR');
            $result = [
                'profile_id' => $profile_id,
                'usd_to_khr' => $usd['average'] ?? null,
                'created_at' => $usd['date'],
                'updated_at' => $usd['date'],
            ];

            return response()->json([
                'message' => 'Exchange rate fetched from NBC.',
                'status' => 200,
                'data' => $result
            ]);
        }

        $exchangeRate = ExchangeRate::find($profile_id);

        if (! $exchangeRate) {
            return response()->json([
                'message' => 'Exchange rate not found for this profile.'
            ], 404);
        }

        return response()->json([
            'message' => 'Exchange rate fetched successfully.',
            'status' => 200,
            'data' => $exchangeRate
        ]);
    }

    /**
     * Update exchange rate by profile_id
     */
    public function update(Request $request, $profile_id)
    {
        $exchangeRate = ExchangeRate::find($profile_id);
        $validated = $request->validate([
            'usd_to_khr' => 'required|numeric|min:0'
        ]);

        if (!$exchangeRate) {
            ExchangeRate::create([
                'profile_id' => $profile_id,
                'usd_to_khr' => $validated['usd_to_khr'] ?? 4000,
            ]);
        }


        $exchangeRate->update($validated);

        return response()->json([
            'message' => 'Exchange rate updated successfully.',
            'status' => 200,
        ]);
    }
}
