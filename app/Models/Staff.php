<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'start_date',
        'possion_title',
        'gender',
        'date_of_birth',
        'address',
        'suburb',
        'state',
        'postcode',
        'phone',
        'mobile',
        'email',
        'employee_tax_file',
        'super_fund',
        'member_no',
        'contractor',
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'business_number',
        'abn_entity_name',
        'abn_address',
        'abn_status',
        'abn_business_name',
        'company_number',
        'bank_name',
        'account_name',
        'bsb_number',
        'account_number',
        'aus_citizen',
        'permanent_resident',
        'visa_expiry_date',
        'restriction',
        'next_of_kin',
        'relationship',
        'kin_address',
        'kin_suburb',
        'kin_state',
        'kin_postcode',
        'kin_phone',
        'kin_mobile',
        'kin_work',
        'about_validate_file',
        'address_validate_file',

    ];
}
