<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DiseasePredictorController extends Controller
{
    //
    public function diseasePredictor(Request $request)
    {
        $category = strtolower(trim($request->livestock_category));
        $symptoms = array_map('strtolower', $request->symptoms ?? []);

        // Disease knowledge base (expand anytime)
        $diseaseDatabase = [
            'dog' => [
                [
                    'name' => 'Parasitic Infections',
                    'description' => 'Parasites such as roundworms, hookworms, and giardia can cause diarrhea, weight loss, and inflammation in the digestive tract.',
                    'symptoms' => ['loss of appetite', 'weight loss', 'diarrhea', 'inflammation', 'vomiting']
                ],
                [
                    'name' => 'Kennel Cough',
                    'description' => 'A contagious respiratory disease causing coughing, sneezing, and nasal discharge, often due to viral or bacterial infection.',
                    'symptoms' => ['coughing', 'sneezing', 'nasal discharge', 'loss of appetite']
                ],
                [
                    'name' => 'Parvovirus',
                    'description' => 'A severe viral infection that causes vomiting, bloody diarrhea, and extreme dehydration in dogs, especially puppies.',
                    'symptoms' => ['vomiting', 'diarrhea', 'lethargy', 'loss of appetite', 'fever']
                ]
            ],
            'poultry' => [
                [
                    'name' => 'Coccidiosis',
                    'description' => 'A parasitic infection in the intestines causing bloody droppings, weakness, and loss of appetite.',
                    'symptoms' => ['bloody droppings', 'depression', 'ruffled feathers', 'loss of appetite']
                ],
                [
                    'name' => 'Newcastle Disease',
                    'description' => 'A viral disease that causes sneezing, coughing, green droppings, and nervous symptoms in birds.',
                    'symptoms' => ['sneezing', 'coughing', 'green droppings', 'loss of appetite']
                ]
            ],
            'goat' => [
                [
                    'name' => 'Parasitic Infections',
                    'description' => 'Worm infestations leading to weight loss, diarrhea, and rough hair coat.',
                    'symptoms' => ['diarrhea', 'weight loss', 'rough coat', 'loss of appetite']
                ],
                [
                    'name' => 'Pneumonia',
                    'description' => 'A bacterial or viral infection causing coughing, nasal discharge, fever, and weakness.',
                    'symptoms' => ['coughing', 'nasal discharge', 'fever', 'loss of appetite']
                ]
            ]
            // add swine, cow, cat, grasscutter, etc.
        ];

        // Validate livestock type
        if (!isset($diseaseDatabase[$category])) {
            return response()->json([
                'message' => 'Invalid or unsupported livestock category provided.'
            ], 400);
        }

        $diseases = $diseaseDatabase[$category];
        $bestMatch = null;
        $bestScore = 0;

        foreach ($diseases as $disease) {
            $matches = array_intersect($symptoms, $disease['symptoms']);
            $score = count($matches);

            if ($score > $bestScore) {
                $bestMatch = $disease;
                $bestScore = $score;
            }
        }

        if (!$bestMatch) {
            return "No known disease matches the provided symptoms for this animal. Please consult a veterinarian.";
        }

        // Format readable output
        $animal = ucfirst($category);
        $formattedSymptoms = ucfirst(implode(', ', $request->symptoms));

        return "{$bestMatch['name']}: {$bestMatch['description']}
Select animal species: {$animal}
Likely symptoms: {$formattedSymptoms}.";
    }
}
