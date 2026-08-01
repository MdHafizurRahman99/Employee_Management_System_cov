<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('business.list', [
            'businesses' => BusinessProfile::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('business.business-profile');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // return $request;
        $validator = Validator::make($request->all(), [
            'company_name' => 'required',
            'company_type' => 'required',
            'phone' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'fax' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'email' => ['required', 'regex:/[^\s@]+@[^\s@]+\.[a-zA-Z]{2,6}/',],
            'mobile' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],

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
                if (!$this->ValidateABN($value)) {
                    $fail('The ABN format is invalid.');
                }
            },],

            'company_number' => ['required', 'regex:/^[0-9 ]+$/', 'min:9', 'max:11', function ($attribute, $value, $fail) {
                if (!$this->ValidateACN($value)) {
                    $fail('The ACN format is invalid.');
                }
            },],

            'accountants_number' => 'required|numeric',
            'software_name' => 'required',
            'api_key' => 'required',

        ], [
            'required' => 'The :attribute field is required.',
            'numeric' => 'The :attribute must contain only numbers.',
            'phone.regex' => 'The :attribute format is invalid.',
            'fax.regex' => 'The :attribute format is invalid.',
            'mobile.regex' => 'The :attribute format is invalid.',
            'email' => 'The :attribute must be a valid email address.',
            'email.regex' => 'The :attribute must be in a valid email format.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'business_number.regex' => 'The :attribute can only contain spaces and numbers.',
            'company_number.regex' => 'The :attribute can only contain spaces and numbers.',
        ]);

        $validator->setAttributeNames([

            'company_name' => 'Company Name',
            'company_type' => 'Company Type',
            'mobile' => 'Mobile',
            'accountants_number' => 'Accountants Number',
            'software_name' => 'Software Name',
            'api_key' => 'Api Key',
            'phone' => 'Phone',
            'fax' => 'Fax',
            'email' => 'Email',
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

            'business_number' => 'ABN',
            'company_number' => 'ACN',


        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $phoneNumber = '+' . $request->phone_country_dial_code . '  ' . $request->phone;
        $faxNumber = '+' . $request->fax_country_dialCode . '  ' . $request->fax;
        $mobileNumber = '+' . $request->mobile_country_dialCode . '  ' . $request->mobile;

        //formting abn
        $abn = preg_replace("/[^\d]/", "", $request->business_number);
        $output_abn = chunk_split(substr($abn, 0, 2), 2, ' ') . chunk_split(substr($abn, 2), 3, ' ');
        $output_abn = rtrim($output_abn);

        //formting acn 
        $acn = preg_replace("/[^\d]/", "", $request->company_number);
        $output_acn =  chunk_split($acn, 3, ' ');
        $output_acn = rtrim($output_acn);

        if ($request->sameAsPhysical == '1') {

            $busniessProfile = BusinessProfile::create([
                'company_name' => $request->company_name,
                'company_type' => $request->company_type,
                'business_number' => $output_abn,
                'company_number' => $output_acn,
                'phone' => $phoneNumber,
                'fax' => $faxNumber,
                'email' => $request->email,
                'mobile' => $mobileNumber,
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

                'accountants_number' => $request->accountants_number,
                'software_name' => $request->software_name,
                'api_key' => $request->api_key,
            ]);
        } else {
            $busniessProfile = BusinessProfile::create([
                'company_name' => $request->company_name,
                'company_type' => $request->company_type,
                'business_number' => $output_abn,
                'company_number' => $output_acn,
                'phone' => $phoneNumber,
                'fax' => $faxNumber,
                'email' => $request->email,
                'mobile' => $mobileNumber,
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
                'accountants_number' => $request->accountants_number,
                'software_name' => $request->software_name,
                'api_key' => $request->api_key,
            ]);
        }

        return back()->with('message', 'Business profile submitted successfully.');
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(BusinessProfile $business)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BusinessProfile $business)
    {
        return view('business.edit', [
            'business' => $business,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BusinessProfile $business)
    {
        // return $request;
        $validator = Validator::make($request->all(), [
            'company_name' => 'required',
            'company_type' => 'required',
            'phone' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'fax' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],
            'email' => ['required', 'regex:/[^\s@]+@[^\s@]+\.[a-zA-Z]{2,6}/',],
            'mobile' => ['required', 'regex:/^[0-9\s()-]+$/', 'min:7', 'max:20',],

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
                if (!$this->ValidateABN($value)) {
                    $fail('The ABN format is invalid.');
                }
            },],

            'company_number' => ['required', 'regex:/^[0-9 ]+$/', 'min:9', 'max:11', function ($attribute, $value, $fail) {
                if (!$this->ValidateACN($value)) {
                    $fail('The ACN format is invalid.');
                }
            },],

            'accountants_number' => 'required|numeric',
            'software_name' => 'required',
            'api_key' => 'required',

        ], [
            'required' => 'The :attribute field is required.',
            'numeric' => 'The :attribute must contain only numbers.',
            'phone.regex' => 'The :attribute format is invalid.',
            'fax.regex' => 'The :attribute format is invalid.',
            'mobile.regex' => 'The :attribute format is invalid.',
            'email' => 'The :attribute must be a valid email address.',
            'email.regex' => 'The :attribute must be in a valid email format.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'business_number.regex' => 'The :attribute can only contain spaces and numbers.',
            'company_number.regex' => 'The :attribute can only contain spaces and numbers.',
        ]);

        $validator->setAttributeNames([

            'company_name' => 'Company Name',
            'company_type' => 'Company Type',
            'mobile' => 'Mobile',
            'accountants_number' => 'Accountants Number',
            'software_name' => 'Software Name',
            'api_key' => 'Api Key',
            'phone' => 'Phone',
            'fax' => 'Fax',
            'email' => 'Email',
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

            'business_number' => 'ABN',
            'company_number' => 'ACN',


        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $phoneNumber = '+' . $request->phone_country_dial_code . '  ' . $request->phone;
        $faxNumber = '+' . $request->fax_country_dialCode . '  ' . $request->fax;
        $mobileNumber = '+' . $request->mobile_country_dialCode . '  ' . $request->mobile;
        // return $mobileNumber;
        //formting abn
        $abn = preg_replace("/[^\d]/", "", $request->business_number);
        $output_abn = chunk_split(substr($abn, 0, 2), 2, ' ') . chunk_split(substr($abn, 2), 3, ' ');
        $output_abn = rtrim($output_abn);

        //formting acn 
        $acn = preg_replace("/[^\d]/", "", $request->company_number);
        $output_acn =  chunk_split($acn, 3, ' ');
        $output_acn = rtrim($output_acn);

        if ($request->sameAsPhysical == '1') {

            $business->update([
                'company_name' => $request->company_name,
                'company_type' => $request->company_type,
                'business_number' => $output_abn,
                'company_number' => $output_acn,
                'phone' => $phoneNumber,
                'fax' => $faxNumber,
                'email' => $request->email,
                'mobile' => $mobileNumber,
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

                'accountants_number' => $request->accountants_number,
                'software_name' => $request->software_name,
                'api_key' => $request->api_key,
            ]);
        } else {
            $business->update([
                'company_name' => $request->company_name,
                'company_type' => $request->company_type,
                'business_number' => $output_abn,
                'company_number' => $output_acn,
                'phone' => $phoneNumber,
                'fax' => $faxNumber,
                'email' => $request->email,
                'mobile' => $mobileNumber,
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
                'accountants_number' => $request->accountants_number,
                'software_name' => $request->software_name,
                'api_key' => $request->api_key,
            ]);
        }
        return redirect()->route('business.index')->with('message', 'Business Profile Updated Successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BusinessProfile $business)
    {
        $business->delete();

        return redirect()->route('business.index')->with('success', 'Business Profile Deleted Successfully.');
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

    public function status($business_id)
    {
        $business = BusinessProfile::find($business_id);

        if ($business) {
            if ($business->status == '0') {
                $business->update([
                    'status' => '1'
                ]);
            } else {
                $business->update([
                    'status' => '0'
                ]);
            }
            return back()->with('success', 'Status Updated Successfully.');
        }
        // return $business;

    }
}
