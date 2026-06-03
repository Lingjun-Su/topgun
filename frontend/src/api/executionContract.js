import { api,v } from 'boot/axios'

export const executionContractApi = {

  //执行合同主体

  list: (params) => api.get(v+'/contract/execution-contracts', { params }),// 分页查询与搜索
  show: (id) => api.get(v+`/contract/execution-contracts/${id}`),// 获取单条详情
  store: (data) => api.post(v+'/contract/execution-contracts', data),// 创建
  update: (id, data) => api.put(v+`/contract/execution-contracts/${id}`, data),// 更新
  remove: (id) => api.delete(v+`/contract/execution-contracts/${id}`),// 逻辑删除
  audit: (execution_id,params) => api.post(v+`/contract/audit/${execution_id}`,params),//审核

  //流程
  toSubmit:(id)=>api.post(v+`/contract/execution-contracts/${id}/submit`),//提交
  toWithdraw:(id)=>api.post(v+`/contract/execution-contracts/${id}/withdraw`),//撤回
  toReject:(id,data)=>api.post(v+`/contract/execution-contracts/${id}/reject`,data),//驳回
  toApprove:(id,data)=>api.post(v+`/contract/execution-contracts/${id}/approve`,data),//通过
  toVoid:(id)=>api.post(v+`/contract/execution-contracts/${id}/void`),//作废

}
