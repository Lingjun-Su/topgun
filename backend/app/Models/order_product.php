<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class order_product extends Model
{
    use SoftDeletes;
    protected $fillable = ['name','pid','organization_id','remark','status','code']; // 白名单

    protected $casts = [
        'id'         => 'integer',//返回数字，而不是字符串
        'name'       =>'string',//名称
        'remark'     =>'string',
        'pid'        =>'integer',//pid
        'status'     =>'integer',
        'code'       =>'string',//唯一编号
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        // 如果有浮点数也可以转成 float
    ];

    /**
     * 关联到组织架构表
     * 严谨起见，明确指定关联键：organization_id 对应 organizations 表的 id
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }
}
