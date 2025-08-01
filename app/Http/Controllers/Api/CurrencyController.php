<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CurrencyController extends Controller
{
    public function getHistory(Request $request)
{
    $validated = $request->validate([
        'from' => 'required|string|size:3',
        'to' => 'required|string|size:3',
        'start_date' => 'required|date_format:Y-m-d',
    ]);

    try {
        $fromCurrency = $validated['from'];
        $toCurrency = $validated['to'];
        $startDateString = $validated['start_date'];
        $endDateString = (new \DateTime())->format('Y-m-d');

        $apiUrl = "https://api.frankfurter.app/{$startDateString}..{$endDateString}?from={$fromCurrency}&to={$toCurrency}";

        $response = Http::get($apiUrl);

        if ($response->failed()) {
            return response()->json(['success' => false, 'error' => 'Could not connect to Frankfurter API.'], 500);
        }

        $data = $response->json();

        if (isset($data['error'])) {
            return response()->json(['success' => false, 'error' => $data['error']], 422);
        }

        $historyData = [];
        if (isset($data['rates'])) {
            foreach ($data['rates'] as $dateString => $rateData) {
                if (isset($rateData[$toCurrency])) {
                    $date = new \DateTime($dateString);
                    $historyData[] = [
                        'date' => $date->format('Y-m-d'),
                        'rate' => $rateData[$toCurrency]
                    ];
                }
            }
        }

        usort($historyData, fn($a, $b) => strcmp($a['date'], $b['date']));

        return response()->json(['success' => true, 'history' => $historyData]);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Frankfurter getHistory error: ' . $e->getMessage());
        return response()->json(['success' => false, 'error' => 'An unexpected server error occurred.'], 500);
    }
}

    public function getPopularRates()
    {
        try {
            $apiKey = env('EXCHANGE_RATE_API_KEY');
            if (!$apiKey) {
                return response()->json(['success' => false, 'error' => 'API key is not configured.'], 500);
            }

            $baseCurrency = 'USD';
            $response = Http::get("https://v6.exchangerate-api.com/v6/{$apiKey}/latest/{$baseCurrency}");

            if ($response->failed() || $response->json()['result'] === 'error') {
                return response()->json(['success' => false, 'error' => 'Could not retrieve data list.'], 500);
            }

            $allRates = $response->json()['conversion_rates'];

            $popularCurrencies = ['USD', 'EUR', 'JPY', 'GBP', 'CNY', 'AUD', 'CAD', 'CHF', 'HKD', 'SGD', 'SEK', 'KRW', 'NOK', 'NZD', 'INR', 'MXN', 'TWD', 'ZAR', 'BRL', 'DKK'];
            $ratesList = [];

            foreach ($popularCurrencies as $currency) {
                if (isset($allRates[$currency])) {
                    $ratesList[] = [
                        'from' => $baseCurrency,
                        'to' => $currency,
                        'rate' => $allRates[$currency]
                    ];
                }
            }

            return response()->json(['success' => true, 'rates' => $ratesList]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('getPopularRates error:' . $e->getMessage());

            return response()->json(['success' => false, 'error' => 'An unexpected server error occurred.'], 500);
        }
    }

    public function convert(Request $request)
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
            'amount' => 'required|numeric|min:0'
        ]);

        try {
            $fromCurrency = $validated['from'];
            $toCurrency = $validated['to'];
            $amount = $validated['amount'];

            $apiKey = env('EXCHANGE_RATE_API_KEY');

            $response = Http::get("https://v6.exchangerate-api.com/v6/{$apiKey}/pair/{$fromCurrency}/{$toCurrency}");

            // check if the request failed
            if ($response->failed() || $response->json()['result'] === 'error') {

                return response()->json(['success' => false, 'error' => 'Could not retrieve exchange rate.'], 500);
            }

            // if successful, get data
            $data = $response->json();
            $rate = $data['conversion_rate'];

            // calculate the exchange rate
            $convertedAmount = $amount * $rate;

            return response()->json([
                'success' => true,
                'rate' => $rate,
                'convertedAmount' => round($convertedAmount, 4),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'An unexpected error occurred.'], 500);
        }
    }
}
