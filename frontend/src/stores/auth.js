import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { api } from 'boot/axios'; // 遵循规范：通过 boot/axios.js 中定义的 api 实例请求

export const useAuthStore = defineStore('auth', () => {
  // --- 状态定义 (State) ---
  const token = ref(localStorage.getItem('token') || '');
  const user = ref(null);         // 基础信息：id, name, account
  const department = ref(null);   // 部门信息：id, name, code
  const position = ref(null);     // 职位信息：id, title, level
  const roles = ref([]);          // 角色列表：['admin', 'finance_manager']
  const permissions = ref([]);    // 权限字串列表：['audit.approve', 'audit.reject', 'contract.view']

  // --- 计算属性 (Getters) ---
  const isAuthenticated = computed(() => !!token.value);

  /**
   * 判断是否拥有某项具体权限
   * @param {String} permission 权限标识，如 'audit.approve'
   */
  const hasPermission = computed(() => {
    return (permission) => {
      // 超级管理员直接放行
      if (roles.value.includes('super_admin')) return true;
      return permissions.value.includes(permission);
    };
  });

  /**
   * 判断是否属于某个特定部门
   * @param {String} deptCode 部门编码
   */
  const isInDepartment = computed(() => {
    return (deptCode) => department.value?.code === deptCode;
  });

  // --- 异步动作 (Actions) ---
  /**
   * 用户登录
   * @param {Object} loginForm { username, password }
   */
  async function login(loginForm) {
    try {
      // 这里的 businessApi 对应 api/modules.js 中的定义
      const response = await api.post('/v1/login', loginForm);

      // 假设后端返回符合规范：data 包含 token, user, department, position, roles, permissions
      const  data  = response;

      token.value = data.token;
      user.value = data.user;
      department.value = data.department;
      position.value = data.position;
      roles.value = data.roles;
      permissions.value = data.permissions;

      // 持久化 Token
      localStorage.setItem('auth_token', data.token);

      return true;
    } catch (error) {
      console.log("error",error)
      // 错误已被 axios 拦截器处理，此处直接向上抛出供组件处理 loading
      // throw error;
    }
  }

  /**
   * 退出登录，清空所有状态
   */
  function logout() {
    token.value = '';
    user.value = null;
    department.value = null;
    position.value = null;
    roles.value = [];
    permissions.value = [];
    localStorage.removeItem('token');
     this.router.push('login')
  }

  return {
    token,
    user,
    department,
    position,
    roles,
    permissions,
    isAuthenticated,
    hasPermission,
    isInDepartment,
    login,
    logout
  };
});
