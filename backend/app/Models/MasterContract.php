<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MasterContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'master_contracts';

    protected $fillable = [
        'contract_no', 'title', 'org_a_id', 'org_b_id',
        'total_limit', 'version', 'status',
        'signed_date', 'effective_date', 'expiry_date',
        'signer_a', 'contact_a', 'signer_b', 'contact_b',
        'contact_a_phone','contact_b_phone',
        'summary', 'remarks'
    ];

    protected $casts = [//输出格式
        'total_limit'    => 'decimal:2',
        'signed_date'    => 'date:Y-m-d',
        'effective_date' => 'date:Y-m-d',
        'expiry_date'    => 'date:Y-m-d',
        'version'        => 'integer',
        'status'        => 'integer',
    ];


    /**
     * 关联甲方组织
     */
    public function organizationA()
    {
        return $this->belongsTo(Organization::class, 'org_a_id');
    }

    /**
     * 关联乙方组织
     */
    public function organizationB()
    {
        return $this->belongsTo(Organization::class, 'org_b_id');
    }

}
