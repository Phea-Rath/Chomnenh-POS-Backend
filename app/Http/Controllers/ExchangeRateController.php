<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    /**
     * Show exchange rate by profile_id
     */
    public function show($profile_id)
    {
        $exchangeRate = ExchangeRate::find($profile_id);

        if (!$exchangeRate) {
            return response()->json([
                'message' => 'Exchange rate not found for this profile.'
            ], 404);
        }

        return response()->json([
            'message' => 'Exchange rate geted successfully.',
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

        if (!$exchangeRate) {
            return response()->json([
                'message' => 'Exchange rate not found for this profile.'
            ], 404);
        }

        $validated = $request->validate([
            'usd_to_khr' => 'required|numeric|min:0'
        ]);

        $exchangeRate->update($validated);

        return response()->json([
            'message' => 'Exchange rate updated successfully.',
            'status' => 200,
            'data' => $exchangeRate
        ]);
    }
}
