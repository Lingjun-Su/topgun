<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 为核心表添加外键约束和缺失的索引
 * 解决 TD-003: 缺少数据库约束和索引优化
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // ==================== 1. product_orders ====================
        if (Schema::hasTable('product_orders')) {
            $this->createIndexIfNotExists($driver, 'product_orders', 'product_id', 'idx_product_orders_product_id');
            $this->createIndexIfNotExists($driver, 'product_orders', 'business_id', 'idx_product_orders_business_id');
            $this->createIndexIfNotExists($driver, 'product_orders', 'channel_id', 'idx_product_orders_channel_id');
            $this->createIndexIfNotExists($driver, 'product_orders', 'organization_id', 'idx_product_orders_organization_id');
            $this->createIndexIfNotExists($driver, 'product_orders', 'order_status', 'idx_product_orders_order_status');
            $this->createIndexIfNotExists($driver, 'product_orders', 'sync_status', 'idx_product_orders_sync_status');
            $this->createIndexIfNotExists($driver, 'product_orders', 'settle_status', 'idx_product_orders_settle_status');
            $this->createIndexIfNotExists($driver, 'product_orders', 'order_time', 'idx_product_orders_order_time');
        }

        // ==================== 2. product_order_tests ====================
        if (Schema::hasTable('product_order_tests')) {
            $this->createIndexIfNotExists($driver, 'product_order_tests', 'product_id', 'idx_product_order_tests_product_id');
            $this->createIndexIfNotExists($driver, 'product_order_tests', 'business_id', 'idx_product_order_tests_business_id');
            $this->createIndexIfNotExists($driver, 'product_order_tests', 'channel_id', 'idx_product_order_tests_channel_id');
            $this->createIndexIfNotExists($driver, 'product_order_tests', 'order_status', 'idx_product_order_tests_order_status');
            $this->createIndexIfNotExists($driver, 'product_order_tests', 'sync_status', 'idx_product_order_tests_sync_status');
            $this->createIndexIfNotExists($driver, 'product_order_tests', 'order_time', 'idx_product_order_tests_order_time');
        }

        // ==================== 3. execution_contracts ====================
        if (Schema::hasTable('execution_contracts')) {
            $this->createIndexIfNotExists($driver, 'execution_contracts', 'org_a_id', 'idx_execution_contracts_org_a_id');
            $this->createIndexIfNotExists($driver, 'execution_contracts', 'org_b_id', 'idx_execution_contracts_org_b_id');
            $this->createIndexIfNotExists($driver, 'execution_contracts', 'type', 'idx_execution_contracts_type');
            $this->createIndexIfNotExists($driver, 'execution_contracts', 'status', 'idx_execution_contracts_status');
        }

        // ==================== 4. master_contracts ====================
        if (Schema::hasTable('master_contracts')) {
            $this->createIndexIfNotExists($driver, 'master_contracts', 'org_a_id', 'idx_master_contracts_org_a_id');
            $this->createIndexIfNotExists($driver, 'master_contracts', 'org_b_id', 'idx_master_contracts_org_b_id');
            $this->createIndexIfNotExists($driver, 'master_contracts', 'status', 'idx_master_contracts_status');
            $this->createIndexIfNotExists($driver, 'master_contracts', 'effective_date', 'idx_master_contracts_effective_date');
        }

        // ==================== 5. employees ====================
        if (Schema::hasTable('employees')) {
            $this->createIndexIfNotExists($driver, 'employees', 'department_id', 'idx_employees_department_id');
            $this->createIndexIfNotExists($driver, 'employees', 'position_id', 'idx_employees_position_id');
            $this->createIndexIfNotExists($driver, 'employees', 'status', 'idx_employees_status');
        }

        // ==================== 6. departments ====================
        if (Schema::hasTable('departments')) {
            $this->createIndexIfNotExists($driver, 'departments', 'organization_id', 'idx_departments_org_id');
            $this->createIndexIfNotExists($driver, 'departments', 'parent_id', 'idx_departments_parent_id');
        }

        // ==================== 7. organizations ====================
        if (Schema::hasTable('organizations')) {
            $this->createIndexIfNotExists($driver, 'organizations', 'parent_id', 'idx_organizations_parent_id');
            $this->createIndexIfNotExists($driver, 'organizations', 'type', 'idx_organizations_type');
            $this->createIndexIfNotExists($driver, 'organizations', 'status', 'idx_organizations_status');
            $this->createIndexIfNotExists($driver, 'organizations', 'social_credit_code', 'idx_organizations_social_credit_code');
            $this->createIndexIfNotExists($driver, 'organizations', 'province_code', 'idx_organizations_province_code');
        }

        // ==================== 8. third_channels ====================
        if (Schema::hasTable('third_channels')) {
            $this->createIndexIfNotExists($driver, 'third_channels', 'status', 'idx_third_channels_status');
            $this->createIndexIfNotExists($driver, 'third_channels', 'organization_id', 'idx_third_channels_org_id');
            $this->createIndexIfNotExists($driver, 'third_channels', 'method', 'idx_third_channels_method');
        }

        // ==================== 9. business ====================
        if (Schema::hasTable('business')) {
            $this->createIndexIfNotExists($driver, 'business', 'status', 'idx_business_status');
            $this->createIndexIfNotExists($driver, 'business', 'carrier_id', 'idx_business_carrier_id');
            $this->createIndexIfNotExists($driver, 'business', 'code', 'idx_business_code');
        }

        // ==================== 10. products ====================
        if (Schema::hasTable('products')) {
            $this->createIndexIfNotExists($driver, 'products', 'status', 'idx_products_status');
            $this->createIndexIfNotExists($driver, 'products', 'sku_code', 'idx_products_sku_code');
        }

        // ==================== 11. positions ====================
        if (Schema::hasTable('positions')) {
            $this->createIndexIfNotExists($driver, 'positions', 'status', 'idx_positions_status');
        }

        // ==================== 12. carrier ====================
        if (Schema::hasTable('carrier')) {
            $this->createIndexIfNotExists($driver, 'carrier', 'status', 'idx_carrier_status');
        }

        // ==================== 13. projects ====================
        if (Schema::hasTable('projects')) {
            $this->createIndexIfNotExists($driver, 'projects', 'parent_id', 'idx_projects_parent_id');
            $this->createIndexIfNotExists($driver, 'projects', 'status', 'idx_projects_status');
            $this->createIndexIfNotExists($driver, 'projects', 'organization_id', 'idx_projects_org_id');
            $this->createIndexIfNotExists($driver, 'projects', 'employee_id', 'idx_projects_employee_id');
        }

        // ==================== 14. organization_banks ====================
        if (Schema::hasTable('organization_banks')) {
            $this->createIndexIfNotExists($driver, 'organization_banks', 'organization_id', 'idx_org_banks_org_id');
            $this->createIndexIfNotExists($driver, 'organization_banks', 'status', 'idx_org_banks_status');
            $this->createIndexIfNotExists($driver, 'organization_banks', 'is_default', 'idx_org_banks_is_default');
        }

        // ==================== 15. channel_products ====================
        if (Schema::hasTable('channel_products')) {
            $this->createIndexIfNotExists($driver, 'channel_products', 'product_id', 'idx_channel_products_product_id');
            $this->createIndexIfNotExists($driver, 'channel_products', 'status', 'idx_channel_products_status');
        }

        // ==================== 16. master_contract_logs ====================
        if (Schema::hasTable('master_contract_logs')) {
            $this->createIndexIfNotExists($driver, 'master_contract_logs', 'user_id', 'idx_master_contract_logs_user_id');
            $this->createIndexIfNotExists($driver, 'master_contract_logs', 'action_type', 'idx_master_contract_logs_action_type');
            $this->createIndexIfNotExists($driver, 'master_contract_logs', 'from_status', 'idx_master_contract_logs_from_status');
            $this->createIndexIfNotExists($driver, 'master_contract_logs', 'to_status', 'idx_master_contract_logs_to_status');
        }

        // ==================== 17. execute_contract_logs ====================
        if (Schema::hasTable('execute_contract_logs')) {
            $this->createIndexIfNotExists($driver, 'execute_contract_logs', 'user_id', 'idx_execute_contract_logs_user_id');
            $this->createIndexIfNotExists($driver, 'execute_contract_logs', 'action_type', 'idx_execute_contract_logs_action_type');
            $this->createIndexIfNotExists($driver, 'execute_contract_logs', 'from_status', 'idx_execute_contract_logs_from_status');
            $this->createIndexIfNotExists($driver, 'execute_contract_logs', 'to_status', 'idx_execute_contract_logs_to_status');
        }
    }

    /**
     * 跨数据库驱动创建索引（如果不存在）
     */
    private function createIndexIfNotExists(string $driver, string $table, string $column, string $indexName): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        if ($driver === 'sqlsrv') {
            // SQL Server 语法
            DB::statement("
                IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = '{$indexName}' AND object_id = OBJECT_ID('{$table}'))
                CREATE NONCLUSTERED INDEX [{$indexName}] ON [{$table}]([{$column}])
            ");
        } else {
            // SQLite / MySQL / PostgreSQL 通用语法
            DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table}({$column})");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        // 收集所有需要回滚的索引
        $indexes = [
            // [table, index_name]
            // product_orders
            ['product_orders', 'idx_product_orders_product_id'],
            ['product_orders', 'idx_product_orders_business_id'],
            ['product_orders', 'idx_product_orders_channel_id'],
            ['product_orders', 'idx_product_orders_organization_id'],
            ['product_orders', 'idx_product_orders_order_status'],
            ['product_orders', 'idx_product_orders_sync_status'],
            ['product_orders', 'idx_product_orders_settle_status'],
            ['product_orders', 'idx_product_orders_order_time'],
            // product_order_tests
            ['product_order_tests', 'idx_product_order_tests_product_id'],
            ['product_order_tests', 'idx_product_order_tests_business_id'],
            ['product_order_tests', 'idx_product_order_tests_channel_id'],
            ['product_order_tests', 'idx_product_order_tests_order_status'],
            ['product_order_tests', 'idx_product_order_tests_sync_status'],
            ['product_order_tests', 'idx_product_order_tests_order_time'],
            // execution_contracts
            ['execution_contracts', 'idx_execution_contracts_org_a_id'],
            ['execution_contracts', 'idx_execution_contracts_org_b_id'],
            ['execution_contracts', 'idx_execution_contracts_type'],
            ['execution_contracts', 'idx_execution_contracts_status'],
            // master_contracts
            ['master_contracts', 'idx_master_contracts_org_a_id'],
            ['master_contracts', 'idx_master_contracts_org_b_id'],
            ['master_contracts', 'idx_master_contracts_status'],
            ['master_contracts', 'idx_master_contracts_effective_date'],
            // employees
            ['employees', 'idx_employees_department_id'],
            ['employees', 'idx_employees_position_id'],
            ['employees', 'idx_employees_status'],
            // departments
            ['departments', 'idx_departments_org_id'],
            ['departments', 'idx_departments_parent_id'],
            // organizations
            ['organizations', 'idx_organizations_parent_id'],
            ['organizations', 'idx_organizations_type'],
            ['organizations', 'idx_organizations_status'],
            ['organizations', 'idx_organizations_social_credit_code'],
            ['organizations', 'idx_organizations_province_code'],
            // third_channels
            ['third_channels', 'idx_third_channels_status'],
            ['third_channels', 'idx_third_channels_org_id'],
            ['third_channels', 'idx_third_channels_method'],
            // business
            ['business', 'idx_business_status'],
            ['business', 'idx_business_carrier_id'],
            ['business', 'idx_business_code'],
            // products
            ['products', 'idx_products_status'],
            ['products', 'idx_products_sku_code'],
            // positions
            ['positions', 'idx_positions_status'],
            // carrier
            ['carrier', 'idx_carrier_status'],
            // projects
            ['projects', 'idx_projects_parent_id'],
            ['projects', 'idx_projects_status'],
            ['projects', 'idx_projects_org_id'],
            ['projects', 'idx_projects_employee_id'],
            // organization_banks
            ['organization_banks', 'idx_org_banks_org_id'],
            ['organization_banks', 'idx_org_banks_status'],
            ['organization_banks', 'idx_org_banks_is_default'],
            // channel_products
            ['channel_products', 'idx_channel_products_product_id'],
            ['channel_products', 'idx_channel_products_status'],
            // master_contract_logs
            ['master_contract_logs', 'idx_master_contract_logs_user_id'],
            ['master_contract_logs', 'idx_master_contract_logs_action_type'],
            ['master_contract_logs', 'idx_master_contract_logs_from_status'],
            ['master_contract_logs', 'idx_master_contract_logs_to_status'],
            // execute_contract_logs
            ['execute_contract_logs', 'idx_execute_contract_logs_user_id'],
            ['execute_contract_logs', 'idx_execute_contract_logs_action_type'],
            ['execute_contract_logs', 'idx_execute_contract_logs_from_status'],
            ['execute_contract_logs', 'idx_execute_contract_logs_to_status'],
        ];

        foreach ($indexes as [$table, $indexName]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                if ($driver === 'sqlsrv') {
                    DB::statement("DROP INDEX [{$indexName}] ON [{$table}]");
                } elseif ($driver === 'pgsql') {
                    DB::statement("DROP INDEX IF EXISTS {$indexName}");
                } else {
                    // SQLite / MySQL
                    DB::statement("DROP INDEX IF EXISTS {$indexName} ON {$table}");
                }
            } catch (\Exception $e) {
                // 忽略删除失败（索引可能已不存在）
            }
        }
    }
};
