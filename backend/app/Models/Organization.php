<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id', 'level', 'province_code', 'city_code',
        'district_code', 'street_code', 'name', 'short_name',
        'social_credit_code', 'type', 'status', 'address',
        'contact_person', 'contact_phone', 'legal_representative', 'remark',
    ];

    protected $casts = [
        'id' => 'integer',
        'parent_id' => 'integer',
        'level' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
        // 如果有浮点数也可以转成 float
    ];

    // 定义一对多关联：一个组织有多个银行账号
    public function bankAccounts()
    {
        return $this->hasMany(OrganizationBank::class, 'organization_id');
    }

    // 定义子组织关联
    public function children()
    {
        return $this->hasMany(Organization::class, 'parent_id', 'id');
    }

    // 定义递归子组织（核心：它会一直往下找）
    public function childrenRecursive()
    {
        return $this->children()->with(['childrenRecursive', 'bankAccounts']);
    }
}
