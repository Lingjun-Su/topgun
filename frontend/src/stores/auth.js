import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { api } from 'boot/axios'; // 遵循规范：通过 boot/axios.js 中定义的 api 实例请求

export const useAuthStore = defineStore('auth', () => {
  // --- 状态定义 (State) ---
  const token = ref(localStorage.getItem('auth_token') || '');
  const user = ref(JSON.parse(localStorage.getItem('auth_user') || 'null'));         // 基础信息：id, name, account
  const department = ref(null);   // 部门信息：id, name, code
  const position = ref(null);     // 职位信息：id, title, level
  const roles = ref([]);          // 角色列表：['admin', 'finance_manager']
  const permissions = ref([]);    // 权限字串列表：['audit.approve', 'audit.reject', 'contract.view']
  const dataPermissions = ref(JSON.parse(localStorage.getItem('auth_data_permissions') || 'null')); // 数据权限：{page_restrictions, channel_ids, business_ids, product_ids}

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

      // 后端返回 {code, status, message, data}，axios 拦截器返回 response.data
      // 实际业务数据在 response.data 中
      const { data } = response;

      token.value = data.token;
      user.value = data.user;
      department.value = data.department;
      position.value = data.position;
      roles.value = data.roles;
      permissions.value = data.permissions;
      dataPermissions.value = data.user?.data_permissions || null;

      // 持久化 Token 和用户信息、数据权限
      localStorage.setItem('auth_token', data.token);
      localStorage.setItem('auth_user', JSON.stringify(data.user));
      localStorage.setItem('auth_data_permissions', JSON.stringify(dataPermissions.value));

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
    dataPermissions.value = null;
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');
    localStorage.removeItem('auth_data_permissions');
    window.location.href = '/login';
  }

  return {
    token,
    user,
    department,
    position,
    roles,
    permissions,
    dataPermissions,
    isAuthenticated,
    hasPermission,
    isInDepartment,
    login,
    logout
  };
});
