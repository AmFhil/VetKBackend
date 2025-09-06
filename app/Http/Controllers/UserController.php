<?php

namespace App\Http\Controllers;

use App\Models\VeterinaryDoctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    //feed calculator
    public function feedCalculator(Request $request)
    {
        $livestock_category = $request->livestock_category;
        switch ($livestock_category) {
            case 'Poultry':
                $bird_type = $request->bird_type;
                $feed_type = $request->feed_type;
                if ($bird_type == 'Broilers') {
                    return $this->PoultryFeedType($feed_type, $request);
                } else {
                    return $this->PoultryFeedType($feed_type, $request);
                }
                break;
            case 'Fish':
                $fish = [
                    ['pellet_size' => 2, 'feed' => ($request->no_of_fish * 0.015), 'space_requires' => ($request->no_of_fish / 8)],
                    ['pellet_size' => 3, 'feed' => ($request->no_of_fish * 0.03), 'space_requires' => ''],
                    ['pellet_size' => 4.5, 'feed' => ($request->no_of_fish * 0.15), 'space_requires' => ''],
                    ['pellet_size' => 6, 'feed' => ($request->no_of_fish * 0.24), 'space_requires' => ''],
                    ['pellet_size' => 8, 'feed' => ($request->no_of_fish * 0.455), 'space_requires' => '']
                ];
                return $fish;
                break;


            default:
                $pig = [
                    ['age' => 2, 'feed' => ($request->no_of_fish * 1.7), 'space_requires' => ($request->no_of_fish / 8)],
                    ['pellet_size' => 3, 'feed' => ($request->no_of_pig * 1.7), 'space_requires' => ($request->no_of_pig / 0.6)],
                    ['pellet_size' => 4, 'feed' => ($request->no_of_pig * 1.8), 'space_requires' => ($request->no_of_pig / 1.6)],
                    ['pellet_size' => 5, 'feed' => ($request->no_of_pig * 2.0), 'space_requires' => ($request->no_of_pig / 7)],
                    ['pellet_size' => 6, 'feed' => ($request->no_of_pig * 2.0), 'space_requires' => '-'],
                    ['pellet_size' => 7, 'feed' => ($request->no_of_pig * 2.3), 'space_requires' => '-'],
                    ['pellet_size' => 7, 'feed' => ($request->no_of_pig * 2.3), 'space_requires' => '-']
                ];
                return $pig;
                break;
        }
    }

    //reusable function for feedcalculator
    public function PoultryFeedType($feed_type, $request)
    {
        $no_of_week = $request->no_of_week;
        $no_of_bird = $request->no_of_bird;
        switch ($feed_type) {
            case 'Starter':
                $feed = $no_of_week * $no_of_bird;
                $feeder = floor((($no_of_bird - 1) / 20) + 1);
                $chicks = 0.09 * $no_of_bird;
                $birds = $no_of_bird / 5;
                return [
                    'livestock_category' => $request->livestock_category,
                    'bird_type' => $request->bird_type,
                    'space' => "Space requirement for Chicks - " . $chicks . "Sqm | " . $birds . "Sqm for Birds",
                    'feed_type' => $request->feed_type,
                    'no_of_bird' => $no_of_bird,
                    'feeder' => $feeder,
                    'drinker' => $feeder,
                    'weight' => $feed . 'kg'
                ];
                break;
            case 'Finisher':
                $feeder = floor((($no_of_bird - 1) / 15) + 1);
                $feed = $no_of_week * $no_of_bird;
                $birds = $no_of_bird / 4;
                return [
                    'livestock_category' => $request->livestock_category,
                    'bird_type' => $request->bird_type,
                    'space' => "Space requirement for Birds - " . $birds . "Sqm for Birds",
                    'feed_type' => $request->feed_type,
                    'no_of_bird' => $no_of_bird,
                    'feeder' => $feeder,
                    'drinker' => $feeder,
                    'weight' => $feed . 'kg'
                ];
                break;
            case 'Chick Mash':
                $feeder = floor((($no_of_bird - 1) / 20) + 1);
                $feed = 1.8 * $no_of_bird;
                return [
                    'livestock_category' => $request->livestock_category,
                    'bird_type' => $request->bird_type,
                    'feed_type' => $request->feed_type,
                    'no_of_bird' => $no_of_bird,
                    'feeder' => $feeder,
                    'drinker' => $feeder,
                    'weight' => $feed . 'kg'
                ];
                break;
            case 'Growers Mash':
                $feeder = floor((($no_of_bird - 1) / 15) + 1);
                $feed = 6.1 * $no_of_bird;
                return [
                    'livestock_category' => $request->livestock_category,
                    'bird_type' => $request->bird_type,
                    'feed_type' => $request->feed_type,
                    'no_of_bird' => $no_of_bird,
                    'feeder' => $feeder,
                    'drinker' => $feeder,
                    'weight' => $feed . 'kg'
                ];
                break;
            case 'Layer Mash/Pellet':
                $feeder = floor((($no_of_bird - 1) / 15) + 1);
                $feed = 8.1 * $no_of_bird;
                return [
                    'livestock_category' => $request->livestock_category,
                    'bird_type' => $request->bird_type,
                    'feed_type' => $request->feed_type,
                    'no_of_bird' => $no_of_bird,
                    'feeder' => $feeder,
                    'drinker' => $feeder,
                    'weight' => $feed . 'kg'
                ];
                break;
            default:
                break;
        }
    }

    //disease predictor
    public function diseasePredictor(Request $request)
    {
        $counter = 0;
        $livestock_category = strtolower($request->livestock_category) . '_disease';
        $diseaseSentence = '';
        for ($l = 0; $l < count($request->diseases); $l++) {
            $diseaseSentence = $diseaseSentence . ', ' . $request->diseases[$l];
        }
        for ($k = 0; $k < count($request->diseases); $k++) {
            for ($i = 0; $i < count($this->$livestock_category); $i++) {
                for ($j = 0; $j < count($this->$livestock_category[$i]['array']); $j++) {
                    for ($l = 0; $l < count($this->$livestock_category[$i]['array'][$j]['diseases']); $l++) {
                        if ($j == $j && in_array($request->diseases[$k], $this->$livestock_category[$i]['array'][$j]['diseases'])) {
                            $counter = $counter + 1;
                        }
                        if (($counter)) {
                            return 'Based on the signs and symptoms you have selected, the suspected condition is <p style="color:red">' . $this->$livestock_category[$i]['array'][$j]['disease_name'];
                        }
                    }
                }
                if (($i == count($this->$livestock_category) - 1) && ($counter == 0)) {
                    return 'These Symptoms do not match any disease yet on our System!';
                }
            }
        }
    }

    //All livestock diseases based on category
    public function getLivestockDiseases(Request $request)
    {
        $livestock_category = strtolower($request->livestock);
        return $this->$livestock_category;
    }

    // use long and lati to get nearest veterinary doctor
    public function getNearestVeterinaryDoctor(Request $request)
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        if (!$latitude || !$longitude) {
            return response()->json(['message' => 'Latitude and Longitude are required.'], 400);
        }

        // Haversine formula to calculate distance
        $haversine = "(6371 * acos(cos(radians($latitude)) * cos(radians(veterinary_doctor.latitude)) * cos(radians(veterinary_doctor.longitude) - radians($longitude)) + sin(radians($latitude)) * sin(radians(veterinary_doctor.latitude))))";

        $veterinaryDoctors = DB::table('veterinary_doctor')
            ->join('users_registration', 'veterinary_doctor.user_id', '=', 'users_registration.id')
            ->leftJoin('rating', 'veterinary_doctor.user_id', '=', 'rating.rateable_id')
            ->select(
                'veterinary_doctor.id',
                'veterinary_doctor.user_id',
                'veterinary_doctor.specialty',
                'veterinary_doctor.list_them',
                'veterinary_doctor.address',
                'veterinary_doctor.longitude',
                'veterinary_doctor.latitude',
                'veterinary_doctor.role',
                'veterinary_doctor.availability',
                'users_registration.email',
                'users_registration.first_name',
                'users_registration.last_name',
                'users_registration.phone_num',
                'users_registration.state',
                'users_registration.country',
                DB::raw('AVG(rating.rating) as average_rating'),
                DB::raw("$haversine AS distance")
            )
            ->whereNotNull('veterinary_doctor.latitude')
            ->whereNotNull('veterinary_doctor.longitude')
            ->orderBy('distance', 'asc')
            ->limit(10)
            ->get();

        return response()->json(['veterinary_doctors' => $veterinaryDoctors], 200);
    }

    // get nearest location by search
    public function searchLocation(Request $request){
        $query = $request->input('query');

        if (!$query) {
            return response()->json(['veterinary_doctors' => []], 200);
        }

        $veterinaryDoctors = VeterinaryDoctor::where('address', 'like', "%$query%")
            ->with(['user:id,email,first_name,last_name,phone_num,state,country','ratings:id,rateable_id,rating,comment'])
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->limit(5);

            // add average rating to each veterinary doctor
            $veterinaryDoctors->getCollection()->transform(function ($doctor){
                $doctor->average_rating = $doctor->averageRating();
                return $doctor;
            });

        return response()->json(['veterinary_doctors' => $veterinaryDoctors], 200);
    }
}
