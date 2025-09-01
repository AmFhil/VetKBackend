<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class USSDController extends Controller
{
    //
    public function handleUssd(Request $request)
    {
        // Log all incoming Request
        Log::info("************ USSD *****************");
        Log::info($request->all());
        Log::info("************ USSD *****************");

        // Get user input from the USSD request
        $userInput = $request->input('text');
        $sessionId = $request->input('sessionId');
        $phoneNumber = substr($request->input('phoneNumber'), 4);
        $phoneNumber = '0'.$phoneNumber;
        $serviceCode = $request->input('serviceCode');

        // Process the user input
        $response = $this->processActions($userInput, $sessionId, $phoneNumber, $serviceCode);

        // Return the USSD response
        return response($response)->header('Content-Type', 'text/plain');
    }

    private function processActions($userInput, $sessionId, $phoneNumber, $serviceCode)
    {
        // Trim and sanitize user input
        $trimmedInput = trim($userInput);
        $last = substr($trimmedInput, strrpos($trimmedInput, '*') + 1);

        if ($trimmedInput == '') {
            // Step 1: Check if phone number exists in either table
            $user = User::where('phone_number', $phoneNumber)->first();

            if ($user) {
                $firstName = $user->first_name;
                return "CON Welcome to Vetkonect, {$firstName}!\nChoose an option:\n2.Veterinary Services.\n3.Training & Resource.\n4.Vaccination Schedule.\n5.Call Support/Helpline.\n0 Exit.";
            } else {
                return "CON Your phone number is not registered. Please enter your email to link your account or register.\n0. Back to Main Menu";
            }
        }

        // Step 2: Handle email input for linking
        if (filter_var($trimmedInput, FILTER_VALIDATE_EMAIL)) {
            $email = $trimmedInput;

            $user = User::where('email', $email)->first();

            if ($user) {
                $user->update(['phone_number' => $phoneNumber]);
                return "CON Your phone number has been linked, {$user->first_name}.\nChoose an option:\n2.Veterinary Services.\n3.Training & Resource.\n4.Vaccination Schedule.\n5.Call Support/Helpline.\n0 Exit.";
            } else {
                //return "CON Email not found. Choose an option to register:\n2. Livestock Farmer\n3. Pet Owner\n4. Vendor\n0. Exit";
                return "CON Email not found. Choose an option:\n1. Register\n0. Exit";
            }
        }


            $trimmedInput = explode("*", $trimmedInput);


            $firstItem = $trimmedInput[0] ?? null;       // Get the first item safely

            if (!is_numeric($firstItem)) {
            // It's not a number � you can return an error or handle it
            array_shift($trimmedInput);
            }

            $trimmedInput = implode("*", $trimmedInput);








        switch ($trimmedInput) {
            case '0': // Back to main menu
                return "END Thank you for using our service";
                break;

            /**
             * 1. Register
             */
            case '1':
                $response = "CON Register \n";
                //$response .= "1. Veterinarian/VPP \n";
                $response .= "2. Animal Owner \n";
                //$response .= "3. Pet Owner \n";
                //$response .= "4. Vendor \n";
                $response .= "0. Back \n";
                return $response; // Added return here
                break;

            case '1*0':
                return "END Thank you for using our services";
                break;

            // *1 => Register Veterinarian/VPP
            case '1*1':
                return "END please send valid Option";
                //return "CON Veterinarian/VPP \nEnter Name \n";

            case (strpos($trimmedInput, '1*1') === 0):
                $parts = explode('*', $trimmedInput);
                $name = $parts[2] ?? null;
                $phone = $parts[3] ?? null;
                $license = $parts[4] ?? null;
                $email = $parts[5] ?? null;
                $location = $parts[6] ?? null;

                if ($name && !$phone) {
                    return "CON Veterinarian/VPP \nName Submitted: {$name}\nEnter Phone Number \n";
                } elseif ($name && $phone && !$license) {
                    return "CON Veterinarian/VPP \nPhone Number Submitted: {$phone}\nEnter License Number \n";
                } elseif ($name && $phone && $license && !$email) {
                    return "CON Veterinarian/VPP \nLicense Number Submitted: {$license}\nEnter Email \n";
                } elseif ($name && $phone && $license && $email && !$location) {
                    return "CON Veterinarian/VPP \nEmail Submitted: {$email}\nEnter Location \n";
                } elseif ($name && $phone && $license && $email && $location) {
                    // $this->saveVeterinarianToDatabase(['name' => $name, 'phone_number' => $phone, 'license_number' => $license, 'email' => $email, 'location' => $location]);
                    return "END Veterinarian/VPP \nLocation Submitted: {$location} \nThank you for Registering \nGoodBye \n";
                } //else {
                    //return "END Veterinarian/VPP \nUnknown Error \nPlease Try Again \nGoodBye \n"; //Ensure user gets a helpful error
                //}

            // *2 => Livestock Farmer
            case '1*2':
                return "CON Enter Your \nFirst Name \n";

            case (strpos($trimmedInput, '1*2') === 0):
                $parts = explode('*', $trimmedInput);
                $name = $parts[2] ?? null;
                $lastname = $parts[3] ?? null;
                $email = $parts[4] ?? null;
                $location = $parts[5] ?? null;
                $register = $parts[6] ?? null;

                if ($name && !$lastname) {
                    return "CON First Name Submitted: {$name} \nEnter Your Last Name \n";
                } elseif ($name && $lastname && !$email) {
                    return "CON Full Name Submitted: {$name} {$lastname}\nEnter Your Email \n";
                } elseif ($name && $lastname && $email && !$location) {
                    return "CON Full Name  \n{$name} {$lastname}\nEmail\n{$email} \nEnter Address \n";
                } elseif ($name && $lastname && $email && $location && !$register) {


            DB::table('animal_owner')->insert([
            'email' => $email,
            'password' => sha1("934567"),
            'first_name' => $name,
            'last_name' => $lastname,
            'phone_number' => $phoneNumber,
            'address' => $location,
            'country' => 'Nigeria',
            'account_activation' => 'Activated',
            'role' => 'Animal Owner',
        ]);

  //save into database
                    return "END Your registration is successful.\n Restart to Enjoy our services at Vetkonect";

                } //elseif ($name && $phone && $location && $email && $livestockType) {
                    // $this->saveLivestockFarmerToDatabase(...)
                   // return "END Livestock Farmer \nType of Livestock Submitted: {$livestockType} \nThank you for Registering \nGoodBye \n";
                //} //else {
                    //return "END Livestock Farmer \nUnknown Error \nPlease Try Again \nGoodBye \n";
                //}

            // *3 => Pet Owner
            case '1*3':
                //return "CON Pet Owner \nEnter Name \n";
                return "END Select a valid Option";

            case (strpos($trimmedInput, '1*3') === 0):
                $parts = explode('*', $trimmedInput);
                $name = $parts[2] ?? null;
                $phone = $parts[3] ?? null;
                $location = $parts[4] ?? null;
                $email = $parts[5] ?? null;
                $petType = $parts[6] ?? null;

                if ($name && !$phone) {
                    return "CON Pet Owner \nName Submitted: {$name} \nEnter Phone Number \n";
                } elseif ($name && $phone && !$location) {
                    return "CON Pet Owner \nPhone Number Submitted: {$phone} \nEnter Location \n";
                } elseif ($name && $phone && $location && !$email) {
                    return "CON Pet Owner \nLocation Submitted: {$location} \nEnter Email \n";
                } elseif ($name && $phone && $location && $email && !$petType) {
                    return "CON Pet Owner \nEmail Submitted: {$email} \nEnter Type of Pet \n";
                } elseif ($name && $phone && $location && $email && $petType) {
                    //$this->savePetOwnerToDatabase
                    return "END Pet Owner \nType of Pet Submitted: {$petType} \nThank you for Registering \nGoodBye \n";
                } else {
                    return "END Pet Owner \nUnknown Error \nPlease Try Again \nGoodBye \n";
                }

            // *4 => Vendor
            case '1*4':
                //return "CON Vendor \nEnter Name \n";
                return "END Select a valid Option";

            case (strpos($trimmedInput, '1*4') === 0):
                $parts = explode('*', $trimmedInput);
                $name = $parts[2] ?? null;
                $phone = $parts[3] ?? null;
                $location = $parts[4] ?? null;
                $email = $parts[5] ?? null;
                $products = $parts[6] ?? null;

                if ($name && !$phone) {
                    return "CON Vendor \nName Submitted: {$name} \nEnter Phone Number \n";
                } elseif ($name && $phone && !$location) {
                    return "CON Vendor \nPhone Number Submitted: {$phone} \nEnter Location \n";
                } elseif ($name && $phone && $location && !$email) {
                    return "CON Vendor \nLocation Submitted: {$location} \nEnter Email \n";
                } elseif ($name && $phone && $location && $email && !$products) {
                    return "CON Vendor \nEmail Submitted: {$email} \nEnter Type of Products \nSeperated by Comma ',' \n";
                } elseif ($name && $phone && $location && $email && $products ) {
                    //$this->saveVendorToDatabase
                    return "END Vendor \nType of Products Submitted: {$products} \nThank you for Registering \nGoodBye \n";
                } else {
                    return "END Vendor \nUnknown Error \nPlease Try Again \nGoodBye \n";
                }

            case '2':
                return "CON Veterinary Services \n1. Request Vet Visit \n2. Emergency Services \n3. Health Tips \n0. Exit \n";

            case '2*1':
                return "CON Request Vet Visit \nEnter the first three letters of your state \n";

            case (strpos($trimmedInput, '2*1') === 0):
                $parts = explode('*', $trimmedInput);
                $location = $parts[2] ?? null;
                $date = $parts[3] ?? null;

                if ($location && !$date) {

                    $vets = DB::table('veterinarian')
        ->select('first_name', 'last_name', 'phone_number')
        ->where('state', 'LIKE', $location . '%')
        ->inRandomOrder()
        ->limit(5)
        ->get();

    if ($vets->isEmpty()) {
        return "END No veterinarians found in states starting with '{$location}'";
    }

    $response = "END Call any of the following Vets:\n";
    foreach ($vets as $index => $vet) {
        $response .= ($index + 1) . ". {$vet->first_name} {$vet->last_name} - {$vet->phone_number}\n";
    }

    return $response;

                    //return "CON Request Vet Visit \nLocation Submitted: {$location} \nEnter Preferred Date \nFORMAT: '[DD-MM-YYYY]' \n";
    } elseif ($location && $date) {
    return "END Request Vet Visit \nRequest Submitted \nA Vet will contact you shortly. \nGoodBye \n";
    } //else {
                    //return "END Request Vet Visit \nUnknown Error \nPlease Try Again \nGoodBye \n";
                //}

            case '2*2':
                return "END Emergency Services \nCall our emergency hotline: 07078340106 \n";

            case '2*3':
                return "CON Select Animal Type \n1. Poultry \n2. Cattle \n3. Sheep/Goat \n4. Pets (Dog, Cat etc.) \n0. Back \n";

            case '2*3*0':
                return "CON Veterinary Services \n1. Request Vet Visit \n2. Emergency Services \n3. Health Tips \n0. Exit \n";

            case (strpos($trimmedInput, '2*3*') === 0):
                $parts = explode('*', $trimmedInput);
                $animalType = $parts[2] ?? null;

                switch ($animalType) {
                    case '1':
                        return "END Health Tips - Poultry \nTip: Ensure proper hygiene in poultry houses. \n";
                    case '2':
                        return "END Health Tips - Cattle \nTip: Provide adequate grazing and water for cattle. \n";
                    case '3':
                        return "END Health Tips - Sheep/Goat \nTip: Regularly check for signs of worms in sheep and goats. \n";
                    case '4':
                        return "END Health Tips - Pets \nTip: Ensure your pets have regular vaccinations. \n";
                    default:
                        return "CON Invalid selection. \n0. Back \n";
                }
                break;

            case '3':
                return "CON Training & Resources \n1. Upcoming Workshops \n2. Downloadable Guides \n0. Exit \n";

            case '3*1':
                return "END Upcoming Workshops \nNo workshops scheduled at the moment. Check back later. \n";

            case '3*2':
                return "END Downloadable Guides \nVisit our website to download guides on animal care. \n";

            case '4':
                return "CON Vaccination Schedule \nEnter Animal Type: \n1. Poultry \n2. Cattle \n3. Sheep/Goat \n4. Pets (Dog, Cat etc.) \n0. Back \n";

            case '4*0':
                return "CON Welcome to Vet Konect!\n1. Register\n2. Veterinary Services\n3. Training & Resources\n4. Vaccination Schedule\n5. Call Support/Helpline\n0. Exit";

            case (strpos($trimmedInput, '4*') === 0):
                $parts = explode('*', $trimmedInput);
                $animalTypeVaccine = $parts[1] ?? null;

                switch ($animalTypeVaccine) {
                    case '1':
                        return "END Vaccination Schedule - Poultry \nDay 1: Marek's disease vaccine. \nWeek 1: Newcastle disease, Infectious bronchitis. \n";
                    case '2':
                        return "END Vaccination Schedule - Cattle \n3-6 months: Blackleg, Brucellosis (for heifers). \nYearly: Foot and Mouth Disease. \n";
                    case '3':
                        return "END Vaccination Schedule - Sheep/Goat \n3-4 months: Peste des petits ruminants (PPR). \nYearly: Contagious caprine pleuropneumonia (CCPP). \n";
                    case '4':
                        return "END Vaccination Schedule - Pets \nDogs: 6-8 weeks: Distemper, parvovirus. \nCats: 6-8 weeks: Feline distemper, calicivirus, rhinotracheitis. \n";
                    default:
                        return "CON Invalid selection. \n0. Back \n";
                }
                break;

            case '5':
                return "END Call Support/Helpline \nCall our support hotline at 08066459317 for assistance. \n";

            default:
                return "END Invalid selection. Please try again. \n";
        }
    }


    /**
     * Process info to Databse
     */
    private function saveInfoToDatabase($data)
    {
        // Run all your DB Insert and Update Query
        Log::info('Data to Process: ' .$data);
        Log::info('Break '.now());
    }

}
