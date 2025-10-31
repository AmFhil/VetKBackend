<?php

namespace App\Http\Controllers;

use App\Models\DiseasePredictor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiseasePredictorController extends Controller
{
    //
    public function diseasePredictor(Request $request)
    {
        $request->validate([
            'livestock_category' => 'required|string',
            'symptom' => 'required|array|min:1',
            'symptom.*' => 'string',
            'image' => 'nullable|image|max:2048' // max 2MB
        ]);

        $category = strtolower(trim($request->livestock_category));
        $symptoms = array_map('strtolower', $request->symptom);
        Log::info('Disease prediction requested', [
            'category' => $category,
            'symptoms' => $symptoms
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('disease_images', 'public');
        }

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
                    'symptoms' => ['coughing', 'sneezing', 'discharge from eyes/nose', 'loss of appetite']
                ],
                [
                    'name' => 'Parvovirus',
                    'description' => 'A severe viral infection that causes vomiting, bloody diarrhea, and extreme dehydration in dogs, especially puppies.',
                    'symptoms' => ['vomiting', 'diarrhea', 'lethargy', 'loss of appetite', 'fever']
                ]
            ],
            'cat' => [
                [
                    'name' => 'Diabetes',
                    'description' => 'A metabolic disorder where the body cannot properly regulate blood sugar levels, leading to increased thirst and urination.',
                    'symptoms' => ['excessive thirst', 'frequent urination', 'weight loss', 'loss of appetite', 'lethargy']
                ],
                [
                    'name' => 'Chronic Kidney Disease',
                    'description' => 'A progressive loss of kidney function over time, common in older cats, leading to toxin buildup in the body.',
                    'symptoms' => ['loss of appetite', 'weight loss', 'vomiting', 'excessive thirst', 'lethargy']
                ],
                [
                    'name' => 'Upper Respiratory Infection',
                    'description' => 'Viral or bacterial infections affecting the nose, throat, and sinuses, often highly contagious among cats.',
                    'symptoms' => ['coughing', 'discharge from eyes/nose', 'fever', 'loss of appetite', 'difficulty breathing']
                ]
            ],
            'poultry' => [
                [
                    'name' => 'Coccidiosis',
                    'description' => 'A parasitic infection in the intestines causing bloody droppings, weakness, and loss of appetite.',
                    'symptoms' => ['diarrhea', 'lethargy', 'ruffled feathers', 'loss of appetite']
                ],
                [
                    'name' => 'Newcastle Disease',
                    'description' => 'A viral disease that causes sneezing, coughing, green droppings, and nervous symptoms in birds.',
                    'symptoms' => ['coughing', 'difficulty breathing', 'diarrhea', 'loss of appetite']
                ]
            ],
            'swine' => [
                [
                    'name' => 'African Swine Fever',
                    'description' => 'A highly contagious viral disease affecting pigs, leading to high mortality rates with no vaccine available.',
                    'symptoms' => ['fever', 'loss of appetite', 'lethargy', 'diarrhea', 'vomiting', 'difficulty breathing']
                ],
                [
                    'name' => 'Swine Dysentery',
                    'description' => 'A bacterial infection causing severe diarrhea, often bloody, leading to dehydration and weight loss.',
                    'symptoms' => ['diarrhea', 'weight loss', 'loss of appetite', 'lethargy']
                ],
                [
                    'name' => 'Porcine Reproductive and Respiratory Syndrome (PRRS)',
                    'description' => 'A viral disease affecting reproduction and respiratory systems in pigs, causing abortions and respiratory issues.',
                    'symptoms' => ['fever', 'coughing', 'difficulty breathing', 'loss of appetite', 'lethargy']
                ]
            ],
            'goat' => [
                [
                    'name' => 'Parasitic Infections',
                    'description' => 'Worm infestations leading to weight loss, diarrhea, and rough hair coat.',
                    'symptoms' => ['diarrhea', 'weight loss', 'hair loss', 'loss of appetite']
                ],
                [
                    'name' => 'Pneumonia',
                    'description' => 'A bacterial or viral infection causing coughing, nasal discharge, fever, and weakness.',
                    'symptoms' => ['coughing', 'discharge from eyes/nose', 'fever', 'loss of appetite']
                ]
            ],
            'cattle' => [
                [
                    'name' => 'Bovine Respiratory Disease (BRD)',
                    'description' => 'A complex of respiratory infections caused by viruses and bacteria, common in stressed cattle like those in feedlots.',
                    'symptoms' => ['coughing', 'difficulty breathing', 'fever', 'discharge from eyes/nose', 'loss of appetite', 'lethargy']
                ],
                [
                    'name' => 'Foot Rot',
                    'description' => 'A bacterial infection of the foot causing lameness, swelling, and pain in cattle.',
                    'symptoms' => ['limping', 'swelling', 'inflammation in the limbs', 'fever']
                ],
                [
                    'name' => 'Bovine Viral Diarrhea (BVD)',
                    'description' => 'A viral infection that can cause respiratory issues, diarrhea, and immunosuppression in cattle.',
                    'symptoms' => ['diarrhea', 'fever', 'loss of appetite', 'weight loss', 'discharge from eyes/nose']
                ]
            ]
            // add grasscutter, etc.
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
            return response()->json(['message' =>  "No known disease matches the provided symptoms for this animal. Please consult a veterinarian."], 404);
        }

        // Format readable output
        $animal = ucfirst($category);
        $formattedSymptoms = ucfirst(implode(', ', $request->symptoms));

        DiseasePredictor::create([
            'specie' => $animal,
            'symptoms' => $request->symptoms,
            'description' => $bestMatch['description'],
            'image' => $imagePath
        ]);

        return response()->json([
            'predicted_disease' => $bestMatch['name'],
            'description' => $bestMatch['description'],
            'matched_symptoms' => $formattedSymptoms,
            'image_url' => $imagePath ? asset('storage/' . $imagePath) : null
        ], 200);
    }
}
