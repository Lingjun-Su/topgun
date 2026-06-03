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
        Schema::create('salary_details', function (Blueprint $table) {
            $table->id(); // 自增主键
            $table->unsignedBigInteger('employee_id')->comment('关联员工ID');
            $table->unsignedBigInteger('settlement_detail_id')->nullable()->comment('关联结算明细ID（关联农民工工资相关明细）');
            $table->string('salary_month', 6)->comment('薪资月份（格式：YYYYMM，如202612）');
            $table->unsignedTinyInteger('total_days')->comment('当月总天数（对应参考数据“31天”）');
            $table->unsignedTinyInteger('payable_days')->comment('计薪天数（对应参考数据“23天”）');
            $table->unsignedTinyInteger('absent_days')->default(0)->comment('事假/不在职天数（对应参考数据“苏灵均 不在职天”）');
            $table->unsignedTinyInteger('absenteeism_days')->default(0)->comment('旷工天数（对应参考数据“旷工”字段）');
            $table->unsignedTinyInteger('late_leave_times')->default(0)->comment('迟到早退次数（对应参考数据“迟到早退”）');
            $table->unsignedTinyInteger('sick_days')->default(0)->comment('病假天数（对应参考数据“病假天”）');
            $table->unsignedTinyInteger('paid_leave_days')->default(0)->comment('带薪休假天数（对应参考数据“带薪休假”）');
            $table->decimal('basic_salary', 18, 2)->comment('基本工资（对应参考数据“苏灵均 2700”）');
            $table->decimal('performance_salary', 18, 2)->comment('绩效工资（对应参考数据“苏灵均 2700”）');
            $table->decimal('duty_salary', 18, 2)->default(0)->comment('职务工资（对应参考数据“职务工资”）');
            $table->decimal('seniority_subsidy', 18, 2)->default(0)->comment('工龄补贴（对应参考数据“工龄补”）');
            $table->decimal('computer_subsidy', 18, 2)->default(0)->comment('电脑补贴（对应参考数据“电脑补”）');
            $table->decimal('other_subsidy', 18, 2)->default(0)->comment('其他补贴（对应参考数据“其它补”）');
            $table->decimal('performance_reward', 18, 2)->default(0)->comment('绩效奖励（对应参考数据“苏灵均 900.00”）');
            $table->decimal('attendance_deduction', 18, 2)->default(0)->comment('考勤扣款（对应参考数据“考勤扣款”）');
            $table->decimal('social_security_personal', 18, 2)->default(0)->comment('社保个人缴纳部分（对应参考数据“社保 个人”）');
            $table->decimal('housing_fund_personal', 18, 2)->default(0)->comment('公积金个人缴纳部分（对应参考数据“公积金 个人”）');
            $table->decimal('other_personal_deduction', 18, 2)->default(0)->comment('其他个人扣款（对应参考数据“其它个人扣款”）');
            $table->decimal('gross_pay', 18, 2)->comment('应发合计（自动计算：基本工资+绩效工资+各类补贴+绩效奖励）');
            $table->decimal('total_deduction', 18, 2)->comment('应扣合计（自动计算：考勤扣款+社保个人+公积金个人+其他个人扣款）');
            $table->decimal('personal_income_tax', 18, 2)->default(0)->comment('个人所得税（对应参考数据“个人所得税”）');
            $table->decimal('net_pay', 18, 2)->comment('实发总额（自动计算：应发合计-应扣合计-个人所得税）');
            $table->decimal('performance_score', 5, 2)->nullable()->comment('绩效评分（对应参考数据“绩效评分”）');
            $table->unsignedTinyInteger('status')->default(0)->comment('薪资状态：0=待核算，1=已核算，2=已发放，3=作废');
            $table->timestamps();

            // 外键关联
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->foreign('settlement_detail_id')->references('id')->on('settlement_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_details');
    }
};
