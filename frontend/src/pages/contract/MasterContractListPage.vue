<template>
  <q-page class="q-pa-md">
    <q-card flat bordered class="q-mb-md bg-grey-1">
      <q-card-section class="row items-center q-gutter-sm">
        <div class="text-h6 text-primary q-mr-md">框架合同台账</div>

        <q-input
          v-model="filter.keyword"
          dense
          outlined
          placeholder="搜索系统号/业务号/供应商..."
          class="bg-white"
          style="width: 300px"
          @keyup.enter="fetchList"
        >
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>

        <q-select
          v-model="filter.status"
          :options="statusOptions"
          label="合同状态"
          dense
          outlined
          emit-value
          map-options
          class="bg-white"
          style="width: 150px"
          clearable
          @update:model-value="fetchList"
        />

        <q-space />

        <q-btn
          color="primary"
          icon="add"
          label="新建框架合同"
          @click="handleCreate"
        />
      </q-card-section>
    </q-card>

    <q-card flat bordered>
      <q-table
        :rows="rows"
        :columns="columns"
        row-key="id"
        :loading="loading"
        :pagination="pagination"
        binary-state-sort
        flat
      >
        <template v-slot:body-cell-status="props">
          <q-td :props="props">
            <q-chip
              :color="statusMap[props.value].color"
              text-color="white"
              size="sm"
              square
            >
              {{ statusMap[props.value].label }}
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-total_amount="props">
          <q-td :props="props" class="text-weight-bold">
            {{ formatMoney(props.value) }}
          </q-td>
        </template>

        <template v-slot:body-cell-balance="props">
          <q-td :props="props">
            <span :class="props.value < 0 ? 'text-negative' : 'text-secondary'">
              {{ formatMoney(props.value) }}
            </span>
          </q-td>
        </template>

        <template v-slot:body-cell-actions="props">
          <q-td :props="props" class="q-gutter-xs">
            <q-btn flat round size="sm" color="info" icon="visibility" @click="goDetail(props.row.id)">
              <q-tooltip>查看详情</q-tooltip>
            </q-btn>

            <q-btn
              v-if="props.row.status === 0 || props.row.status === 2"
              flat round size="sm" color="green" icon="edit"
              @click="handleEdit(props.row.id)"
            >
              <q-tooltip>草稿或驳回状态可以修改</q-tooltip>
            </q-btn>
            <q-btn
              v-if="props.row.status === 0"
              flat round size="sm" color="negative" icon="delete"
              @click="confirmDelete(props.row.id,props.row.contract_no)"
            >
              <q-tooltip>仅可以删除草稿</q-tooltip>
            </q-btn>
            <q-btn
              v-if="props.row.status === 1"
              flat round size="sm" color="negative" icon="fact_check"
              @click="toAudit(props.row.id)"
            >
              <q-tooltip>审核</q-tooltip>
            </q-btn>
            <q-btn
              v-if="props.row.status === 0 || props.row.status==2"
              flat round size="sm" color="green" icon="done_outline"
              @click="toSubmit(props.row.id,props.row.contract_no)"
            >
              <q-tooltip>提交审核</q-tooltip>
            </q-btn>
            <q-btn
              v-if="props.row.status === 1"
              flat round size="sm" color="warning" icon="undo"
              @click="toWithdraw(props.row.id,props.row.contract_no)"
            >
              <q-tooltip>撤回审核</q-tooltip>
            </q-btn>

            <q-btn
              v-if="props.row.status === 3"
              flat round size="sm" color="secondary" icon="content_cut"
              @click="splitContract(props.row.id)"
            >
              <q-tooltip>分切执行单</q-tooltip>
            </q-btn>


          </q-td>
        </template>
      </q-table>
    </q-card>

    <execution-split-dialog ref="splitDialog" @success="fetchList" />
  </q-page>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { masterContractApi } from 'src/api/masterContract'

const $q = useQuasar()
const router = useRouter()

// --- 数据定义 ---
const loading = ref(false)
const rows = ref([])
const splitDialog = ref(null)

const filter = reactive({
  keyword: '',
  status: null
})

const pagination = ref({
  sortBy: 'created_at',
  descending: true,
  page: 1,
  rowsPerPage: 15
})

// 状态映射表（严谨维护业务状态）
const statusMap = {
  0: { label: '草 稿', color: 'grey-7' },
  1: { label: '审批中', color: 'orange-8' },
  2: { label: '驳 回', color: 'warning' },
  3: { label: '已生效', color: 'positive' },
  4: { label: '已过期', color: 'brown' },
  5: { label: '已终止', color: 'negative' },
  6: { label: '作废', color: 'grey-10' }
}

const statusOptions = Object.keys(statusMap).map(key => ({
  label: statusMap[key].label,
  value: parseInt(key)
}))

// 表格列定义
const columns = [
  { name: 'contract_no', label: '合同号', field: 'contract_no', align: 'left', sortable: true },
  { name: 'title', label: '合同名称', field: 'title', align: 'left' },
  { name: 'signed_datae', label: '签订时间', field: 'signed_date', align: 'left' },
  { name: 'organization_a', label: '甲方', field: row => row.organization_a.name, align: 'right' },
  { name: 'organization_b', label: '乙方', field: row=> row.organization_b.name, align: 'right' },
  { name: 'total_limit', label: '合同总额', field: 'total_limit', align: 'right' },
  { name: 'status', label: '状态', field: 'status', align: 'center' },
  { name: 'created_at', label: '创建日期', field: 'created_at', align: 'center', format: val => val?.substring(0, 10) },
  { name: 'actions', label: '操作', align: 'left' }
]

// --- 逻辑处理 ---

// 获取数据
const fetchList = async () => {
  loading.value = true
  try {
    const params = {
      ...filter,
      page: pagination.value.page,
      per_page: pagination.value.rowsPerPage
    }
    const { data } = await masterContractApi.list(params)
    rows.value = data
    // 如果后端返回了分页信息，在此更新 pagination.value
  } catch (error) {
    console.error('获取列表失败', error)
  } finally {
    loading.value = false
  }
}

// 金额格式化
const formatMoney = (val) => {
  return new Intl.NumberFormat('zh-CN', {
    style: 'currency',
    currency: 'CNY',
    minimumFractionDigits: 2
  }).format(val || 0)
}

// 页面跳转
const handleCreate = () => router.push('/contract-form');
const handleEdit = (id) => router.push(`/contract-form/${id}`);
const goDetail = (id) => router.push(`/contract-detail/${id}`);
const toAudit = (id) =>router.push(`/contract-audit/${id}`);
const splitContract = (id) =>router.push(`/contract-split/${id}`);

// 逻辑删除
const confirmDelete = (id,contract_no) => {
  $q.dialog({
    title: '确认删除',
    message: `您确定要删除合同 [${contract_no}] 吗？此操作将记录审计轨迹且不可逆。`,
    cancel: { flat: true, color: 'grey' },
    ok: { flat: true, color: 'negative', label: '确认删除' },
    persistent: true
  }).onOk(async () => {
    try {
      await masterContractApi.toVoid(id)
      // $q.notify({ type: 'positive', message: '合同已逻辑删除' })
      fetchList()
    } catch (e) {
      console.log("Err",e);
    }
  })
}

const toSubmit =(id,system_no)=>{//提交审核
  let title ='确认提交审核';
  let message =`您确定要把合同 [${system_no}] 提交审核吗？`;
  $q.dialog({
    title: title,
    message: message,
    cancel: { flat: true, color: 'grey' },
    ok: { flat: true, color: 'negative', label: '确认提交' },
    persistent: true
  }).onOk(async () => {
    try {
      await masterContractApi.toSubmit(id)
      // $q.notify({ type: 'positive', message: '合同已提交审核' })
      fetchList()
    } catch (e) {
      console.log("Err",e);
    }
  })
}

const toWithdraw =(id,system_no)=>{//撤回审核
  let title ='确认撤回审核';
  let message =`您确定要把合同 [${system_no}] 撤回审核吗？`;

  $q.dialog({
    title: title,
    message: message,
    cancel: { flat: true, color: 'grey' },
    // ok: { flat: true, color: 'negative', label: '确认撤回' },
    persistent: true
  }).onOk(async () => {
    try {
      await masterContractApi.toWithdraw(id)
      // $q.notify({ type: 'positive', message: '合同已撤回审核' })
      fetchList()
    } catch (e) {
      console.log("Err",e);
    }
  })
}

onMounted(fetchList)
</script>

<style scoped>
/* 增加表格行的悬停感 */
.q-tr:hover {
  background-color: #f5f5f5 !important;
}
</style>
