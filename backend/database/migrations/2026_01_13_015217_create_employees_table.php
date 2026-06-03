
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id(); // 自增主键
            $table->string('name', 20)->comment('真实姓名（与身份证一致，适配薪资/个税要求）');
            $table->string('id_card', 18)->unique()->comment('身份证号码（唯一索引，个税核心标识）');
            $table->unsignedTinyInteger('gender')->nullable()->comment('性别：1=男，2=女，0=未知');
            $table->string('phone', 11)->nullable()->comment('联系电话');
            $table->unsignedBigInteger('organization_id')->comment('所属组织ID');
            $table->unsignedBigInteger('department_id')->nullable()->comment('所属部门ID（农民工可关联施工队部门）');
            $table->unsignedBigInteger('position_id')->nullable()->comment('所属职位ID');
            $table->unsignedTinyInteger('type')->comment('员工类型：1=正式员工，2=农民工，3=临时人员，4=项目负责人');
            $table->date('entry_date')->comment('入职日期');
            $table->date('leave_date')->nullable()->comment('离职日期，在职时为null');
            $table->string('bank_name', 100)->nullable()->comment('个人开户银行（薪资发放用）');
            $table->string('bank_account', 30)->comment('个人银行账号（与姓名一致，薪资发放账户）');
            $table->unsignedTinyInteger('tax_register_status')->default(0)->comment('个税登记状态：0=未登记，1=已登记');
            $table->timestamps();
            $table->softDeletes();

            // 外键关联
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('department_id')->references('id')->on('departments');
            $table->foreign('position_id')->references('id')->on('positions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
