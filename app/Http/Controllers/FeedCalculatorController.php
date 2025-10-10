<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FeedCalculatorController extends Controller
{
    //
    public function feedCalculator(Request $request)
{
    $category = ucfirst(strtolower(trim($request->livestock_category)));

    switch ($category) {
        case 'Poultry':
            $birdType = ucfirst(strtolower($request->bird_type ?? 'Unknown'));
            $feedType = ucfirst(strtolower($request->feed_type ?? 'Unknown'));
            $numBirds = $request->no_of_bird ?? 0;
            $weeks = $request->no_of_week ?? 0;

            if (in_array($feedType, ['Starter', 'Finisher'])) {
                $feedKg = $weeks * $numBirds;
            } elseif ($feedType === 'Chick mash') {
                $feedKg = 1.8 * $numBirds;
            } elseif ($feedType === 'Growers mash') {
                $feedKg = 6.1 * $numBirds;
            } elseif (in_array($feedType, ['Layer mash', 'Layer mash/pellet'])) {
                $feedKg = 8.1 * $numBirds;
            } else {
                return response()->json(['message' => 'Invalid or missing poultry feed type.'], 400);
            }

            return response()->json([
                'livestock_category' => $category,
                'bird_type' => $birdType,
                'feed_type' => ucfirst($feedType),
                'number_of_birds' => $numBirds,
                'feed_required' => round($feedKg, 2) . ' kg'
            ]);

        case 'Fish':
            $numFish = $request->no_of_fish ?? 0;
            $fishSize = $request->fish_size ?? 0; // e.g., pellet size
            $feedRatio = 0;

            if ($fishSize <= 2) {
                $feedRatio = 0.015;
            } elseif ($fishSize <= 3) {
                $feedRatio = 0.03;
            } elseif ($fishSize <= 4.5) {
                $feedRatio = 0.15;
            } elseif ($fishSize <= 6) {
                $feedRatio = 0.24;
            } else {
                $feedRatio = 0.455;
            }

            $feedKg = $numFish * $feedRatio;

            return response()->json([
                'livestock_category' => $category,
                'fish_size' => $fishSize . ' mm',
                'number_of_fish' => $numFish,
                'feed_required' => round($feedKg, 2) . ' kg'
            ]);

        case 'Pig':
            $numPigs = $request->no_of_pig ?? 0;
            // Assume average 2.0 kg feed per pig/day as baseline
            $feedKg = $numPigs * 2.0;

            return response()->json([
                'livestock_category' => $category,
                'number_of_pigs' => $numPigs,
                'feed_required' => round($feedKg, 2) . ' kg'
            ]);

        default:
            return response()->json([
                'message' => 'Invalid livestock category. Please choose Poultry, Fish, or Pig.'
            ], 400);
    }
}

}
