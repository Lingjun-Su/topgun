import { api,v } from 'boot/axios'

export const masterContractApi = {

  //框架合同主体

  list: (params) => api.get(v+'/contract/master-contracts', { params }),// 分页查询与搜索
  show: (id) => api.get(v+`/contract/master-contracts/${id}`),// 获取单条详情
  store: (data) => api.post(v+'/contract/master-contracts', data),// 创建
  update: (id, data) => api.put(v+`/contract/master-contracts/${id}`, data),// 更新
  remove: (id) => api.delete(v+`/contract/master-contracts/${id}`),// 逻辑删除
  audit: (master_id,params) => api.post(v+`/contract/audit/${master_id}`,params),//审核

  //流程
  toSubmit:(id)=>api.post(v+`/contract/master-contracts/${id}/submit`),//提交
  toWithdraw:(id)=>api.post(v+`/contract/master-contracts/${id}/withdraw`),//撤回
  toReject:(id,data)=>api.post(v+`/contract/master-contracts/${id}/reject`,data),//驳回
  toApprove:(id,data)=>api.post(v+`/contract/master-contracts/${id}/approve`,data),//通过
  toVoid:(id)=>api.post(v+`/contract/master-contracts/${id}/void`),//作废

  //分割框架合同
  splitContract:(data)=>  api.post(v+'/contract/contracts/split', data),

  //执行合同
  listExecution:(params)=>api.get(v+`/contract/contract-execution`,params),//列表
  showExecution:(id)=>api.get(v+`/contract/contract-execution/${id}`),//显示
  updateExecution:(id,data)=>api.put(v+`/contract/contract-execution/${id}`,data),//修改

  executionSubmit:(id)=>api.post(v+`/contract/contract-execution/${id}/submit`), //提交审核
  executionWithdraw:(id)=>api.post(v+`/contract/contract-execution/${id}/withdraw`), //撤回审核
  executionReject:(id,data)=>api.post(v+`/contract/contract-execution/${id}/reject`,data), //驳回审核
  executionApprove:(id,data)=>api.post(v+`/contract/contract-execution/${id}/approve`,data), //通过审核

}
