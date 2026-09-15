import { api,v } from 'boot/axios'

/**
 * @typedef {Object} ExecutionContractItem
 * @property {number} id
 * @property {number} master_id - 框架合同ID
 * @property {string} system_no - 系统编号
 * @property {string} external_no - 外部编号
 * @property {string} title - 执行合同标题
 * @property {number} total_amount - 合同金额
 * @property {string} type - 类型: REVENUE / COST
 * @property {number} status - 状态: 0=草稿, 1=审批中, 2=已生效, 3=驳回, 4=已过期, 5=已终止
 * @property {string} effective_date - 生效日期
 * @property {string} expiry_date - 到期日期
 * @property {Object} masterContract - 关联框架合同
 * @property {Object[]} logs - 审核日志
 * @property {string} created_at - 创建时间
 * @property {string} updated_at - 更新时间
 */

/**
 * 执行合同管理 API
 */
export const executionContractApi = {
  /** 分页查询执行合同列表 */
  list: (params) => api.get(v+'/contract/execution-contracts', { params }),
  /** 获取执行合同详情 */
  show: (id) => api.get(v+`/contract/execution-contracts/${id}`),
  /** 创建执行合同 */
  store: (data) => api.post(v+'/contract/execution-contracts', data),
  /** 更新执行合同 */
  update: (id, data) => api.put(v+`/contract/execution-contracts/${id}`, data),
  /** 逻辑删除执行合同 */
  remove: (id) => api.delete(v+`/contract/execution-contracts/${id}`),
  /** 提交审核记录 */
  audit: (execution_id,params) => api.post(v+`/contract/audit/${execution_id}`,params),

  /** 提交审核 */
  toSubmit:(id)=>api.post(v+`/contract/execution-contracts/${id}/submit`),
  /** 撤回审核 */
  toWithdraw:(id)=>api.post(v+`/contract/execution-contracts/${id}/withdraw`),
  /** 驳回 */
  toReject:(id,data)=>api.post(v+`/contract/execution-contracts/${id}/reject`,data),
  /** 通过审批 */
  toApprove:(id,data)=>api.post(v+`/contract/execution-contracts/${id}/approve`,data),
  /** 作废 */
  toVoid:(id)=>api.post(v+`/contract/execution-contracts/${id}/void`),
}