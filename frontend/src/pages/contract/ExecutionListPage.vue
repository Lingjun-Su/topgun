<template>
  <q-page class="q-pa-md bg-grey-1">
    <!-- 1. 顶部面包屑与标题栏 -->
    <div class="row items-center justify-between q-mb-md">
      <div>
        <div class="text-h5 text-weight-bold text-primary">执行合同管理</div>
        <q-breadcrumbs class="text-grey-6 q-mt-xs" active-color="primary">
          <q-breadcrumbs-el label="主页" icon="home" />
          <q-breadcrumbs-el label="合同管理" />
          <q-breadcrumbs-el label="执行合同列表" />
        </q-breadcrumbs>
      </div>
      <!-- 固锁：只有具备创建权限的人员才显示新增按钮 -->
      <q-btn
        v-if="hasAuth('execution-contract-create')"
        color="primary"
        icon="add"
        label="新建执行合同"
        @click="openCreateDialog"
      />
    </div>

    <!-- 2. 高级检索过滤区 -->
    <q-card flat bordered class="q-mb-md bg-white">
      <q-card-section class="row q-col-gutter-md items-center">
        <div class="col-12 col-md-4">
          <q-input
            v-model="filter.keyword"
            outlined
            dense
            clearable
            placeholder="输入合同编号或标题搜索..."
            @keyup.enter="refreshList"
          >
            <template v-slot:append>
              <q-icon name="search" class="cursor-pointer" @click="refreshList" />
            </template>
          </q-input>
        </div>
        <div class="col-12 col-md-3">
          <q-select
            v-model="filter.status"
            outlined
            dense
            clearable
            emit-value
            map-options
            :options="statusOptions"
            label="合同状态"
            @update:model-value="refreshList"
          />
        </div>
        <div class="col-12 col-md-2">
          <q-btn color="primary" label="查询" icon="search" @click="refreshList" class="full-width" />
        </div>
        <div class="col-12 col-md-1">
          <q-btn outline color="grey-7" label="重置" @click="resetFilter" class="full-width" />
        </div>
      </q-card-section>
    </q-card>

    <!-- 3. 数据表格区 (Quasar q-table) -->
    <q-table
      flat
      bordered
      binary-state-sort
      row-key="id"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      v-model:pagination="pagination"
      @request="onRequest"
      class="bg-white"
    >
      <!-- 槽位优化：组织架构主体展示 -->
      <template v-slot:body-cell-organization_a="props">
        <q-td :props="props">
          <q-badge outline color="indigo" :label="props.row.organization_a?.name || '未指定'" />
        </q-td>
      </template>

      <!-- 槽位优化：合同状态美化展示 -->
      <template v-slot:body-cell-status="props">
        <q-td :props="props" class="text-center">
          <q-badge :color="getStatusBadge(props.row.status).color" :label="getStatusBadge(props.row.status).label" />
        </q-td>
      </template>

      <!-- 槽位优化：金额格式化 (千分位) -->
      <template v-slot:body-cell-amount="props">
        <q-td :props="props" class="text-right text-weight-bold text-negative">
          ￥{{ formatMoney(props.row.amount) }}
        </q-td>
      </template>

      <!-- 核心槽位：动态动作矩阵渲染 (数据驱动安全锁) -->
      <template v-slot:body-cell-actions="props">
        <q-td :props="props" class="q-gutter-xs text-center">
          <!-- 查看详情：客观永远允许查看 -->
          <q-btn flat round dense color="primary" icon="visibility" @click="handleView(props.row)">
            <q-tooltip>查看详情</q-tooltip>
          </q-btn>

          <!-- 编辑：状态允许且具备修改身份[cite: 1] -->
          <q-btn
            v-if="isActionAllowed(props.row, 'update', 'execution-contract-edit')"
            flat round dense color="orange" icon="edit" @click="handleEdit(props.row)"
          >
            <q-tooltip>修改合同</q-tooltip>
          </q-btn>

          <!-- 提交审核：状态允许且具备负责人身份[cite: 1] -->
          <q-btn
            v-if="isActionAllowed(props.row, 'submit', 'execution-contract-own')"
            flat round dense color="green" icon="send" @click="handleSubmit(props.row)"
          >
            <q-tooltip>提交审核</q-tooltip>
          </q-btn>

          <!-- 审批通过：状态允许且具备审核员身份[cite: 1] -->
          <q-btn
            v-if="isActionAllowed(props.row, 'approve', 'execution-contract-audit')"
            flat round dense color="positive" icon="check_circle" @click="handleApprove(props.row)"
          >
            <q-tooltip>审批通过</q-tooltip>
          </q-btn>

          <!-- 审批驳回：状态允许且具备审核员身份[cite: 1] -->
          <q-btn
            v-if="isActionAllowed(props.row, 'reject', 'execution-contract-audit')"
            flat round dense color="red" icon="cancel" @click="handleReject(props.row)"
          >
            <q-tooltip>审批驳回</q-tooltip>
          </q-btn>

          <!-- 逻辑删除：状态允许且具备特定身份[cite: 1] -->
          <q-btn
            v-if="isActionAllowed(props.row, 'delete', 'execution-contract-delete')"
            flat round dense color="negative" icon="delete" @click="handleDelete(props.row)"
          >
            <q-tooltip>移入回收站</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- 4. 审批流意见弹窗 (用于驳回操作) -->
    <q-dialog v-model="commentDialog.show" persistent>
      <q-card style="min-width: 400px">
        <q-card-section class="row items-center bg-primary text-white">
          <div class="text-h6">{{ commentDialog.title }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>

        <q-card-section class="q-pt-md">
          <q-input
            v-model="commentDialog.comment"
            type="textarea"
            outlined
            label="请输入审批/驳回意见 (必填)"
            :rules="[val => !!val || '意见不能为空']"
          />
        </q-card-section>

        <q-card-actions align="right" class="text-primary q-pb-md q-pr-md">
          <q-btn flat label="取消" v-close-popup />
          <q-btn color="primary" label="确认提交" @click="confirmCommentAction" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useQuasar } from 'quasar'
import { executionContractApi } from 'src/api/executionContract' // 遵循模块化接口规范
import { useWorkflow } from 'src/composables/useWorkflow' // 引入正交解耦状态机钩子[cite: 1]

const $q = useQuasar()
const { hasAuth, isActionAllowed } = useWorkflow() // 映射静态权限校验与动态矩阵交叉校验[cite: 1]

// --- 状态定义 ---
const rows = ref([])
const loading = ref(false)

// 严格遵循数据字典约定的前端检索选项
const filter = reactive({
  keyword: '',
  status: null
})

// 分页状态 (对接后端 LengthAwarePaginator)
const pagination = ref({
  sortBy: 'id',
  descending: true,
  page: 1,
  rowsPerPage: 15,
  rowsNumber: 0
})

// 静态状态字典映射
const statusOptions = [
  { label: '草稿', value: 0 },
  { label: '审批中', value: 1 },
  { label: '已驳回', value: 2 },
  { label: '已生效', value: 3 },
  { label: '已过期', value: 4 },
  { label: '已终止', value: 5 },
  { label: '已作废', value: 6 }
]

// 表头定义
const columns = [
  { name: 'contract_no', label: '合同编号', field: 'contract_no', align: 'left', sortable: true },
  { name: 'title', label: '合同标题', field: 'title', align: 'left', maxw: '200px' },
  { name: 'organization_a', label: '所属组织A', field: 'organization_a', align: 'left' },
  { name: 'amount', label: '合同金额', field: 'amount', align: 'right', sortable: true },
  { name: 'party_a', label: '甲方主体', field: 'party_a', align: 'left' },
  { name: 'status', label: '状态', field: 'status', align: 'center' },
  { name: 'start_at', label: '开始日期', field: 'start_at', align: 'center' },
  { name: 'end_at', label: '结束日期', field: 'end_at', align: 'center' },
  { name: 'actions', label: '操作控制矩阵', field: 'actions', align: 'center' }
]

// 意见弹窗交互状态
const commentDialog = reactive({
  show: false,
  title: '',
  comment: '',
  row: null,
  type: '' // 'approve' 或 'reject'
})

// --- 核心逻辑加载 ---
/**
 * 分页与条件数据拉取引擎
 */
const loadData = async (page = 1, perPage = 15) => {
  loading.value = true
  try {
    // 严格透传分页参数与过滤矩阵
    const params = {
      page,
      per_page: perPage,
      keyword: filter.keyword || undefined,
      status: filter.status !== null ? filter.status : undefined
    }

    // axios 拦截器已实现自动拆包，此处直接获取数据主体[cite: 1]
    const res = await executionContractApi.list(params)

    rows.value = res.data
    pagination.value.page = res.current_page
    pagination.value.rowsPerPage = res.per_page
    pagination.value.rowsNumber = res.total
  } catch (error) {
    console.log("error",error);
    // 异常已被 axios 拦截器捕获并弹出 Notification 红色警告，此处仅做静默挂起[cite: 1]
  } finally {
    loading.value = false
  }
}

/**
 * 触发基于 Quasar Table 内部机制的分页切换
 */
const onRequest = (props) => {
  const { page, rowsPerPage } = props.pagination
  loadData(page, rowsPerPage)
}

const refreshList = () => {
  loadData(1, pagination.value.rowsPerPage)
}

const resetFilter = () => {
  filter.keyword = ''
  filter.status = null
  refreshList()
}

// --- 业务状态流转动作执行 ---

/**
 * 动作：提交审核
 */
const handleSubmit = (row) => {
  $q.dialog({
    title: '工作流确认',
    message: `您确认要把执行合同 [${row.contract_no}] 提交至审批流程吗？`,
    cancel: true,
    persistent: true
  }).onOk(async () => {
    try {
      await executionContractApi.submit(row.id)
      refreshList() // 刷新列表，按钮状态会自动随 permitted_actions 联动刷新
    } catch (e) {
      console.log('e',e);
    }
  })
}

/**
 * 动作：审批通过弹出处理
 */
const handleApprove = (row) => {
  commentDialog.show = true
  commentDialog.title = '合同审批通过'
  commentDialog.comment = '审批通过'
  commentDialog.row = row
  commentDialog.type = 'approve'
}

/**
 * 动作：审批驳回弹出处理
 */
const handleReject = (row) => {
  commentDialog.show = true
  commentDialog.title = '合同审批驳回原因留痕'
  commentDialog.comment = ''
  commentDialog.row = row
  commentDialog.type = 'reject'
}

/**
 * 弹窗确认统一流转提交
 */
const confirmCommentAction = async () => {
  if (!commentDialog.comment.trim()) {
    $q.notify({ type: 'negative', message: '请必须填写审核意见批注' })
    return
  }

  try {
    if (commentDialog.type === 'approve') {
      await executionContractApi.approve(commentDialog.row.id, { comment: commentDialog.comment })
    } else {
      await executionContractApi.reject(commentDialog.row.id, { comment: commentDialog.comment })
    }
    commentDialog.show = false
    refreshList()
  } catch (e) {
    console.log('e',e);
  }
}

/**
 * 动作：安全逻辑删除 (Soft Delete)[cite: 1]
 */
const handleDelete = (row) => {
  $q.dialog({
    title: '高危数据移除警告',
    message: `您确认将合同 [${row.contract_no}] 移入回收站吗？此操作虽然属于逻辑删除，但在恢复前该合同将失效。`,
    cancel: { color: 'grey-7', flat: true },
    ok: { color: 'negative' },
    persistent: true
  }).onOk(async () => {
    try {
      await executionContractApi.destroy(row.id)
      refreshList()
    } catch (e) {
      console.log('e',e);
    }
  })
}

// --- 辅助 UI 工具方法 ---
const getStatusBadge = (status) => {
  const map = {
    0: { color: 'grey-7', label: '草稿' },
    1: { color: 'orange-8', label: '审批中' },
    2: { color: 'red-7', label: '已驳回' },
    3: { color: 'green-7', label: '已生效' },
    4: { color: 'brown-6', label: '已过期' },
    5: { color: 'blue-grey-7', label: '已终止' },
    6: { color: 'purple-7', label: '已作废' }
  }
  return map[status] || { color: 'black', label: '未知状态' }
}

const formatMoney = (val) => {
  if (!val) return '0.00'
  return parseFloat(val).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')
}

// 基础脚手架跳转占位
const openCreateDialog = () => $q.notify({ type: 'info', message: '触发新建执行合同表单，跳转至明细编辑页...' })
const handleView = (row) => $q.notify({ type: 'info', message: `查看合同 [${row.contract_no}] 详细审计历史线...` })
const handleEdit = (row) => $q.notify({ type: 'info', message: `编辑模式加载合同 [${row.contract_no}] 的基础数据...` })

// 挂载执行生命周期
onMounted(() => {
  refreshList()
})
</script>

<style scoped>
/* 保证长标题列在 Quasar 表格内优雅换行且不溢出 */
.maxw-200px {
  max-width: 200px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
