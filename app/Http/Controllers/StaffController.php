<?php

namespace App\Http\Controllers;

use Artisaninweb\SoapWrapper\Facades\SoapWrapper;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

// use SoapWrapper;
use SoapClient;

class StaffController extends Controller
{
    public $image, $imageName, $directory, $imgUrl;

    /**
     * Display a listing of the resource.
     */

    public function index()
    {
        if (Auth::user()->hasRole('User')) {
            $userId = Auth::id();
            return view(
                'admin.staff.list',
                [
                    'staffs' => Staff::where('user_id', $userId)->get(),
                ]
            );
        } else {
            return view(
                'admin.staff.list',
                [
                    'staffs' => Staff::select('id', 'first_name', 'last_name', 'email')->get(),
                ]
            );
        }
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.staff.create(updated)');
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        // $ip = $_SERVER['REMOTE_ADDR'];
        // return $ip;
        // $this->image = $request->file('address_validate_file');
        // return $this->image;
        // return $request;
        // dd($request->file('address_validate_file')->getMimeType());

        // Retrieve ABN from the form or request

        // SoapWrapper::service('abr', function ($service) use ($abn) {
        //     try {
        //         $response = $service->call('ABRSearchByABN', [
        //             'parameters' => [
        //                 'searchString' => $abn,
        //                 'includeHistoricalDetails' => 'N',
        //                 'authenticationGuid' => 'GUID_HERE', // Replace with  authentication GUID
        //             ],
        //         ]);

        //         // Process response or handle errors
        //         dd($response); // Output response for debugging
        //     } catch (\Exception $e) {
        //         dd($e->getMessage()); // Output any exception message for debugging
        //     }
        // });
        // return $abn;
        // Perform SOAP request
        // dd('hello');
        // SoapWrapper::service('abr', function ($service) use ($abn) {
        //     $response = $service->call('ABRSearchByABN', [
        //         'parameters' => [
        //             'searchString' => $abn,
        //             'includeHistoricalDetails' => 'N',
        //             'authenticationGuid' => 'GUID_HERE', // Replace with  authentication GUID
        //         ],
        //     ]);

        //     // Process the response
        //     $abnDetails = $response->ABRPayloadSearchResults->response->businessEntity;
        //     return $abnDetails;
        //     // Return the details or pass them to a view for rendering
        //     return view('abn_lookup_result', [
        //         'abnDetails' => $abnDetails,
        //     ]);
        // });



        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'email' => ['required', 'regex:/[^\s@]+@[^\s@]+\.[a-zA-Z]{2,6}/',],
            'business_number' => ['required_if:contractor,Yes',    function ($attribute, $value, $fail) use ($request) {
                // Performing custom validation logic here
                if (!$this->ValidateABN($value) && $request->contractor == 'Yes') {
                    $fail('The ABN format is invalid.');
                }
            },],
            'employee_tax_file' => [function ($attribute, $value, $fail) use ($request) {
                // Performing custom validation logic here
                if (!$this->ValidateTFN($value) && $request->contractor != 'Yes') {
                    $fail('The TFN format is invalid.');
                }
            },],
            'company_number' => ['required_if:contractor,Yes',  function ($attribute, $value, $fail) use ($request) {
                // Performing custom validation logic here
                if (!$this->ValidateACN($value)  && $request->contractor == 'Yes') {
                    $fail('The ACN format is invalid.');
                }
            },],
            'bsb_number' =>  ['required', 'regex:/^[0-9-]+$/', 'min:6', 'max:7'],
            'first_name' => 'required',
            'last_name' => 'required',
            // 'start_date' => '',
            // 'possion_title' => '',
            // 'gender' => '',
            // 'date_of_birth' => '',
            // 'address' => '',
            // 'suburb' => '',
            // 'state' => '',
            // 'postcode' => '',
            'mobile' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            // 'super_fund' => '',
            // 'member_no' => '',
            'company_name' => 'required_if:contractor,Yes',
            'company_address' => 'required_if:contractor,Yes',
            'company_phone' => 'required_if:contractor,Yes',
            'company_email' => [
                'required_if:contractor,Yes', function ($attribute, $value, $fail) use ($request) {
                    // Custom validation logic here
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL) && $request->contractor == 'Yes') {
                        $fail('The email format is invalid.');
                    }
                },
            ],
            // 'bank_name' => '',
            // 'branch' => '',
            // 'account_name' => '',
            // 'account_number' => '',
            // 'aus_citizen' => '',
            'permanent_resident' => 'required_if:aus_citizen,No',
            'visa_expiry_date' => 'required_if:aus_citizen,No',
            // 'restriction' => 'exclude_if:aus_citizen,Yes|required',
            // 'next_of_kin' => '',
            // 'relationship' => '',
            // 'kin_address' => '',
            // 'kin_suburb' => '',
            // 'kin_state' => '',
            // 'kin_postcode' => '',
            'kin_phone' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'kin_mobile' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            // 'kin_work' => '',
            // 'about_validate_file' => '',
            'address_validate_file' => 'image|mimes:jpeg,png,jpg,gif,JPEG,PNG,JPG,GIF|max:2048',
        ], [
            'required' => 'The :attribute field is required.',
            'numeric' => 'The :attribute must contain only numbers.',
            'url' => 'The :attribute must be a valid URL.',
            'phone.regex' => 'The :attribute format is invalid.',
            'email' => 'The :attribute must be a valid email address.',
            'email.regex' => 'The :attribute must be in a valid email format.',
            'company_email' => 'The :attribute must be a valid email address.',
            'company_email.regex' => 'The :attribute must be in a valid email format.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'bsb_number.regex' => 'The :attribute may only contain numbers and hyphens.',
            'business_number.regex' => 'The :attribute can only contain spaces and numbers.',
            'employee_tax_file.regex' => 'The :attribute can only contain spaces and numbers.',
            'company_number.regex' => 'The :attribute can only contain spaces and numbers.',
        ]);

        $validator->setAttributeNames([
            'phone' => 'Phone',
            'email' => 'Email',
            'company_email' => 'Company Email',
            'employee_tax_file' => 'Employee Tax File',
            'business_number' => 'ABN',
            'company_number' => 'ACN',
            'account_name' => 'Account Name',
            'account_number' => 'Account Number',
            'bsb_number' => 'BSB Number',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            // 'start_date' => 'start Date',
            // 'possion_title' => 'Possion Title',
            // 'gender' => 'Gender',
            // 'date_of_birth' => 'Date Of Birth',
            // 'address' => 'Address',
            // 'suburb' => 'Suburb',
            // 'state' => 'State',
            // 'postcode' => 'Postcode',
            'mobile' => 'Mobile',
            // 'super_fund' => 'Super Fund',
            // 'member_no' => 'Member No',
            'company_name' => 'Company Name',
            'company_address' => 'Company Address',
            'company_phone' => 'Company Phone',
            // 'bank_name' => 'Bank Name',
            // 'branch' => 'branch',
            // 'aus_citizen' => 'Australian citizen',
            'permanent_resident' => 'Permanent Resident',
            'visa_expiry_date' => 'Expiry Date',
            // 'restriction' => 'Restriction',
            // 'next_of_kin' => 'next_of_kin',
            // 'relationship' => 'relationship',
            // 'kin_address' => 'kin_address',
            // 'kin_suburb' => 'kin_suburb',
            // 'kin_state' => 'kin_state',
            // 'kin_postcode' => 'kin_postcode',
            'kin_phone' => 'Phone',
            'kin_mobile' => 'Mobile',
            'kin_work' => 'kin_work',
            'address_validate_file' => 'File',

        ]);

        if ($validator->fails()) {
            // return response()->json(['errors' => $validator->errors()], 400);
            return redirect()->back()->withErrors($validator)->withInput();
        }
        // return $request;
        $userId = Auth::id();

        $phoneNumber = '+' . $request->phone_country_dialCode . '  ' . $request->phone;
        $mobileNumber = '+' . $request->mobile_country_dialCode . '  ' . $request->mobile;
        $company_phoneNumber = '+' . $request->company_phone_country_dialCode . '  ' . $request->company_phone;

        $kin_phoneNumber = '+' . $request->kin_phone_country_dialCode . '  ' . $request->kin_phone;
        $kin_mobileNumber = '+' . $request->kin_mobile_country_dialCode . '  ' . $request->kin_mobile;


        // $bsb = $request->bsb_number;
        $bsb = preg_replace("/[^\d]/", "", $request->bsb_number);
        $firstPart = substr($bsb, 0, 3);
        $secondPart = substr($bsb, 3);
        $output_bsb = $firstPart . '-' . $secondPart;

        $abn = preg_replace("/[^\d]/", "", $request->business_number);
        $output_abn = chunk_split(substr($abn, 0, 2), 2, ' ') . chunk_split(substr($abn, 2), 3, ' ');
        $output_abn = rtrim($output_abn);

        //formting acn 
        $acn = preg_replace("/[^\d]/", "", $request->company_number);
        $output_acn =  chunk_split($acn, 3, ' ');
        $output_acn = rtrim($output_acn);

        //formting tfn
        $tfn = preg_replace("/[^\d]/", "", $request->employee_tax_file);
        $output_tfn =  chunk_split($tfn, 3, ' ');
        $output_tfn = rtrim($output_tfn);

        //image
        $address_validate_file = $this->saveImage($request);

        $staff = Staff::create(
            [
                'user_id' => $userId,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'start_date' => $request->start_date,
                'possion_title' => $request->possion_title,
                'gender' => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'address' => $request->address,
                'suburb' => $request->suburb,
                'state' => $request->state,
                'postcode' => $request->postcode,
                'phone' => $phoneNumber,
                'mobile' => $mobileNumber,
                'email' => $request->email,
                'employee_tax_file' => $output_tfn,
                'super_fund' => $request->super_fund,
                'member_no' => $request->member_no,
                'contractor' => $request->contractor,
                'company_name' => $request->company_name,
                'company_address' => $request->company_address,
                'company_phone' => $company_phoneNumber,
                'company_email' => $request->company_email,
                'business_number' => $output_abn,
                'abn_entity_name' => $request->abn_entity_name,
                'abn_address' => $request->abn_address,
                'abn_status' => $request->abn_status,
                'abn_business_name' => $request->abn_business_name,
                'company_number' => $output_acn,
                'bank_name' => $request->bank_name,
                'branch' => $request->branch,
                'account_name' => $request->account_name,
                'bsb_number' => $output_bsb,
                'account_number' => $request->account_number,
                'aus_citizen' => $request->aus_citizen,
                'permanent_resident' => $request->permanent_resident,
                'visa_expiry_date' => $request->visa_expiry_date,
                'restriction' => $request->restriction,
                'next_of_kin' => $request->next_of_kin,
                'relationship' => $request->relationship,
                'kin_address' => $request->kin_address,
                'kin_suburb' => $request->kin_suburb,
                'kin_state' => $request->kin_state,
                'kin_postcode' => $request->kin_postcode,
                'kin_phone' => $kin_phoneNumber,
                'kin_mobile' => $kin_mobileNumber,
                'kin_work' => $request->kin_work,
                'about_validate_file' => $request->about_validate_file,
                'address_validate_file' => $address_validate_file,

            ]
        );
        return redirect()->route('staff.index')->with('message', 'Request submitted successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Staff $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Staff $id)
    {
        if (Auth::user()->hasRole('User')) {
            $userId = Auth::id();
            // dd($userId);
            if (intval($id->user_id) == $userId) {
                // dd(intval($id->user_id));
                return view(
                    'admin.staff.edit',
                    [
                        'staff' => $id,
                    ]
                );
            } else {
                return back();
            }
        } else {
            return view(
                'admin.staff.edit',
                [
                    'staff' => $id,
                ]
            );
        }

        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Staff $id)
    {
        // return $request;
        // $validator = Validator::make($request->all(), [
        //     'phone' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
        //     'email' => ['required', 'regex:/[^\s@]+@[^\s@]+\.[a-zA-Z]{2,6}/',],

        //     'business_number' => ['required', 'regex:/^[0-9 ]+$/', 'min:11', 'max:14',    function ($attribute, $value, $fail) {
        //         // Performing custom validation logic here
        //         if (!$this->ValidateABN($value)) {
        //             $fail('The ABN format is invalid.');
        //         }
        //     },],
        //     'employee_tax_file' => ['required', 'regex:/^[0-9 ]+$/', 'min:9', 'max:11',    function ($attribute, $value, $fail) {
        //         // Performing custom validation logic here
        //         if (!$this->ValidateTFN($value)) {
        //             $fail('The TFN format is invalid.');
        //         }
        //     },],
        //     'company_number' => ['required', 'regex:/^[0-9 ]+$/', 'min:9', 'max:11', function ($attribute, $value, $fail) {
        //         // Performing custom validation logic here
        //         if (!$this->ValidateACN($value)) {
        //             $fail('The ACN format is invalid.');
        //         }
        //     },],
        //     'bsb_number' =>  ['required', 'regex:/^[0-9-]+$/', 'min:6', 'max:7'],
        //     'first_name' => 'required',
        //     'last_name' => 'required',
        //     'start_date' => '',
        //     'possion_title' => '',
        //     'gender' => '',
        //     'date_of_birth' => '',
        //     'address' => '',
        //     'suburb' => '',
        //     'state' => '',
        //     'postcode' => '',
        //     'mobile' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
        //     'super_fund' => '',
        //     'member_no' => '',
        //     'company_name' => '',
        //     'company_address' => '',
        //     'company_phone' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
        //     'company_email' => ['required', 'regex:/[^\s@]+@[^\s@]+\.[a-zA-Z]{2,6}/',],
        //     'bank_name' => '',
        //     'branch' => '',
        //     'account_name' => '',
        //     'account_number' => '',
        //     'aus_citizen' => '',
        //     'permanent_resident' => 'exclude_if:aus_citizen,Yes|required',
        //     'visa_expiry_date' => 'exclude_if:aus_citizen,Yes|required',
        //     'restriction' => 'exclude_if:aus_citizen,Yes|required',
        //     'next_of_kin' => '',
        //     'relationship' => '',
        //     'kin_address' => '',
        //     'kin_suburb' => '',
        //     'kin_state' => '',
        //     'kin_postcode' => '',
        //     'kin_phone' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
        //     'kin_mobile' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
        //     'kin_work' => '',
        // ], [
        //     'required' => 'The :attribute field is required.',
        //     'numeric' => 'The :attribute must contain only numbers.',
        //     'url' => 'The :attribute must be a valid URL.',
        //     'phone.regex' => 'The :attribute format is invalid.',
        //     'email' => 'The :attribute must be a valid email address.',
        //     'email.regex' => 'The :attribute must be in a valid email format.',
        //     'company_email' => 'The :attribute must be a valid email address.',
        //     'company_email.regex' => 'The :attribute must be in a valid email format.',
        //     'min' => 'The :attribute must be at least :min characters.',
        //     'max' => 'The :attribute may not be greater than :max characters.',
        //     'bsb_number.regex' => 'The :attribute may only contain numbers and hyphens.',
        //     'business_number.regex' => 'The :attribute can only contain spaces and numbers.',
        //     'employee_tax_file.regex' => 'The :attribute can only contain spaces and numbers.',
        //     'company_number.regex' => 'The :attribute can only contain spaces and numbers.',
        // ]);

        // $validator->setAttributeNames([
        //     'phone' => 'Phone',
        //     'email' => 'Email',
        //     'company_email' => 'Company Email',
        //     'employee_tax_file' => 'Employee Tax File',
        //     'business_number' => 'ABN',
        //     'company_number' => 'ACN',
        //     'account_name' => 'Account Name',
        //     'account_number' => 'Account Number',
        //     'bsb_number' => 'BSB Number',
        //     'first_name' => 'First Name',
        //     'last_name' => 'Last Name',
        //     'start_date' => 'start Date',
        //     'possion_title' => 'Possion Title',
        //     'gender' => 'Gender',
        //     'date_of_birth' => 'Date Of Birth',
        //     'address' => 'Address',
        //     'suburb' => 'Suburb',
        //     'state' => 'State',
        //     'postcode' => 'Postcode',
        //     'mobile' => 'Mobile',
        //     'super_fund' => 'Super Fund',
        //     'member_no' => 'Member No',
        //     'company_name' => 'Company Name',
        //     'company_address' => 'Company Address',
        //     'company_phone' => 'Company Phone',
        //     'bank_name' => 'Bank Name',
        //     'branch' => 'branch',
        //     'aus_citizen' => 'aus_citizen',
        //     'permanent_resident' => 'permanent_resident',
        //     'visa_expiry_date' => 'Expiry Date',
        //     'restriction' => 'Restriction',
        //     'next_of_kin' => 'next_of_kin',
        //     'relationship' => 'relationship',
        //     'kin_address' => 'kin_address',
        //     'kin_suburb' => 'kin_suburb',
        //     'kin_state' => 'kin_state',
        //     'kin_postcode' => 'kin_postcode',
        //     'kin_phone' => 'Phone',
        //     'kin_mobile' => 'Mobile',
        //     'kin_work' => 'kin_work',
        // ]);

        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'email' => ['required', 'regex:/[^\s@]+@[^\s@]+\.[a-zA-Z]{2,6}/',],
            'business_number' => ['required_if:contractor,Yes',    function ($attribute, $value, $fail) use ($request) {
                // Performing custom validation logic here
                if (!$this->ValidateABN($value) && $request->contractor == 'Yes') {
                    $fail('The ABN format is invalid.');
                }
            },],
            'employee_tax_file' => [function ($attribute, $value, $fail) use ($request) {
                // Performing custom validation logic here
                if (!$this->ValidateTFN($value) && $request->contractor != 'Yes') {
                    $fail('The TFN format is invalid.');
                }
            },],
            'company_number' => ['required_if:contractor,Yes',  function ($attribute, $value, $fail) use ($request) {
                // Performing custom validation logic here
                if (!$this->ValidateACN($value)  && $request->contractor == 'Yes') {
                    $fail('The ACN format is invalid.');
                }
            },],
            'bsb_number' =>  ['required', 'regex:/^[0-9-]+$/', 'min:6', 'max:7'],
            'first_name' => 'required',
            'last_name' => 'required',
            'start_date' => '',
            'possion_title' => '',
            'gender' => '',
            'date_of_birth' => '',
            'address' => '',
            'suburb' => '',
            'state' => '',
            'postcode' => '',
            'mobile' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'super_fund' => '',
            'member_no' => '',
            'company_name' => 'required_if:contractor,Yes',
            'company_address' => 'required_if:contractor,Yes',
            'company_phone' => 'required_if:contractor,Yes',
            'company_email' => [
                'required_if:contractor,Yes', function ($attribute, $value, $fail) use ($request) {
                    // Custom validation logic here
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL) && $request->contractor == 'Yes') {
                        $fail('The email format is invalid.');
                    }
                },
            ],
            'bank_name' => '',
            'branch' => '',
            'account_name' => '',
            'account_number' => '',
            'aus_citizen' => '',
            'permanent_resident' => 'required_if:aus_citizen,No',
            'visa_expiry_date' => 'required_if:aus_citizen,No',
            // 'restriction' => 'exclude_if:aus_citizen,Yes|required',
            'next_of_kin' => '',
            'relationship' => '',
            'kin_address' => '',
            'kin_suburb' => '',
            'kin_state' => '',
            'kin_postcode' => '',
            'kin_phone' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'kin_mobile' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'kin_work' => '',
            'about_validate_file' => '',
            'address_validate_file' => 'image|mimes:jpeg,png,jpg,gif,JPEG,PNG,JPG,GIF|max:2048',
        ], [
            'required' => 'The :attribute field is required.',
            'numeric' => 'The :attribute must contain only numbers.',
            'url' => 'The :attribute must be a valid URL.',
            'phone.regex' => 'The :attribute format is invalid.',
            'email' => 'The :attribute must be a valid email address.',
            'email.regex' => 'The :attribute must be in a valid email format.',
            'company_email' => 'The :attribute must be a valid email address.',
            'company_email.regex' => 'The :attribute must be in a valid email format.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'bsb_number.regex' => 'The :attribute may only contain numbers and hyphens.',
            'business_number.regex' => 'The :attribute can only contain spaces and numbers.',
            'employee_tax_file.regex' => 'The :attribute can only contain spaces and numbers.',
            'company_number.regex' => 'The :attribute can only contain spaces and numbers.',
        ]);

        $validator->setAttributeNames([
            'phone' => 'Phone',
            'email' => 'Email',
            'company_email' => 'Company Email',
            'employee_tax_file' => 'Employee Tax File',
            'business_number' => 'ABN',
            'company_number' => 'ACN',
            'account_name' => 'Account Name',
            'account_number' => 'Account Number',
            'bsb_number' => 'BSB Number',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'start_date' => 'start Date',
            'possion_title' => 'Possion Title',
            'gender' => 'Gender',
            'date_of_birth' => 'Date Of Birth',
            'address' => 'Address',
            'suburb' => 'Suburb',
            'state' => 'State',
            'postcode' => 'Postcode',
            'mobile' => 'Mobile',
            'super_fund' => 'Super Fund',
            'member_no' => 'Member No',
            'company_name' => 'Company Name',
            'company_address' => 'Company Address',
            'company_phone' => 'Company Phone',
            'bank_name' => 'Bank Name',
            'branch' => 'branch',
            'aus_citizen' => 'Australian citizen',
            'permanent_resident' => 'Permanent Resident',
            'visa_expiry_date' => 'Expiry Date',
            'restriction' => 'Restriction',
            'next_of_kin' => 'next_of_kin',
            'relationship' => 'relationship',
            'kin_address' => 'kin_address',
            'kin_suburb' => 'kin_suburb',
            'kin_state' => 'kin_state',
            'kin_postcode' => 'kin_postcode',
            'kin_phone' => 'Phone',
            'kin_mobile' => 'Mobile',
            'kin_work' => 'kin_work',
            'address_validate_file' => 'File',

        ]);
        if ($validator->fails()) {
            // return response()->json(['errors' => $validator->errors()], 400);
            return redirect()->back()->withErrors($validator)->withInput();
        }
        // return $request;
        $userId = Auth::id();

        $phoneNumber = '+' . $request->phone_country_dialCode . '  ' . $request->phone;
        $mobileNumber = '+' . $request->mobile_country_dialCode . '  ' . $request->mobile;
        $company_phoneNumber = '+' . $request->company_phone_country_dialCode . '  ' . $request->company_phone;
        $kin_phoneNumber = '+' . $request->kin_phone_country_dialCode . '  ' . $request->kin_phone;
        $kin_mobileNumber = '+' . $request->kin_mobile_country_dialCode . '  ' . $request->kin_mobile;


        // $bsb = $request->bsb_number;
        $bsb = preg_replace("/[^\d]/", "", $request->bsb_number);
        $firstPart = substr($bsb, 0, 3);
        $secondPart = substr($bsb, 3);
        $output_bsb = $firstPart . '-' . $secondPart;

        $abn = preg_replace("/[^\d]/", "", $request->business_number);
        $output_abn = chunk_split(substr($abn, 0, 2), 2, ' ') . chunk_split(substr($abn, 2), 3, ' ');
        $output_abn = rtrim($output_abn);

        //formting acn 
        $acn = preg_replace("/[^\d]/", "", $request->company_number);
        $output_acn =  chunk_split($acn, 3, ' ');
        $output_acn = rtrim($output_acn);

        //formting tfn
        $tfn = preg_replace("/[^\d]/", "", $request->employee_tax_file);
        $output_tfn =  chunk_split($tfn, 3, ' ');
        $output_tfn = rtrim($output_tfn);

        //image
        if ($request->address_validate_file) {
            $address_validate_file = $this->saveImage($request);
            if ($id->address_validate_file) {
                unlink($id->address_validate_file);
            }
            $id->update(
                [
                    'address_validate_file' => $address_validate_file,
                ]
            );
        }

        if ($request->abn_entity_name) {
            $id->update(
                [
                    'abn_entity_name' => $request->abn_entity_name,
                    'abn_address' => $request->abn_address,
                    'abn_status' => $request->abn_status,
                    'abn_business_name' => $request->abn_business_name,
                ]
            );
        }

        $id->update(
            [
                'user_id' => $userId,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'start_date' => $request->start_date,
                'possion_title' => $request->possion_title,
                'gender' => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'address' => $request->address,
                'suburb' => $request->suburb,
                'state' => $request->state,
                'postcode' => $request->postcode,
                'phone' => $phoneNumber,
                'mobile' => $mobileNumber,
                'email' => $request->email,
                'employee_tax_file' => $output_tfn,
                'super_fund' => $request->super_fund,
                'member_no' => $request->member_no,
                'contractor' => $request->contractor,
                'company_name' => $request->company_name,
                'company_address' => $request->company_address,
                'company_phone' => $company_phoneNumber,
                'company_email' => $request->company_email,
                'business_number' => $output_abn,
                'company_number' => $output_acn,
                'bank_name' => $request->bank_name,
                'branch' => $request->branch,
                'account_name' => $request->account_name,
                'bsb_number' => $output_bsb,
                'account_number' => $request->account_number,
                'aus_citizen' => $request->aus_citizen,
                'permanent_resident' => $request->permanent_resident,
                'visa_expiry_date' => $request->visa_expiry_date,
                'restriction' => $request->restriction,
                'next_of_kin' => $request->next_of_kin,
                'relationship' => $request->relationship,
                'kin_address' => $request->kin_address,
                'kin_suburb' => $request->kin_suburb,
                'kin_state' => $request->kin_state,
                'kin_postcode' => $request->kin_postcode,
                'kin_phone' => $kin_phoneNumber,
                'kin_mobile' => $kin_mobileNumber,
                'kin_work' => $request->kin_work,
                'about_validate_file' => $request->about_validate_file,
                // 'address_validate_file' => $address_validate_file,
            ]
        );
        return redirect()->route('staff.index')->with('message', 'Staff Inforamation Updated Successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Staff $id)
    {
        // return $id->id;
        if ($id->address_validate_file) {
            unlink($id->address_validate_file);
        }
        $id->delete();
        return back()->with('message', 'Staff Information Deleted Successfully!');

        //
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
        // $weights = array(10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19);

        // // strip anything other than digits
        // $abn = preg_replace("/[^\d]/", "", $abn);

        // // check length is 11 digits
        // if (strlen($abn) == 11) {
        //     // apply ato check method 
        //     $sum = 0;
        //     foreach ($weights as $position => $weight) {
        //         $digit = $abn[$position] - ($position ? 0 : 1);
        //         $sum += $weight * $digit;
        //     }
        //     return ($sum % 89) == 0;
        // }
        // $abn = request()->input('business_number');
        $wsdl = 'https://abr.business.gov.au/abrxmlsearch/ABRXMLSearch.asmx?WSDL';
        $options = [
            'trace' => true, // Enable tracing
            // Add any other options as needed
        ];

        $client = new SoapClient($wsdl, $options);

        // Call the ABRSearchByABN method
        $response = $client->SearchByABNv202001(['searchString' => $abn, 'includeHistoricalDetails' => 'N', 'authenticationGuid' => '1d25add7-1327-4ed6-bd85-d8318553340e']);
        // return $response;
        if (isset($response->ABRPayloadSearchResults->response->businessEntity202001->ABN->isCurrentIndicator) == 'Y') {
            // Property exists, so access its value
            return true;
            // return $response->ABRPayloadSearchResults->response->businessEntity202001->ABN->isCurrentIndicator;
        }
        // if (isset($response->ABRPayloadSearchResults->response->exception)) {
        //     return $response->ABRPayloadSearchResults->response->exception;
        // }
        // return $abn;
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

    private function saveImage($request)
    {
        $this->image = $request->file('address_validate_file');
        if ($this->image) {
            $this->imageName = rand() . '.' . $this->image->getClientOriginalExtension();
            $this->directory = 'adminAsset/staff/address-validate-file/';
            $this->imgUrl = $this->directory . $this->imageName;
            $this->image->move($this->directory, $this->imageName);
            return $this->imgUrl;
        } else {
            return $this->image;
        }
    }
}
