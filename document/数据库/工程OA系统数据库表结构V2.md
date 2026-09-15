# 工程OA系统数据库表结构（V2调整版）

## 1. 组织信息表（`organizations`）

存储总包组织、分包施工队、甲方（招标单位）等所有相关组织信息，作为业务关联基础。

|字段名|字段类型（SQL Server）|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|组织唯一标识|符合Laravel默认主键规范，原company_id调整为id|
|parent_id|INT（外键，可空）|上级组织ID|关联organizations(id)，构建组织层级结构，顶级组织为空|
|level|TINYINT（非空，默认1）|组织层级|1=顶级组织，2=二级组织，3=三级组织等，适配层级管理|
|name|NVARCHAR(100)（非空）|组织名称|原company_name调整为name，如“中邮建”“毅和”“浩锋”“施工队”等|
|type|TINYINT（非空）|组织类型|1=甲方，2=总包，3=分包，4=施工队，5=其他，原company_type调整为type|
|province_code|VARCHAR(6)（可空）|省份编码|符合行政区划编码规则，关联地域信息|
|city_code|VARCHAR(6)（可空）|城市编码|符合行政区划编码规则，关联地域信息|
|districk_code|VARCHAR(6)（可空）|区县编码|符合行政区划编码规则，关联地域信息，字段名districk_code保持用户输入|
|street_code|VARCHAR(6)（可空）|街道编码|符合行政区划编码规则，关联地域信息|
|contact_person|NVARCHAR(20)|联系人||
|contact_phone|VARCHAR(11)|联系电话||
|address|NVARCHAR(255)|详细地址||
|status|TINYINT（非空，默认1）|状态|1=启用，0=禁用|
|remark|NVARCHAR(500)|备注||
|created_at|DATETIME2（非空）|创建时间|Laravel默认字段|
|updated_at|DATETIME2（非空）|更新时间|Laravel默认字段|


## 2. 组织银行账户表（`organization_banks`）

存储各组织的多个银行账户信息，支持指定默认账户，用于收付款业务，与组织表为一对多关系。

|字段名|字段类型（SQL Server）|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|账户唯一标识|符合Laravel默认主键规范|
|organization_id|INT（外键，非空）|关联组织ID|关联organizations(id)，建立一对多关系，级联删除|
|bank_name|NVARCHAR(100)（非空）|开户银行|如“中国工商银行XX支行”|
|bank_account|VARCHAR(30)（唯一，非空）|银行账号|唯一索引，确保账号不重复|
|is_default|TINYINT（非空，默认0）|是否默认账户|0=非默认，1=默认账户；一个组织仅允许一个默认账户|
|status|TINYINT（非空，默认1）|账户状态|1=启用，0=禁用（禁用账户不可用于收付款）|
|remark|NVARCHAR(500)|账户备注|如“主营收付款账户”“专项结算账户”等|
|created_at|DATETIME2（非空）|创建时间|Laravel默认字段|
|updated_at|DATETIME2（非空）|更新时间|Laravel默认字段|
## 3. 部门表（`departments`）

存储各组织下的部门信息，用于员工归属、权限划分及业务分工管理。

|字段名|字段类型（SQL Server）|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|部门唯一标识||
|organization_id|INT（外键，非空）|所属组织ID|关联organizations(id)|
|name|NVARCHAR(50)（非空）|部门名称|如“工程部”“财务部”“施工一队”|
|employee_id|INT（外键，可空）|部门负责人ID|关联employees(id)，原manager_id调整为employee_id|
|status|TINYINT（非空，默认1）|状态|1=启用，0=禁用|
|remark|NVARCHAR(500)|备注||
|created_at|DATETIME2（非空）|创建时间||
|updated_at|DATETIME2（非空）|更新时间||
## 4. 岗位/职位表（`positions`）

存储各组织及部门下的岗位/职位信息，用于员工职位分配、权限关联及薪资等级划分。

|字段名|字段类型（SQL Server）|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|职位唯一标识|符合Laravel默认主键规范|
|organization_id|INT（外键，非空）|所属组织ID|关联organizations(id)，确保职位归属组织|
|department_id|INT（外键，可空）|所属部门ID|关联departments(id)，支持跨部门通用职位（空值）|
|name|NVARCHAR(50)（非空）|职位名称|如“项目经理”“施工员”“财务专员”“农民工班组长”|
|description|NVARCHAR(500)|职位描述|记录职位职责、权限范围等|
|sort|INT（默认0）|排序权重|用于职位列表展示排序|
|status|TINYINT（非空，默认1）|状态|1=启用，0=禁用|
|created_at|DATETIME2（非空）|创建时间|Laravel默认字段|
|updated_at|DATETIME2（非空）|更新时间|Laravel默认字段|
## 5. 员工信息表（`employees`）

存储所有组织的员工、农民工信息，支撑薪资发放、个税报税、业务对接。

|字段名|字段类型（SQL Server）|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|员工唯一标识||
|name|NVARCHAR(20)（非空）|真实姓名|与身份证一致，适配薪资/个税要求，原full_name调整为name|
|id_card|VARCHAR(18)（唯一，非空）|身份证号码|唯一索引，个税核心标识|
|gender|TINYINT|性别|1=男，2=女，0=未知|
|phone|VARCHAR(11)|联系电话||
|organization_id|INT（外键，非空）|所属组织ID|关联organizations(id)|
|department_id|INT（外键，可空）|所属部门ID|关联departments(id)，农民工可关联施工队部门|
|position_id|INT（外键，可空）|所属职位ID|关联positions(id)，记录员工具体职位|
|type|TINYINT（非空）|员工类型|1=正式员工，2=农民工，3=临时人员，4=项目负责人，原employee_type调整为type|
|bank_name|NVARCHAR(100)|个人开户银行|薪资发放用|
|bank_account|VARCHAR(30)（非空）|个人银行账号|与姓名一致，薪资发放账户|
|tax_register_status|TINYINT（默认0）|个税登记状态|0=未登记，1=已登记|
|created_at|DATETIME2（非空）|创建时间||
|updated_at|DATETIME2（非空）|更新时间||
## 6. 员工调动表（`employee_transfers`）

记录员工入职、升迁、部门/组织调动、降级、离职等全职业周期变动信息，支撑员工履历追溯与人事分析。

|字段名|字段类型（SQL Server）|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|变动记录唯一标识|符合Laravel默认主键规范|
|employee_id|INT（外键，非空）|关联员工ID|关联employees(id)，锁定变动主体|
|transfer_type|TINYINT（非空）|变动类型|1=入职，2=组织调动，3=部门调动，4=升迁，5=降级，6=离职，7=其他变动|
|old_organization_id|INT（外键，可空）|变动前所属组织ID|关联organizations(id)，入职时为空|
|old_department_id|INT（外键，可空）|变动前所属部门ID|关联departments(id)，入职/无部门时为空|
|old_position_id|INT（外键，可空）|变动前所属职位ID|关联positions(id)，入职时为空|
|new_organization_id|INT（外键，非空）|变动后所属组织ID|关联organizations(id)，离职时仍记录最后所属组织|
|new_department_id|INT（外键，可空）|变动后所属部门ID|关联departments(id)，无部门时为空|
|new_position_id|INT（外键，可空）|变动后所属职位ID|关联positions(id)，离职时为空|
|reason|NVARCHAR(500)|变动原因|如“项目调派”“能力晋升”“个人离职”等|
|effective_date|DATE（非空）|变动生效日期|入职日期/调动生效日期/离职日期|
|operator_id|INT（外键，非空）|操作人ID|关联employees(id)，记录办理变动的人事人员|
|remark|NVARCHAR(500)|备注|记录变动过程中的特殊说明|
|created_at|DATETIME2（非空）|记录创建时间|Laravel默认字段|
|updated_at|DATETIME2（非空）|记录更新时间|Laravel默认字段|
## 7. 系统字典表（`dictionaries`）

维护枚举类型数据，新增结算相关字典类型（如发票状态、结算状态）。

|字段名|字段类型|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|字典唯一标识|原dict_id调整为id|
|type|VARCHAR(50)（非空）|字典类型编码|原dict_type调整为type，新增：settlement_status（结算状态）、invoice_status（发票状态）|
|code|VARCHAR(50)（非空）|字典项编码|原dict_code调整为code，如invoice_status的“paid”（已开票）、“unpaid”（未开票）|
|label|NVARCHAR(50)（非空）|字典项名称|原dict_name调整为name，如“已开票”“未开票”“结算中”“已完成”|
|sort|INT（默认0）|排序权重||
|status|TINYINT（默认1）|状态|1=启用，0=禁用|
# 二、工程项目与结算模块（核心业务表）

## 1. 项目投标信息表（`project_bids`）

|字段名|字段类型|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|投标记录唯一标识|原bid_id调整为id|
|no|VARCHAR(30)（唯一，非空）|投标编号|原bid_no调整为no，如BID-2026-001|
|project_name|NVARCHAR(100)（非空）|投标项目名称|引用projects表名称，用project_name，对应Excel“订单名称”|
|organization_id|INT（外键）|招标单位（甲方）ID|原tender_organization_id简化为organization_id，关联organizations(id)|
|amount|DECIMAL(18,2)（非空）|投标报价金额|原bid_amount调整为amount|
|result|TINYINT（默认0）|中标结果|原bid_result调整为result，0=待定，1=中标，2=未中标|
|winning_amount|DECIMAL(18,2)|中标金额（合同金额）||
|employee_id|INT（外键）|创建人ID|原creator_id调整为employee_id，关联employees(id)|
|created_at|DATETIME2（非空）|创建时间||
|updated_at|DATETIME2（非空）|更新时间||
## 2. 工程项目主表（`projects`）

|字段名|字段类型|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|项目唯一标识|原project_id调整为id|
|order_no|VARCHAR(30)（唯一，非空）|订单编号|对应Excel“订单编号”，如ORDER-2026-001|
|name|NVARCHAR(100)（非空）|项目名称（订单名称）|原project_name调整为name，对应Excel“订单名称”|
|province_code|VARCHAR(6)（可空）|省份编码|符合行政区划编码规则，关联地域信息|
|city_code|VARCHAR(6)（可空）|城市编码|符合行政区划编码规则，关联地域信息|
|districk_code|VARCHAR(6)（可空）|区县编码|符合行政区划编码规则，关联地域信息，字段名districk_code保持用户输入|
|street_code|VARCHAR(6)（可空）|街道编码|符合行政区划编码规则，关联地域信息|
|project_bid_id|INT（外键，可空）|关联投标记录ID|原bid_id调整为project_bid_id，关联project_bids(id)|
|organization_id|INT（外键，非空）|总包组织ID|原contractor_organization_id简化为organization_id，关联organizations(id)，如中邮建|
|employee_id|INT（外键，非空）|项目负责人ID|原project_manager_id调整为employee_id，关联employees(id)|
|region|NVARCHAR(50)|区域|对应Excel“区域”字段|
|amount|DECIMAL(18,2)（非空）|项目合同金额|原project_amount调整为amount|
|general_contract_settlement_amount|DECIMAL(18,2)（非空）|总包结算金额|项目对应的总包方结算总金额，对应Excel“总包结算金额”|
|subcontract_ratio|DECIMAL(5,2)（非空）|分包比例|项目整体分包比例，如93.90=93.9%，对应Excel“分包比例”字段|
|status|TINYINT（非空，默认1）|项目状态|原project_status调整为status，关联dictionaries(id)|
|created_at||创建时间||
|updated_at|DATETIME2（非空）|更新时间||
## 3. 工程分包合同表（`project_subcontracts`）

|字段名|字段类型|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|分包合同唯一标识|原subcontract_id调整为id|
|no|VARCHAR(30)（唯一，非空）|分包合同编号|原subcontract_no调整为no|
|project_id|INT（外键，非空）|关联项目ID|关联projects(id)|
|contractor_organization_id|INT（外键，非空）|总包组织ID|关联organizations(id)，如中邮建，保留contractor_前缀区分分包方|
|subcontractor_organization_id|INT（外键，非空）|分包组织ID|原subcontract_organization_id调整为subcontractor_organization_id，关联organizations(id)，如毅和、浩锋、施工队|
|amount|DECIMAL(18,2)（非空）|分包合同金额|原subcontract_amount调整为amount|
|ratio|DECIMAL(5,2)（非空）|分包比例|原subcontract_ratio调整为ratio，对应Excel“分包比例”，如93.90=93.9%|
|scope|NVARCHAR(1000)（非空）|分包工程范围|原subcontract_scope调整为scope|
|status|TINYINT（非空，默认1）|合同状态|原contract_status调整为status，关联dictionaries(id)|
|created_at|DATETIME2（非空）|创建时间||
|updated_at|DATETIME2（非空）|更新时间||
## 4. 项目结算主表（`project_settlements`）【新增，核心结算表】


|字段名|字段类型|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|结算记录唯一标识|原settlement_id调整为id|
|no|VARCHAR(30)（唯一，非空）|结算编号|原settlement_no调整为no，如SETTLE-2026-001|
|project_id|INT（外键，非空）|关联项目ID|关联projects(id)，关联订单编号、名称|
|receivable_amount|DECIMAL(18,2)（非空）|应收金额|项目累计应收总金额|
|payable_amount|DECIMAL(18,2)（非空）|应付金额|项目累计应付总金额|
|should_issue_invoice_amount|DECIMAL(18,2)（非空）|应开发票金额|需向合作方开具的累计发票总金额|
|should_receive_invoice_amount|DECIMAL(18,2)（非空）|应收发票金额|需从合作方收取的累计发票总金额|
|received_amount|DECIMAL(18,2)（非空，默认0）|已收金额|累计已收取的款项金额|
|paid_amount|DECIMAL(18,2)（非空，默认0）|已付金额|累计已支付的款项金额|
|unreceived_amount|DECIMAL(18,2)（非空，默认0）|未收金额|应收金额 - 已收金额，自动计算得出|
|unpaid_amount|DECIMAL(18,2)（非空，默认0）|未付金额|应付金额 - 已付金额，自动计算得出|
|issued_invoice_amount|DECIMAL(18,2)（非空，默认0）|已开发票金额|累计已向合作方开具的发票金额|
|unissued_invoice_amount|DECIMAL(18,2)（非空，默认0）|未开发票金额|应开发票金额 - 已开发票金额，自动计算得出|
|received_invoice_amount|DECIMAL(18,2)（非空，默认0）|已收发票金额|累计已从合作方收取的发票金额|
|unreceived_invoice_amount|DECIMAL(18,2)（非空，默认0）|未收发票金额|应收发票金额 - 已收发票金额，自动计算得出|
|general_contract_amount|DECIMAL(18,2)（非空）|总包结算金额|原general_contract_settlement_amount调整为general_contract_amount，对应Excel“总包结算金额”|
|arrival_date|DATE|到帐日期|对应Excel“到帐日期”|
|status|TINYINT（非空，默认0）|结算状态|原settlement_status调整为status，关联dictionaries(id)：0=结算中，1=已完成，2=已驳回|
|remark|NVARCHAR(500)|备注|对应Excel“备注”字段|
|employee_id|INT（外键，非空）|创建人ID|原creator_id调整为employee_id，关联employees(id)|
|created_at|DATETIME2（非空）|创建时间||
|updated_at|DATETIME2（非空）|更新时间||
## 5. 结算明细关联表（`settlement_details`）【新增，适配多参与方】

|字段名|字段类型（SQL Server）|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|明细唯一标识|原detail_id调整为id，符合Laravel默认主键规范|
|project_settlement_id|INT（外键，非空）|关联结算主表ID|原settlement_id调整为project_settlement_id，关联project_settlements(id)，锁定所属结算单|
|organization_id|INT（外键，非空）|参与方组织ID|关联organizations(id)，如中邮建（总包）、毅和、浩锋、施工队等结算参与主体|
|transaction_type|TINYINT（非空，默认0）|交易类型（收款/付款）|0=未明确，1=收款，2=付款，关联dictionaries表的transaction_type类型字典|
|ratio|DECIMAL(5,2)（非空）|结算比例|原settlement_ratio调整为ratio，对应Excel“毅和应得比例”“浩锋结算比例”“总包93.9%”等字段|
|amount|DECIMAL(18,2)（非空）|结算金额|原settlement_amount调整为amount，对应Excel“毅和利润”“施工队结算金额”“总包结算金额”等核心金额字段|
|invoice_amount|DECIMAL(18,2)（默认0）|开票金额|对应Excel“毅和开票金额”“开票金额 总包93.9%”等字段，未开票时为0|
|invoice_date|DATE|发票日期|对应Excel“发票日期”字段，已开票时填写，未开票时为空|
|paid_amount|DECIMAL(18,2)（默认0）|已付款金额|关联project_payables表数据，自动汇总该明细对应的已付款总额|
|unpaid_amount|DECIMAL(18,2)（默认0）|未付款金额|自动计算：amount - paid_amount，支撑付款计划制定|
|status|TINYINT（非空，默认1）|结算明细状态|关联dictionaries表的settlement_status类型字典：1=结算中，2=已完成，3=已作废|
|remark|NVARCHAR(500)|备注|记录明细特殊说明，如“含农民工工资XXX元”“扣质保金XXX元”等|
|created_at|DATETIME2（非空）|创建时间|Laravel默认字段，记录明细创建时间|
|updated_at|DATETIME2（非空）|更新时间|Laravel默认字段，记录明细更新时间|
# 三、财务收付款与开票模块（适配调整）

## 1. 项目收款记录表（`project_receivables`）

记录项目相关的所有收款信息，关联项目、结算明细及付款方组织，确保收款数据与项目结算信息精准匹配，支撑财务对账与核算。

|字段名|字段类型（SQL Server）|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|收款记录唯一标识|符合Laravel默认主键规范，原receivable_id调整为id|
|no|VARCHAR(30)（唯一，非空）|收款单号|原receivable_no调整为no，如RECV-2026-001|
|project_id|INT（外键，非空）|关联项目ID|关联projects(id)，锁定收款所属项目|
|settlement_detail_id|INT（外键，可空）|关联结算明细ID|关联settlement_details(id)，确保收款与具体结算明细对应|
|organization_id|INT（外键，非空）|付款方组织ID|原pay_company_id调整为organization_id，关联organizations(id)，如甲方、总包组织等|
|amount|DECIMAL(18,2)（非空）|收款金额|原receivable_amount调整为amount，记录实际收款金额|
|receivable_date|DATE（非空）|收款日期|原receipt_date调整为receivable_date，记录实际到账日期|
|payment_method|TINYINT（非空）|付款方式|关联dictionaries(id)的payment_method类型：1=银行转账，2=承兑汇票，3=现金，4=其他|
|bank_account_id|INT（外键，非空）|收款银行账户ID|关联organization_banks(id)，记录收款对应的本组织银行账户|
|status|TINYINT（非空，默认0）|收款状态|关联dictionaries(id)的receivable_status类型：0=待收款，1=已收款，2=部分收款，3=作废|
|remark|NVARCHAR(500)|备注|记录收款相关说明，如“项目进度款”“竣工结算款”等|
|employee_id|INT（外键，非空）|经办人ID|原creator_id调整为employee_id，关联employees(id)，记录办理收款业务的人员|
|created_at|DATETIME2（非空）|创建时间|Laravel默认字段|
|updated_at|DATETIME2（非空）|更新时间|Laravel默认字段|
## 2. 项目付款记录表（`project_payables`）

记录项目相关的所有付款信息，关联项目、结算明细及收款方组织，确保付款数据与项目结算信息精准匹配，支撑财务对账与核算。

|字段名|字段类型（SQL Server）|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|付款记录唯一标识|符合Laravel默认主键规范，原payable_id调整为id|
|no|VARCHAR(30)（唯一，非空）|付款单号|原payable_no调整为no，如PAY-2026-001|
|project_id|INT（外键，非空）|关联项目ID|关联projects(id)，锁定付款所属项目|
|settlement_detail_id|INT（外键，可空）|关联结算明细ID|关联settlement_details(id)，确保付款与具体结算明细对应|
|organization_id|INT（外键，非空）|收款方组织ID|原receive_company_id调整为organization_id，关联organizations(id)，如分包组织、施工队等|
|amount|DECIMAL(18,2)（非空）|付款金额|原payable_amount调整为amount，记录实际付款金额|
|payable_date|DATE（非空）|付款日期|原payment_date调整为payable_date，记录实际付款日期|
|payment_method|TINYINT（非空）|付款方式|关联dictionaries(id)的payment_method类型：1=银行转账，2=承兑汇票，3=现金，4=其他|
|bank_account_id|INT（外键，非空）|付款银行账户ID|关联organization_banks(id)，记录付款对应的本组织银行账户|
|status|TINYINT（非空，默认0）|付款状态|关联dictionaries(id)的payable_status类型：0=待付款，1=已付款，2=部分付款，3=作废|
|remark|NVARCHAR(500)|备注|记录付款相关说明，如“农民工工资款”“分包进度款”等|
|employee_id|INT（外键，非空）|经办人ID|原creator_id调整为employee_id，关联employees(id)，记录办理付款业务的人员|
|created_at|DATETIME2（非空）|创建时间|Laravel默认字段|
|updated_at|DATETIME2（非空）|更新时间|Laravel默认字段|
## 3. 发票信息表（`invoices`）

记录项目相关的所有发票信息，关联项目、结算明细及收开票方组织，确保发票数据与结算、收付款数据精准匹配，支撑财务对账与税务核算。字段结构在V1版基础上调整外键字段名，新增结算明细关联字段。

|字段名|字段类型（SQL Server）|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|发票记录唯一标识|符合Laravel默认主键规范，原invoice_id调整为id|
|no|VARCHAR(30)（唯一，非空）|发票单号|原invoice_no调整为no，如INVOICE-2026-001|
|project_id|INT（外键，非空）|关联项目ID|关联projects(id)，锁定发票所属项目|
|settlement_detail_id|INT（外键，可空）|关联结算明细ID|新增字段，关联settlement_details(id)，确保发票与具体结算明细对应|
|invoice_type|TINYINT（非空）|发票类型|关联dictionaries(id)的invoice_type类型：1=增值税专用发票，2=增值税普通发票，3=其他发票|
|issue_organization_id|INT（外键，非空）|开票方组织ID|原issue_company_id调整为issue_organization_id，关联organizations(id)|
|receive_organization_id|INT（外键，非空）|收票方组织ID|原receive_company_id调整为receive_organization_id，关联organizations(id)，如甲方、总包、分包组织等|
|amount|DECIMAL(18,2)（非空）|发票金额（价税合计）|原invoice_amount调整为amount，记录发票总金额|
|tax_amount|DECIMAL(18,2)（非空）|税额|发票对应的税额部分|
|tax_rate|DECIMAL(5,2)（非空）|税率|如9.00=9%，13.00=13%，0.00=免税等|
|invoice_date|DATE（非空）|发票日期|发票票面开具日期|
|invoice_code|VARCHAR(20)（非空）|发票代码|发票票面的代码信息，唯一索引（与invoice_number组合唯一）|
|invoice_number|VARCHAR(20)（非空）|发票号码|发票票面的号码信息，唯一索引（与invoice_code组合唯一）|
|status|TINYINT（非空，默认0）|发票状态|关联dictionaries(id)的invoice_status类型：0=正常，1=作废，2=红冲，3=已认证|
|remark|NVARCHAR(500)|备注|记录发票相关说明，如“项目进度款发票”“结算尾款发票”等|
|employee_id|INT（外键，非空）|经办人ID|原handler_id调整为employee_id，关联employees(id)，记录办理发票业务的人员|
|created_at|DATETIME2（非空）|创建时间|Laravel默认字段|
|updated_at|DATETIME2（非空）|更新时间|Laravel默认字段|
（字段结构同V1版，仅外键字段名调整，新增与settlement_details的关联字段settlement_detail_id，确保票款与结算明细对应）

# 四、薪资与个税报税模块（适配调整）

## 1. 薪资明细表（`salary_details`）

记录员工每月薪资的详细构成，包括基础薪资、各类补贴、扣款、个税等信息，关联员工、结算明细，支撑薪资发放与对账。

|字段名|字段类型（SQL Server）|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|薪资明细唯一标识|符合Laravel默认主键规范|
|employee_id|INT（外键，非空）|关联员工ID|关联employees(id)，锁定薪资所属员工|
|settlement_detail_id|INT（外键，可空）|关联结算明细ID|关联settlement_details(id)，关联农民工工资相关结算明细|
|salary_month|VARCHAR(6)（非空）|薪资月份|格式：YYYYMM，如202612，对应参考数据“12月”|
|total_days|TINYINT（非空）|当月总天数|对应参考数据“31天”|
|payable_days|TINYINT（非空）|计薪天数|对应参考数据“23天”|
|absent_days|TINYINT（默认0）|事假/不在职天数|对应参考数据“苏灵均 不在职天”|
|absenteeism_days|TINYINT（默认0）|旷工天数|对应参考数据“旷工”字段|
|late_leave_times|TINYINT（默认0）|迟到早退次数|对应参考数据“迟到早退”|
|sick_days|TINYINT（默认0）|病假天数|对应参考数据“病假天”|
|paid_leave_days|TINYINT（默认0）|带薪休假天数|对应参考数据“带薪休假”|
|basic_salary|DECIMAL(18,2)（非空）|基本工资|对应参考数据“苏灵均 2700”（基本工资）|
|performance_salary|DECIMAL(18,2)（非空）|绩效工资|对应参考数据“苏灵均 2700”（绩效工资）|
|duty_salary|DECIMAL(18,2)（默认0）|职务工资|对应参考数据“职务工资”|
|seniority_subsidy|DECIMAL(18,2)（默认0）|工龄补贴|对应参考数据“工龄补”|
|computer_subsidy|DECIMAL(18,2)（默认0）|电脑补贴|对应参考数据“电脑补”|
|other_subsidy|DECIMAL(18,2)（默认0）|其他补贴|对应参考数据“其它补”|
|performance_reward|DECIMAL(18,2)（默认0）|绩效奖励|对应参考数据“苏灵均 900.00”（绩效奖励）|
|attendance_deduction|DECIMAL(18,2)（默认0）|考勤扣款|对应参考数据“考勤扣款”|
|social_security_personal|DECIMAL(18,2)（默认0）|社保个人缴纳部分|对应参考数据“社保 个人”|
|housing_fund_personal|DECIMAL(18,2)（默认0）|公积金个人缴纳部分|对应参考数据“公积金 个人”|
|other_personal_deduction|DECIMAL(18,2)（默认0）|其他个人扣款|对应参考数据“其它个人扣款”|
|gross_pay|DECIMAL(18,2)（非空）|应发合计|对应参考数据“苏灵均 5400.00”，自动计算：基本工资+绩效工资+各类补贴+绩效奖励|
|total_deduction|DECIMAL(18,2)（非空）|应扣合计|对应参考数据“苏灵均 4173.91”，自动计算：考勤扣款+社保个人+公积金个人+其他个人扣款|
|personal_income_tax|DECIMAL(18,2)（默认0）|个人所得税|对应参考数据“个人所得税”|
|net_pay|DECIMAL(18,2)（非空）|实发总额|对应参考数据“苏灵均 1226.09”，自动计算：应发合计-应扣合计-个人所得税|
|performance_score|DECIMAL(5,2)（可空）|绩效评分|对应参考数据“绩效评分”|
|status|TINYINT（非空，默认0）|薪资状态|0=待核算，1=已核算，2=已发放，3=作废|
|created_at|DATETIME2（非空）|创建时间|Laravel默认字段|
|updated_at|DATETIME2（非空）|更新时间|Laravel默认字段|
## 2. 薪资发放记录表（`salary_payments`）

记录员工薪资的实际发放信息，关联薪资明细、员工及银行账户，确保薪资发放数据可追溯、可对账，支撑薪资发放凭证管理。

|字段名|字段类型（SQL Server）|说明|备注|
|---|---|---|---|
|id|INT（自增，主键）|发放记录唯一标识|符合Laravel默认主键规范|
|no|VARCHAR(30)（唯一，非空）|发放单号|格式示例：SAL-PAY-2026-001，唯一标识每笔薪资发放业务|
|salary_detail_id|INT（外键，非空）|关联薪资明细ID|关联salary_details(id)，确保发放记录与具体薪资明细对应|
|employee_id|INT（外键，非空）|关联员工ID|关联employees(id)，锁定薪资发放对象|
|amount|DECIMAL(18,2)（非空）|实际发放金额|应与salary_details表的net_pay（实发总额）一致，支持分批发放（单条明细可对应多条发放记录）|
|payment_date|DATE（非空）|发放日期|记录薪资实际到账日期，对应银行转账日期|
|payment_method|TINYINT（非空）|付款方式|关联dictionaries(id)的payment_method类型：1=银行转账，2=现金，3=其他，与收付款模块保持一致|
|bank_account_id|INT（外键，非空）|付款银行账户ID|关联organization_banks(id)，记录发放薪资的本组织银行账户|
|employee_bank_account|VARCHAR(30)（非空）|员工收款银行账户|冗余存储员工收款账户，与employees表的bank_account一致，便于对账核查|
|status|TINYINT（非空，默认0）|发放状态|0=待发放，1=已发放，2=部分发放，3=发放失败，4=作废|
|payment_voucher|VARCHAR(255)|发放凭证附件|存储银行转账回单、发放凭证等附件路径，便于凭证归档|
|remark|NVARCHAR(500)|备注|记录发放相关说明，如“12月农民工工资发放”“补发11月绩效工资”等|
|operator_id|INT（外键，非空）|经办人ID|关联employees(id)，记录办理薪资发放业务的人员|
|created_at|DATETIME2（非空）|创建时间|Laravel默认字段|
|updated_at|DATETIME2（非空）|更新时间|Laravel默认字段|
## 3. 个人所得税报税记录表（`tax_declarations`）


# 五、核心关联逻辑说明

1. 组织-银行账户：organizations → organization_banks（1个组织可拥有多个银行账户，支持指定默认账户）

2. 组织-部门-职位-员工：organizations → departments → positions → employees（1个组织下多个部门，1个部门下多个职位，1个职位对应多个员工）

3. 员工-调动记录：employees → employee_transfers（1个员工对应多条职业变动记录，支撑履历追溯）

4. 项目-结算-参与方：projects → project_settlements → settlement_details → organizations（1个项目可多次结算，1次结算含多个参与方明细）

5. 结算-薪资：settlement_details（农民工工资部分）→ salary_details → salary_payments（确保结算中的农民工工资与实际发放一致）

6. 结算-发票-付款：settlement_details → invoices → project_payables（确保开票、付款与结算明细金额匹配）
