import { useAuthStore } from 'src/stores/auth'
export function useWorkflow() {
  const userStore = useAuthStore()
  /**
   * 1. 状态矩阵定义 (Status Matrix)
   */
  const STATUS_MAP = {
    0: { label: '草稿', color: 'grey-7', icon: 'edit' },
    1: { label: '审批中', color: 'orange', icon: 'sync' },
    2: { label: '已驳回', color: 'negative', icon: 'report_problem' },
    3: { label: '已生效', color: 'positive', icon: 'check_circle' },
    4: { label: '已过期', color: 'brown', icon: 'event_busy' },
    5: { label: '已终止', color: 'deep-orange', icon: 'stop_circle' },
    6: { label: '作废', color: 'black', icon: 'delete_sweep' }
  }

  /**
   * 2. 动作矩阵定义 (Action Matrix)
   * 统一全系统的操作图标与颜色规范
   */
  const ACTION_CONFIG = {
    view:     { label: '查看', icon: 'visibility', color: 'info' },
    add:      { label: '新增', icon: 'add',        color: 'primary' },
    edit:     { label: '修改', icon: 'edit',       color: 'warning' },
    delete:   { label: '删除', icon: 'delete',     color: 'negative' }, // 逻辑删除
    submit:   { label: '提交审核', icon: 'send',     color: 'positive' },
    withdraw: { label: '撤回', icon: 'undo',       color: 'orange' },
    approve:  { label: '审核通过', icon: 'fact_check', color: 'positive' },
    reject:   { label: '驳回', icon: 'block',      color: 'negative' },
    void:     { label: '作废', icon: 'delete_forever', color: 'black' },
    terminate:{ label: '终止', icon: 'stop',       color: 'deep-orange' }
  }

  /**
   * 判定逻辑：当前行数据是否允许该动作
   */
  const can = (row, action) => {
    if (!row || !Array.isArray(row.permitted_actions)) return false
    return row.permitted_actions.includes(action)
  }

  /**
   * 获取动作的配置
   */
  const getActionInfo = (action) => {
    return ACTION_CONFIG[action] || { label: action, icon: 'help', color: 'grey' }
  }


  /**
   * 2. 新增：静态权限校验
   * @param {String} permission 权限标识符，如 'contract-approver'
   */
  const hasAuth = (permission) => {
    return userStore.permissions.includes(permission)
  }

  /**
   * 3. 终极判定：状态与身份的“交集”
   * 只有状态允许，且用户有权限时，按钮才真正亮起
   */
  const isActionAllowed = (row, action, requiredPermission = null) => {
    const statusAllow = can(row, action)
    if (!statusAllow) return false

    if (requiredPermission) {
      return hasAuth(requiredPermission)
    }
    return true
  }

  return {
    STATUS_MAP,
    ACTION_CONFIG,
    can,
    getActionInfo,
    hasAuth,
    isActionAllowed
  }
}
