<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 控制器引用 (已根据你的代码整理)
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\DictionaryController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeAssignmentController;

// 第三方接口控制器
use App\Http\Controllers\API\ThirdChannel\QuanyuOrdersController;
use App\Http\Controllers\api\ThirdChannel\ReceiverController;

//多媒体业务
use App\Http\Controllers\API\ThirdChannelController;//渠道
use App\Http\Controllers\API\CarrierController;//运营商
use App\Http\Controllers\API\BusinessController;//业务
use App\Http\Controllers\API\ProductController;//业务产品
use App\Http\Controllers\API\ProductProvinceController;//业务产品
use App\Http\Controllers\API\UserController;//业务产品
use App\Http\Controllers\API\ProductOrderController;//业务产品
use App\Http\Controllers\API\ProductOrderStatusController;//业务产品状态测试

use App\Http\Controllers\API\AreasController;//地区

//公司管理
use App\Http\Controllers\Company\CompanyController;

//--------------报表------------
use App\Http\Controllers\Api\Reports\ProductOrderReportController;//产品订单统计

//==============报表============

//-------------合同-------------
use App\Http\Controllers\Contract\MasterContractController;//框架合同
use App\Http\Controllers\Contract\MasterContractLogController;//框架合同审核
use App\Http\Controllers\Contract\ExecutionContractController;//框架合同分割

//=============合同=============

/*
|--------------------------------------------------------------------------
| 1. 公开接口 (Public Routes)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/regions', [RegionController::class, 'index']); // 地区数据通常公开
});

/*
|--------------------------------------------------------------------------
| 2. 管理后台接口 (Protected Routes - Sanctum Auth)
|--------------------------------------------------------------------------
| 适用于 Vue3 + Quasar 前端，使用标准的 Sanctum 令牌验证
*/
// return response()->json(['message' => 'ok']);
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    //---------运营商(Carrier)-------
    Route::apiResource('carriers',CarrierController::class);

    //---------业务管理(Business)-------
    Route::apiResource('businesses',BusinessController::class);

    //---------业务产品(Product)-------
    Route::apiResource('products',ProductController::class);
    Route::apiResource('productsProvince',ProductProvinceController::class);


    //用户管理
    Route::apiResource('users', UserController::class);
    Route::post('changePassword', [UserController::class,'changePassword']);//修改自身密码


    // 渠道配置管理
    Route::apiResource('channels', ThirdChannelController::class);

    // 嵌套子资源路由：专门处理渠道下的产品配置
    Route::prefix('channels/{channel}')->group(function () {

        // 获取指定渠道下的产品配置列表
        // GET /api/v1/channels/{channel}/products
        Route::get('products', [ThirdChannelController::class, 'getProducts']);

        // 保存/同步指定渠道的产品配置
        // POST /api/v1/channels/{channel}/products
        Route::post('products', [ThirdChannelController::class, 'syncProducts']);

    });

    //---------省市区-------
    Route::get('/areas',[AreasController::class,'index']);

    // --- 用户与认证 ---
    // Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    // --- 系统管理：数据字典 ---
    Route::apiResource('dictionaries', DictionaryController::class);

    // --- 组织架构 (Organizations) ---
    Route::get('organizations/{organization}/tree', [OrganizationController::class, 'tree'])->name('organizations.tree');
    Route::get('organization-bank/{organization}/index', [OrganizationController::class, 'index'])->name('organization-bank.index');
    Route::apiResource('organizations', OrganizationController::class);


    //--------公司（Cmpany) 专指拓耕集团下公司--------
    Route::get('company',[CompanyController::class,'index']);//读取公司列表

    // --- 部门 (Departments) ---
    Route::get('organizations/{organization}/departments', [DepartmentController::class, 'byOrganization'])->name('organizations.departments');
    Route::apiResource('departments', DepartmentController::class);

    // --- 岗位 (Positions) ---
    Route::get('departments/{department}/positions', [PositionController::class, 'byDepartment'])->name('departments.positions');
    Route::get('organizations/{organization}/positions', [PositionController::class, 'byOrganization'])->name('organizations.positions');
    Route::apiResource('positions', PositionController::class);

    // --- 员工 (Employees) ---
    // 逻辑删除由控制器内部调用 $model->delete() 触发 SoftDeletes
    Route::patch('employees/{employee}/leave', [EmployeeController::class, 'leave'])->name('employees.leave');
    Route::patch('employees/{employee}/reinstate', [EmployeeController::class, 'reinstate'])->name('employees.reinstate');
    Route::apiResource('employees', EmployeeController::class);

    // --- 员工任职记录 (Assignments) ---
    Route::get('employees/{employee}/assignments', [EmployeeAssignmentController::class, 'byEmployee'])->name('employees.assignments');
    Route::get('positions/{position}/current-employees', [EmployeeAssignmentController::class, 'currentByPosition'])->name('positions.current-employees');
    Route::apiResource('employee-assignments', EmployeeAssignmentController::class);

    //产品订单
    Route::get('product-orders', [ProductOrderController::class,'index'])->defaults('env','prod');
    Route::get('product-orders/{id}', [ProductOrderController::class,'show'])->defaults('env','prod');

    //报表和图表
    Route::prefix("reports")->group(function(){
        Route::get('product-order-briefing',[ProductOrderReportController::class,'getProductOrderBriefing']);//产品订单销量统计
        Route::get('product-order-chart',[ProductOrderReportController::class,'getSalesMonthCharts']);//产品订单销量统计
        Route::get('product-order-sales-pie-chart',[ProductOrderReportController::class,'getSalesPieCharts']);//产品订单销量统计
        Route::get('product-order-sales-today-data',[ProductOrderReportController::class,'getSalesTodayData']);//产品订单销量统计
        Route::get('product-order-business-stats', [ProductOrderReportController::class, 'getBusinessStats']);
    });

    //合同管理
    Route::prefix("contract")->middleware('auth:sanctum')->group(function(){

        //框架合同
        Route::get('master-contracts',[MasterContractController::class,'index']);//合同列表
        Route::get('master-contracts/{id}',[MasterContractController::class,'show']);//合同明细
        Route::post('master-contracts',[MasterContractController::class,'store']);//合同新增
        Route::put('master-contracts/{id}',[MasterContractController::class,'update']);//合同更新


        // Route::apiResource('contracts', ContractController::class);
        // 额外动作路由（注意顺序，避免与资源路由冲突）
        Route::post('master-contracts/{contract}/submit', [MasterContractController::class, 'submit']);//提交
        Route::post('master-contracts/{contract}/withdraw', [MasterContractController::class, 'withdraw']);//撤回
        Route::post('master-contracts/{contract}/approve', [MasterContractController::class, 'approve']);//通过
        Route::post('master-contracts/{contract}/reject', [MasterContractController::class, 'reject']);//驳回
        Route::post('master-contracts/{contract}/void', [MasterContractController::class, 'void']);//废止
        Route::post('master-contracts/{contract}/terminate', [MasterContractController::class, 'terminate']);//到期
        Route::post('master-contracts/{contract}/add-clause', [MasterContractController::class, 'addClause']);//添加条款

        //合同审核
        Route::post('audit/{masterId}',[MasterContractLogController::class,'store']);//对应合同的审核过程
        Route::get('audit/{masterId}',[MasterContractLogController::class,'index']);//对应合同的审核过程

        Route::get('execution/{masterId}',[MasterContractLogController::class,'index']);//对应合同的审核过程

        //合同分割（执行合同）
        Route::post('contracts/split', [MasterContractController::class, 'splitToExecution']);

        //执行合同
        Route::get('/contract-execution', [ExecutionContractController::class, 'index']);//列表
        Route::get('/contract-execution/{id}', [ExecutionContractController::class, 'show']);//明细
        Route::put('/contract-execution/{id}', [ExecutionContractController::class, 'update']);//修改

        //执行审核
        Route::post('contract-execution/{id}/submit', [ExecutionContractController::class, 'submit']);//提交
        Route::post('contract-execution/{id}/withdraw', [ExecutionContractController::class, 'withdraw']);//撤回
        Route::post('contract-execution/{id}/approve', [ExecutionContractController::class, 'approve']);//通过
        Route::post('contract-execution/{id}/reject', [ExecutionContractController::class, 'reject']);//驳回
        Route::post('contract-execution/{id}/void', [ExecutionContractController::class, 'void']);//废止
        Route::post('contract-execution/{id}/terminate', [ExecutionContractController::class, 'terminate']);//到期
        Route::post('contract-execution/{id}/add-clause', [ExecutionContractController::class, 'addClause']);//添加条款

    });

});

/*
|--------------------------------------------------------------------------
| 3. 第三方渠道接口 (Third Party API - Independent Auth)
|--------------------------------------------------------------------------
| 注意：此处建议使用独立的 Middleware (如签名校验)，而非 Sanctum
*/
Route::prefix('v2')->group(function () {

    //测试
    Route::get('product-order-test',[ProductOrderController::class,'index'])->defaults('env','test');//读取测试订单状态
    Route::get('third-channel-order-status-test/{order_id}',[ProductOrderStatusController::class,'index'])->defaults('env','test');//读取测试订单状态
    Route::post('third-channel-test', [ReceiverController::class, 'store'])->defaults('env', 'test')->middleware('channel.auth');//新增订单
    Route::put('third-channel-test/{order_no}', [ReceiverController::class, 'update'])->defaults('env', 'test');//修改订单

    //正式
    Route::post('third-channel', [ReceiverController::class, 'store'])->defaults('env', 'prod')->middleware('channel.auth');//新增订单
    Route::put('third-channel/{order_no}', [ReceiverController::class, 'update'])->defaults('env', 'prod');//修改订单

    // Route::post('third-channel', [ReceiverController::class, 'receive'])->defaults('env', 'prod');
});

/*
|--------------------------------------------------------------------------
| 4. 调试路由 (Debug)
|--------------------------------------------------------------------------
*/
Route::get('/v2/test-debug', fn () => response()->json(['status' => 'OK', 'timestamp' => now()]));
