<?php

namespace App\Http\Controllers;

use App\Models\DiseasePredictor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiseasePredictorController extends Controller
{
    /**
     * Knowledge base mapping categories to common veterinary conditions and their symptoms.
     */
    protected function getDiseaseDatabase(): array
    {
        return [
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
                    'name' => 'Diabetes Mellitus',
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
                    'symptoms' => ['diarrhea', 'lethargy', 'ruffled feathers', 'loss of appetite', 'weight loss']
                ],
                [
                    'name' => 'Newcastle Disease',
                    'description' => 'A contagious viral disease that causes sneezing, coughing, green droppings, and nervous symptoms in birds.',
                    'symptoms' => ['coughing', 'difficulty breathing', 'diarrhea', 'loss of appetite', 'abnormal posture']
                ],
                [
                    'name' => 'Fowl Pox',
                    'description' => 'A viral infection causing wart-like lesions on non-feathered parts of the bird or lesions in the mouth/throat.',
                    'symptoms' => ['rashes', 'loss of appetite', 'difficulty breathing', 'lethargy']
                ]
            ],
            'swine' => [
                [
                    'name' => 'African Swine Fever',
                    'description' => 'A highly contagious viral disease affecting pigs, leading to high mortality rates.',
                    'symptoms' => ['fever', 'loss of appetite', 'lethargy', 'diarrhea', 'vomiting', 'difficulty breathing']
                ],
                [
                    'name' => 'Swine Dysentery',
                    'description' => 'A bacterial infection causing severe diarrhea, often bloody, leading to dehydration and weight loss.',
                    'symptoms' => ['diarrhea', 'weight loss', 'loss of appetite', 'lethargy']
                ],
                [
                    'name' => 'Porcine Reproductive and Respiratory Syndrome (PRRS)',
                    'description' => 'A viral disease affecting reproduction and respiratory systems in pigs.',
                    'symptoms' => ['fever', 'coughing', 'difficulty breathing', 'loss of appetite', 'lethargy']
                ]
            ],
            'goat' => [
                [
                    'name' => 'Parasitic Gastroenteritis',
                    'description' => 'Worm infestations leading to weight loss, diarrhea, and rough hair coat.',
                    'symptoms' => ['diarrhea', 'weight loss', 'hair loss', 'loss of appetite']
                ],
                [
                    'name' => 'Caprine Pneumonia',
                    'description' => 'A bacterial or viral infection causing coughing, nasal discharge, fever, and weakness.',
                    'symptoms' => ['coughing', 'discharge from eyes/nose', 'fever', 'loss of appetite']
                ]
            ],
            'cattle' => [
                [
                    'name' => 'Bovine Respiratory Disease (BRD)',
                    'description' => 'A complex of respiratory infections caused by viruses and bacteria, common in stressed cattle.',
                    'symptoms' => ['coughing', 'difficulty breathing', 'fever', 'discharge from eyes/nose', 'loss of appetite', 'lethargy']
                ],
                [
                    'name' => 'Foot Rot',
                    'description' => 'A bacterial infection of the foot causing lameness, swelling, and pain in cattle.',
                    'symptoms' => ['limping', 'swelling', 'inflammation in the limbs', 'fever']
                ],
                [
                    'name' => 'Bovine Viral Diarrhea (BVD)',
                    'description' => 'A viral infection that causes respiratory issues, diarrhea, and immunosuppression.',
                    'symptoms' => ['diarrhea', 'fever', 'loss of appetite', 'weight loss', 'discharge from eyes/nose']
                ]
            ]
        ];
    }

    /**
     * Predict disease from symptoms and livestock category.
     */
    public function diseasePredictor(Request $request)
    {
        // Support either 'category'/'symptom' or 'livestock_category'/'diseases'/'symptoms'
        $categoryInput = $request->input('livestock_category') ?? $request->input('category');
        $symptomsInput = $request->input('symptoms') ?? $request->input('diseases') ?? $request->input('symptom');

        if (!$categoryInput || empty($symptomsInput)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Animal species and symptoms are required.'
            ], 422);
        }

        if (is_string($symptomsInput)) {
            $symptomsInput = explode(',', $symptomsInput);
        }

        $category = strtolower(trim($categoryInput));
        $symptoms = array_map(fn($s) => strtolower(trim($s)), (array) $symptomsInput);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('disease_images', 'public');
        }

        $database = $this->getDiseaseDatabase();

        if (!isset($database[$category])) {
            return response()->json([
                'status' => 'error',
                'message' => "Unsupported animal category: '{$category}'. Available categories: dog, cat, poultry, swine, goat, cattle."
            ], 400);
        }

        $diseases = $database[$category];
        $bestMatch = null;
        $bestScore = 0;
        $matchedSymptomsList = [];

        foreach ($diseases as $disease) {
            $matches = array_intersect($symptoms, $disease['symptoms']);
            $score = count($matches);

            if ($score > $bestScore) {
                $bestMatch = $disease;
                $bestScore = $score;
                $matchedSymptomsList = $matches;
            }
        }

        if (!$bestMatch || $bestScore === 0) {
            return response()->json([
                'status' => 'success',
                'predicted_disease' => 'Unspecified Condition / General Concern',
                'description' => 'The provided symptoms do not decisively match a single known condition in our system. We advise scheduling a physical consultation with a verified veterinarian.',
                'matched_symptoms' => implode(', ', $symptoms),
                'confidence' => 'Low',
                'image_url' => $imagePath ? asset('storage/' . $imagePath) : null
            ], 200);
        }

        // Save prediction record safely
        try {
            DiseasePredictor::create([
                'specie' => ucfirst($category),
                'symptoms' => implode(', ', $symptoms),
                'description' => $bestMatch['description'],
                'image' => $imagePath
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not persist disease prediction: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'predicted_disease' => $bestMatch['name'],
            'description' => $bestMatch['description'],
            'matched_symptoms' => implode(', ', array_map('ucwords', $matchedSymptomsList)),
            'confidence' => ($bestScore >= 2 ? 'High' : 'Moderate'),
            'image_url' => $imagePath ? asset('storage/' . $imagePath) : null
        ], 200);
    }

    /**
     * Retrieve all symptoms or diseases for a category.
     */
    public function getLivestockDiseases(Request $request)
    {
        $category = strtolower(trim($request->input('livestock') ?? $request->input('category') ?? ''));
        $database = $this->getDiseaseDatabase();

        if (empty($category) || !isset($database[$category])) {
            return response()->json([
                'categories' => array_keys($database)
            ]);
        }

        $symptoms = [];
        foreach ($database[$category] as $condition) {
            $symptoms = array_merge($symptoms, $condition['symptoms']);
        }

        return response()->json([
            'category' => $category,
            'symptoms' => array_values(array_unique($symptoms))
        ]);
    }
}
