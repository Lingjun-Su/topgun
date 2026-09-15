import { api,v } from 'boot/axios'

/**
 * @typedef {Object} MasterContract
 * @property {number} id
 * @property {string} contract_no - 合同编号
 * @property {string} title - 合同标题
 * @property {number} status - 状态: 0=草稿, 1=审批中, 2=已生效, 3=驳回, 4=已过期, 5=已终止, 6=作废
 * @property {number} total_limit - 合同总额度
 * @property {number} org_a_id - 甲方组织ID
 * @property {number} org_b_id - 乙方组织ID
 * @property {string} effective_date - 生效日期
 * @property {string} signed_date - 签订日期
 * @property {string} expiry_date - 到期日期
 * @property {Object} organizationA - 甲方组织
 * @property {Object} organizationB - 乙方组织
 * @property {Object[]} executions - 关联执行合同
 * @property {Object[]} logs - 审核日志
 */

/**
 * @typedef {Object} ExecutionContract
 * @property {number} id
 * @property {number} master_id - 框架合同ID
 * @property {string} system_no - 系统编号
 * @property {string} external_no - 外部编号
 * @property {string} title - 标题
 * @property {number} total_amount - 金额
 * @property {string} type - 类型: REVENUE / COST
 * @property {number} status - 状态
 */

/**
 * 框架合同与执行合同 API
 */
export const masterContractApi = {
  /** 分页查询框架合同列表 */
  list: (params) => api.get(v+'/contract/master-contracts', { params }),
  /** 获取框架合同详情 */
  show: (id) => api.get(v+`/contract/master-contracts/${id}`),
  /** 创建框架合同 */
  store: (data) => api.post(v+'/contract/master-contracts', data),
  /** 更新框架合同 */
  update: (id, data) => api.put(v+`/contract/master-contracts/${id}`, data),
  /** 逻辑删除框架合同 */
  remove: (id) => api.delete(v+`/contract/master-contracts/${id}`),
  /** 提交审核记录 */
  audit: (master_id,params) => api.post(v+`/contract/audit/${master_id}`,params),

  /** 提交审核 */
  toSubmit:(id)=>api.post(v+`/contract/master-contracts/${id}/submit`),
  /** 撤回审核 */
  toWithdraw:(id)=>api.post(v+`/contract/master-contracts/${id}/withdraw`),
  /** 驳回 */
  toReject:(id,data)=>api.post(v+`/contract/master-contracts/${id}/reject`,data),
  /** 通过审批 */
  toApprove:(id,data)=>api.post(v+`/contract/master-contracts/${id}/approve`,data),
  /** 作废 */
  toVoid:(id)=>api.post(v+`/contract/master-contracts/${id}/void`),

  /** 拆分框架合同为执行合同 */
  splitContract:(data)=>  api.post(v+'/contract/contracts/split', data),

  /** 分页查询执行合同列表 */
  listExecution:(params)=>api.get(v+`/contract/contract-execution`,params),
  /** 获取执行合同详情 */
  showExecution:(id)=>api.get(v+`/contract/contract-execution/${id}`),
  /** 更新执行合同 */
  updateExecution:(id,data)=>api.put(v+`/contract/contract-execution/${id}`,data),

  /** 执行合同提交审核 */
  executionSubmit:(id)=>api.post(v+`/contract/contract-execution/${id}/submit`),
  /** 执行合同撤回审核 */
  executionWithdraw:(id)=>api.post(v+`/contract/contract-execution/${id}/withdraw`),
  /** 执行合同驳回 */
  executionReject:(id,data)=>api.post(v+`/contract/contract-execution/${id}/reject`,data),
  /** 执行合同通过审批 */
  executionApprove:(id,data)=>api.post(v+`/contract/contract-execution/${id}/approve`,data),
}