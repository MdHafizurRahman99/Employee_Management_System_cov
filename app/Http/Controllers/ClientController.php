<?php

namespace App\Http\Controllers;

use App\Mail\NewClient;
use App\Models\Client;
use App\Models\ClientAditionalDetail;
use App\Models\ClientBankDetail;
use App\Models\ClientContactDetail;
use App\Models\ClientTaxCompanyDetail;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

use App\Notifications\AccountantNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $clientsWithData = Client::with([
            'additionalDetails',
            'bankDetails',
            'contactDetails',
            'taxCompanyDetails'
        ])->orderBy('status', 'ASC')->orderBy('created_at', 'Desc')->get();
        // return $clientsWithData;
        return view(
            'client.list',
            ['clients' => $clientsWithData]
        );
    }
    public function userIndex()
    {
        $userId = Auth::id();
        // return $userId ;
        $clientsWithData = Client::where('user_id', $userId)
            ->with([
                'additionalDetails',
                'bankDetails',
                'contactDetails',
                'taxCompanyDetails'
            ])
            ->orderBy('created_at', 'desc')
            ->get();
        // return $clientsWithData;
        return view(
            'client.list',
            ['clients' => $clientsWithData]
        );
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view("client.create");
        //
    }
    public function createRequest()
    {
        return view("client.createRequest");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_structure' => 'required',
            'client_type' => 'required',
            'full_legal_name' => 'required',
            'phone' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'fax' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'email' => ['required', 'regex:/[^\s@]+@[^\s@]+\.[a-zA-Z]{2,6}/',],
            'website' => 'required|url',
            'physical_street' => 'required',
            'physical_city' => 'required',
            'physical_state' => 'required',
            'physical_postal_code' => 'required|numeric',
            'physical_country' => 'required',
            'postal_street' => 'exclude_if:sameAsPhysical,1|required',
            'postal_city' => 'exclude_if:sameAsPhysical,1|required',
            'postal_state' => 'exclude_if:sameAsPhysical,1|required',
            'postal_postal_code' => 'exclude_if:sameAsPhysical,1|required|numeric',
            'postal_country' => 'exclude_if:sameAsPhysical,1|required',
            'business_number' => ['required', 'regex:/^[0-9 ]+$/', 'min:11', 'max:14',    function ($attribute, $value, $fail) {
                // Performing custom validation logic here
                if (!$this->ValidateABN($value)) {
                    $fail('The ABN format is invalid.');
                }
            },],
            'tax_file_number' => ['required', 'regex:/^[0-9 ]+$/', 'min:9', 'max:11',    function ($attribute, $value, $fail) {
                // Performing custom validation logic here
                if (!$this->ValidateTFN($value)) {
                    $fail('The TFN format is invalid.');
                }
            },],
            'company_number' => ['required', 'regex:/^[0-9 ]+$/', 'min:9', 'max:11', function ($attribute, $value, $fail) {
                // Performing custom validation logic here
                if (!$this->ValidateACN($value)) {
                    $fail('The ACN format is invalid.');
                }
            },],
            // 'tax_file_number' => 'required|min:9|max:11',
            // 'business_number' => 'required|min:11|max:14',
            // 'company_number' => 'required|min:9|max:11',
            'account_name' => 'required',
            'account_number' => 'required',
            'financial_institution_name' => 'required',
            'bsb_number' =>  ['required', 'regex:/^[0-9-]+$/', 'min:6', 'max:7'],
            'document_type' => 'required',
        ], [
            'required' => 'The :attribute field is required.',
            'required_if' => 'The :attribute field is required.',
            'numeric' => 'The :attribute must contain only numbers.',
            'url' => 'The :attribute must be a valid URL.',
            'phone.regex' => 'The :attribute format is invalid.',
            'fax.regex' => 'The :attribute format is invalid.',
            'email' => 'The :attribute must be a valid email address.',
            'email.regex' => 'The :attribute must be in a valid email format.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'bsb_number.regex' => 'The :attribute may only contain numbers and hyphens.',
            'business_number.regex' => 'The :attribute can only contain spaces and numbers.',
            'tax_file_number.regex' => 'The :attribute can only contain spaces and numbers.',
            'company_number.regex' => 'The :attribute can only contain spaces and numbers.',
        ]);

        $validator->setAttributeNames([
            'business_structure' => 'Business Structure',
            'client_type' => 'Client Type',
            'full_legal_name' => 'Full Legal Name',
            'phone' => 'Phone',
            'fax' => 'Fax',
            'email' => 'Email',
            'website' => 'Website',
            'physical_street' => 'Physical Street',
            'physical_city' => 'Physical City',
            'physical_state' => 'Physical State',
            'physical_postal_code' => 'Postal Code',
            'physical_country' => 'Physical Country',
            'postal_street' => 'Postal Street',
            'postal_city' => 'Postal City',
            'postal_state' => 'Postal State',
            'postal_postal_code' => 'Postal Code',
            'postal_country' => 'Postal Country',
            'tax_file_number' => 'TFN',
            'business_number' => 'ABN',
            'company_number' => 'ACN',
            'account_name' => 'Account Name',
            'account_number' => 'Account Number',
            'financial_institution_name' => 'Financial Institution Name',
            'bsb_number' => 'BSB Number',
            'document_type' => 'Document Type',
        ]);
        // $customMessages = [
        //     'required' => 'The :attribute field is required.',
        //     'email' => 'The :attribute must be a valid email address.',
        //     'min' => 'The :attribute must be at least :min characters.',
        //     'max' => 'The :attribute may not be greater than :max characters.',
        // ];
        // $validator->setMessages($customMessages);

        if ($validator->fails()) {
            // return response()->json(['errors' => $validator->errors()], 400);
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $userId = Auth::id();

        $client = Client::create([
            // 'id' => $userId,
            'user_id' => $userId,
            'business_structure' => $request->business_structure,
            'client_type' => $request->client_type,
            'full_legal_name' => $request->full_legal_name,
        ]);

        $phoneNumber = '+' . $request->phone_country_dial_code . '  ' . $request->phone;
        $faxNumber = '+' . $request->fax_country_dialCode . '  ' . $request->fax;
        // return  $phoneNumber;
        if ($request->sameAsPhysical == '1') {
            $clientContactDetail = ClientContactDetail::create([
                'client_id' => $client->id,
                'phone' => $phoneNumber,
                'fax' => $faxNumber,
                'email' => $request->email,
                'website' => $request->website,
                'physical_street' => $request->physical_street,
                'physical_city' => $request->physical_city,
                'physical_state' => $request->physical_state,
                'physical_postal_code' => $request->physical_postal_code,
                'physical_country' => $request->physical_country,

                'postal_street' => $request->physical_street,
                'postal_city' => $request->physical_city,
                'postal_state' => $request->physical_state,
                'postal_postal_code' => $request->physical_postal_code,
                'postal_country' => $request->physical_country,
            ]);
        } else {
            $clientContactDetail = ClientContactDetail::create([
                'client_id' => $client->id,
                'phone' => $phoneNumber,
                'fax' => $faxNumber,
                'email' => $request->email,
                'website' => $request->website,
                'physical_street' => $request->physical_street,
                'physical_city' => $request->physical_city,
                'physical_state' => $request->physical_state,
                'physical_postal_code' => $request->physical_postal_code,
                'physical_country' => $request->physical_country,
                'postal_street' => $request->postal_street,
                'postal_city' => $request->postal_city,
                'postal_state' => $request->postal_state,
                'postal_postal_code' => $request->postal_postal_code,
                'postal_country' => $request->postal_country,
            ]);
        }

        $bsb = $request->bsb_number;
        $bsb = preg_replace("/[^\d]/", "", $request->bsb_number);
        $firstPart = substr($bsb, 0, 3);
        $secondPart = substr($bsb, 3);
        $output_bsb = $firstPart . '-' . $secondPart;

        // return $output_bsb;
        $clientBankDetail = ClientBankDetail::create([
            'client_id' => $client->id,
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'financial_institution_name' => $request->financial_institution_name,
            'bsb_number' => $output_bsb,
        ]);
        // formating abn number
        $abn = preg_replace("/[^\d]/", "", $request->business_number);
        $output_abn = chunk_split(substr($abn, 0, 2), 2, ' ') . chunk_split(substr($abn, 2), 3, ' ');
        $output_abn = rtrim($output_abn);

        //formting acn 
        $acn = preg_replace("/[^\d]/", "", $request->company_number);
        $output_acn =  chunk_split($acn, 3, ' ');
        $output_acn = rtrim($output_acn);

        //formting tfn
        $tfn = preg_replace("/[^\d]/", "", $request->tax_file_number);
        $output_tfn =  chunk_split($tfn, 3, ' ');
        $output_tfn = rtrim($output_tfn);

        $clientTaxCompany = ClientTaxCompanyDetail::create([
            'client_id' => $client->id,
            'tax_file_number' => $output_tfn,
            'business_number' => $output_abn,
            'company_number' => $output_acn,
        ]);

        $clientAditionalDeatail = ClientAditionalDetail::create([
            'client_id' => $client->id,
            'activity_statement' => $request->activity_statement,
            'tax_form' => $request->tax_form,
            'ato_client' => $request->ato_client,
            'verification_document' => $request->verification_document,
            'document_type' => $request->document_type,
        ]);

        //it will send mail to hfhafiz0@gmail.com
        // Mail::to('hfhafiz0@gmail.com')->send(new NewClient($request));
        $accountantRole = Role::where('name', 'Accountant')->first();
        if ($accountantRole) {
            $accountantUsers = $accountantRole->roleUsers()->get();
            //send notification to accountents
            $userIds = $accountantUsers->pluck('id')->toArray();


            // Create a custom single notification entry in the database
            // $notificationId = DB::table('notifications')->insertGetId([
            //     'type' => AccountantNotification::class,
            //     'notifiable_type' => User::class, // Assuming user notifications
            //     'notifiable_id' =>  $accountantRole->id, // Set null as it's a general notification
            //     // 'data' => $client,
            //     'data' => [
            //         // 'client' => $client,
            //         'user_id' => $userId,
            //         'client_id' => $client->id,
            //         'client_type' => $client->client_type,
            //     ],
            //     'created_at' => now(),
            //     'updated_at' => now(),
            // ]);
            $data = [
                'user_id' => $userId,
                'client_id' => $client->id,
                'client_type' => $client->client_type,
            ];
            $notificationId = DB::table('notifications')->insertGetId([
                'type' => AccountantNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $accountantRole->id, // Assuming this is the correct ID
                'data' => json_encode($data), // Convert $client to JSON
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // return $notificationId;


            // Send a single notification to all accountants
            // Notification::send(User::find($userIds), new AccountantNotification($client)); // if we want to use this then in database id will be character
        }
        return back()->with('message', 'New request submitted successfully.');        //
    }

    private function ValidateTFN($tfn)
    {
        $weights = array(1, 4, 3, 7, 5, 8, 6, 9, 10);
        // strip anything other than digits
        $tfn = preg_replace("/[^\d]/", "", $tfn);

        // check length is 9 digits
        if (strlen($tfn) == 9) {
            $sum = 0;
            foreach ($weights as $position => $weight) {
                $digit = $tfn[$position];
                $sum += $weight * $digit;
            }
            return ($sum % 11) == 0;
        }
        return false;
    }

    private function ValidateABN($abn)
    {
        $weights = array(10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19);

        // strip anything other than digits
        $abn = preg_replace("/[^\d]/", "", $abn);

        // check length is 11 digits
        if (strlen($abn) == 11) {
            // apply ato check method 
            $sum = 0;
            foreach ($weights as $position => $weight) {
                $digit = $abn[$position] - ($position ? 0 : 1);
                $sum += $weight * $digit;
            }
            return ($sum % 89) == 0;
        }
        return false;
    }

    private function ValidateACN($acn)
    {
        $weights = array(8, 7, 6, 5, 4, 3, 2, 1);

        // strip anything other than digits
        $acn = preg_replace("/[^\d]/", "", $acn);

        // check length is 9 digits
        if (strlen($acn) == 9) {
            // apply ato check method 
            $sum = 0;
            foreach ($weights as $position => $weight) {
                $sum += $weight * $acn[$position];
            }
            $check = (10 - ($sum % 10)) % 10;
            return $acn[8] == $check;
        }
        return false;
    }
    /**
     * Display the specified resource.
     */
    public function show(Client $client)
    {
        return $client;
        //
    }

    public function edit(Client $client)
    {
        // $notification = Notification::whereRaw("JSON_EXTRACT(data, '$.client_id') = ?", [$client->id])->first();

        // if ($notification) {
        //     // $notification->markAsRead();
        //     // Additional code...
        //     return $notification;
        // }
        $notification = DB::table('notifications')
            ->whereRaw("JSON_EXTRACT(data, '$.client_id') = ?", [$client->id])
            ->first();
        // return $notification;

        if ($notification) {
            $notification = DatabaseNotification::find($notification->id); // Replace 881 with the actual notification ID
            if ($notification) {
                $notification->markAsRead();
                // Additional code...
                // return $notification;
            }
        }
        $clientData = Client::with([
            'additionalDetails',
            'bankDetails',
            'contactDetails',
            'taxCompanyDetails'
        ])->find($client->id);

        // return $clientData;
        return view('client.edit', ['clientData' => $clientData]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        // return $request;
        $validator = Validator::make($request->all(), [
            'business_structure' => 'required',
            'client_type' => 'required',
            'full_legal_name' => 'required',
            'phone' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'fax' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'email' => ['required', 'regex:/[^\s@]+@[^\s@]+\.[a-zA-Z]{2,6}/',],
            'website' => 'required|url',
            'physical_street' => 'required',
            'physical_city' => 'required',
            'physical_state' => 'required',
            'physical_postal_code' => 'required|numeric',
            'physical_country' => 'required',
            'postal_street' => 'exclude_if:sameAsPhysical,1|required',
            'postal_city' => 'exclude_if:sameAsPhysical,1|required',
            'postal_state' => 'exclude_if:sameAsPhysical,1|required',
            'postal_postal_code' => 'exclude_if:sameAsPhysical,1|required|numeric',
            'postal_country' => 'exclude_if:sameAsPhysical,1|required',
            'business_number' => ['required', 'regex:/^[0-9 ]+$/', 'min:11', 'max:14',    function ($attribute, $value, $fail) {
                // Performing custom validation logic here
                if (!$this->ValidateABN($value)) {
                    $fail('The ABN format is invalid.');
                }
            },],
            'tax_file_number' => ['required', 'regex:/^[0-9 ]+$/', 'min:9', 'max:11',    function ($attribute, $value, $fail) {
                // Performing custom validation logic here
                if (!$this->ValidateTFN($value)) {
                    $fail('The TFN format is invalid.');
                }
            },],
            'company_number' => ['required', 'regex:/^[0-9 ]+$/', 'min:9', 'max:11', function ($attribute, $value, $fail) {
                // Performing custom validation logic here
                if (!$this->ValidateACN($value)) {
                    $fail('The ACN format is invalid.');
                }
            },],
            // 'tax_file_number' => 'required|min:9|max:11',
            // 'business_number' => 'required|min:11|max:14',
            // 'company_number' => 'required|min:9|max:11',
            'account_name' => 'required',
            'account_number' => 'required',
            'financial_institution_name' => 'required',
            'bsb_number' =>  ['required', 'regex:/^[0-9-]+$/', 'min:6', 'max:7'],
            'document_type' => 'required',
        ], [
            'required' => 'The :attribute field is required.',
            'required_if' => 'The :attribute field is required.',
            'numeric' => 'The :attribute must contain only numbers.',
            'url' => 'The :attribute must be a valid URL.',
            'phone.regex' => 'The :attribute format is invalid.',
            'fax.regex' => 'The :attribute format is invalid.',
            'email' => 'The :attribute must be a valid email address.',
            'email.regex' => 'The :attribute must be in a valid email format.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'bsb_number.regex' => 'The :attribute may only contain numbers and hyphens.',
            'business_number.regex' => 'The :attribute can only contain spaces and numbers.',
            'tax_file_number.regex' => 'The :attribute can only contain spaces and numbers.',
            'company_number.regex' => 'The :attribute can only contain spaces and numbers.',
        ]);

        $validator->setAttributeNames([
            'business_structure' => 'Business Structure',
            'client_type' => 'Client Type',
            'full_legal_name' => 'Full Legal Name',
            'phone' => 'Phone',
            'fax' => 'Fax',
            'email' => 'Email',
            'website' => 'Website',
            'physical_street' => 'Physical Street',
            'physical_city' => 'Physical City',
            'physical_state' => 'Physical State',
            'physical_postal_code' => 'Postal Code',
            'physical_country' => 'Physical Country',
            'postal_street' => 'Postal Street',
            'postal_city' => 'Postal City',
            'postal_state' => 'Postal State',
            'postal_postal_code' => 'Postal Code',
            'postal_country' => 'Postal Country',
            'tax_file_number' => 'TFN',
            'business_number' => 'ABN',
            'company_number' => 'ACN',
            'account_name' => 'Account Name',
            'account_number' => 'Account Number',
            'financial_institution_name' => 'Financial Institution Name',
            'bsb_number' => 'BSB Number',
            'document_type' => 'Document Type',
        ]);
        // $customMessages = [
        //     'required' => 'The :attribute field is required.',
        //     'email' => 'The :attribute must be a valid email address.',
        //     'min' => 'The :attribute must be at least :min characters.',
        //     'max' => 'The :attribute may not be greater than :max characters.',
        // ];
        // $validator->setMessages($customMessages);

        if ($validator->fails()) {
            // return response()->json(['errors' => $validator->errors()], 400);
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $aditional_detail = ClientAditionalDetail::where('client_id', $client->id)->first();
        $bank_detail = ClientBankDetail::where('client_id', $client->id)->first();
        $contact_detail = ClientContactDetail::where('client_id', $client->id)->first();
        $tax_company_detail = ClientTaxCompanyDetail::where('client_id', $client->id)->first();

        $client->update([
            'business_structure' => $request->business_structure,
            'client_type' => $request->client_type,
            'full_legal_name' => $request->full_legal_name,
        ]);

        $phoneNumber = '+' . $request->phone_country_dial_code . '  ' . $request->phone;
        $faxNumber = '+' . $request->fax_country_dialCode . '  ' . $request->fax;
        // return  $phoneNumber;
        if ($request->sameAsPhysical == '1') {
            $contact_detail->update([
                'phone' => $phoneNumber,
                'fax' => $faxNumber,
                'email' => $request->email,
                'website' => $request->website,
                'physical_street' => $request->physical_street,
                'physical_city' => $request->physical_city,
                'physical_state' => $request->physical_state,
                'physical_postal_code' => $request->physical_postal_code,
                'physical_country' => $request->physical_country,
                'postal_street' => $request->physical_street,
                'postal_city' => $request->physical_city,
                'postal_state' => $request->physical_state,
                'postal_postal_code' => $request->physical_postal_code,
                'postal_country' => $request->physical_country,
            ]);
        } else {
            $contact_detail->update([
                'phone' => $phoneNumber,
                'fax' => $faxNumber,
                'email' => $request->email,
                'website' => $request->website,
                'physical_street' => $request->physical_street,
                'physical_city' => $request->physical_city,
                'physical_state' => $request->physical_state,
                'physical_postal_code' => $request->physical_postal_code,
                'physical_country' => $request->physical_country,
                'postal_street' => $request->postal_street,
                'postal_city' => $request->postal_city,
                'postal_state' => $request->postal_state,
                'postal_postal_code' => $request->postal_postal_code,
                'postal_country' => $request->postal_country,
            ]);
        }

        $bsb = $request->bsb_number;
        $bsb = preg_replace("/[^\d]/", "", $request->bsb_number);
        $firstPart = substr($bsb, 0, 3);
        $secondPart = substr($bsb, 3);
        $output_bsb = $firstPart . '-' . $secondPart;

        // return $output_bsb;
        $bank_detail->update([
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'financial_institution_name' => $request->financial_institution_name,
            'bsb_number' => $output_bsb,
        ]);
        // formating abn number
        $abn = preg_replace("/[^\d]/", "", $request->business_number);
        $output_abn = chunk_split(substr($abn, 0, 2), 2, ' ') . chunk_split(substr($abn, 2), 3, ' ');
        $output_abn = rtrim($output_abn);

        //formting acn 
        $acn = preg_replace("/[^\d]/", "", $request->company_number);
        $output_acn =  chunk_split($acn, 3, ' ');
        $output_acn = rtrim($output_acn);

        //formting tfn
        $tfn = preg_replace("/[^\d]/", "", $request->tax_file_number);
        $output_tfn =  chunk_split($tfn, 3, ' ');
        $output_tfn = rtrim($output_tfn);

        $tax_company_detail->update([
            'client_id' => $client->id,
            'tax_file_number' => $output_tfn,
            'business_number' => $output_abn,
            'company_number' => $output_acn,
        ]);

        $aditional_detail->update([
            'activity_statement' => $request->activity_statement,
            'tax_form' => $request->tax_form,
            'ato_client' => $request->ato_client,
            'verification_document' => $request->verification_document,
            'document_type' => $request->document_type,
        ]);

        // $contact_detail->update([
        //     'phone' => $request->phone,
        //     'fax' => $request->fax,
        //     'email' => $request->email,
        //     'website' => $request->website,
        //     'physical_street' => $request->physical_street,
        //     'physical_city' => $request->physical_city,
        //     'physical_state' => $request->physical_state,
        //     'physical_postal_code' => $request->physical_postal_code,
        //     'physical_country' => $request->physical_country,
        //     'postal_street' => $request->postal_street,
        //     'postal_city' => $request->postal_city,
        //     'postal_state' => $request->postal_state,
        //     'postal_postal_code' => $request->postal_postal_code,
        //     'postal_country' => $request->postal_country,
        // ]);


        // $bank_detail->update([
        //     'account_name' => $request->account_name,
        //     'account_number' => $request->account_number,
        //     'financial_institution_name' => $request->financial_institution_name,
        //     'bsb_number' => $request->bsb_number,
        // ]);


        // $tax_company_detail->update([
        //     'tax_file_number' => $request->tax_file_number,
        //     'business_number' => $request->business_number,
        //     'company_number' => $request->company_number,
        // ]);
        // $aditional_detail->update([
        //     'activity_statement' => $request->activity_statement,
        //     'tax_form' => $request->tax_form,
        //     'ato_client' => $request->ato_client,
        //     'verification_document' => $request->verification_document,
        //     'document_type' => $request->document_type,
        // ]);

        return redirect()->route('client.index')->with('success', 'Client Information Updated successfully! ');
    }


    public function status($client_id)
    {
        $client = Client::find($client_id);
        if ($client->status == '0') {
            // return $client;
            $client->update([
                'status' => '1'
            ]);
        } else {
            $client->update([
                'status' => '0'
            ]);
        }

        return back()->with('success', 'Status Updated Successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        // return $client;
        $client->delete();
        $aditional_detail = ClientAditionalDetail::where('client_id', $client->id)->delete();
        $bank_detail = ClientBankDetail::where('client_id', $client->id)->delete();
        $contact_detail = ClientContactDetail::where('client_id', $client->id)->delete();
        $tax_company_detail = ClientTaxCompanyDetail::where('client_id', $client->id)->delete();
        return redirect()->route('client.index')->with('success', 'Client Request Deleted Successfully.');
    }
}
