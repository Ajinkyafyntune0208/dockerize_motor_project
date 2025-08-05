<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserManagementModel extends Model
{
    protected $table = 'users';

    protected $fillable = ['user_id', 'role_id', 'permission_id', 'module_id', 'policy_id', 'endoresment_no', 'company_id', 'designation', 'user_for', 'user_type_id', 'user_level', 'parent_id', 'username', 'password', 'email', 'first_name', 'middle_name', 'last_name', 'dob', 'mobile_no', 'alternate_mobile_no', 'office_type', 'corporate_office', 'regional_office', 'branch_office', 'address', 'city', 'district', 'state', 'pincode', 'gender', 'pan_no', 'aadhar_no', 'married_status', 'profile_pic', 'ip_address', 'created_by', 'updated_by', 'last_login_date', 'status', 'lock_status', 'lock_date', 'password_status', 'created_on', 'updated_on', 'deleted_on', 'branch_city', 'from_api_hit'];
}
