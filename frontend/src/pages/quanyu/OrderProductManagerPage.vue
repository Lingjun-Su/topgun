<template>
  <div class="q-pa-md">
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row items-center q-gutter-sm">
        <q-input v-model="filter" dense outlined placeholder="搜索名称..." class="col-grow">
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>
        <q-btn color="primary" icon="add" label="新增产品" @click="openDialog()" />
        <q-btn color="grey-7" icon="refresh" @click="fetchData" />
      </q-card-section>
    </q-card>

    <q-table
      :rows="rows"
      :columns="columns"
      row-key="id"
      :loading="loading"
      :filter="filter"
      flat
      bordered
    >
      <template v-slot:body-cell-status="props">
        <q-td :props="props">
          <q-chip
            :color="props.value === 1 ? 'positive' : 'negative'"
            text-color="white"
            size="sm"
          >
            {{ props.value === 1 ? '启用' : '禁用' }}
          </q-chip>
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props" class="q-gutter-xs">
          <q-btn flat round color="blue" icon="edit" size="sm" @click="openDialog(props.row)">
            <q-tooltip>修改</q-tooltip>
          </q-btn>
          <q-btn flat round color="red" icon="delete" size="sm" @click="confirmDelete(props.row)">
            <q-tooltip>逻辑删除</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="dialog.show" persistent>
      <q-card style="min-width: 400px">
        <q-card-section class="row items-center">
          <div class="text-h6">{{ dialog.isEdit ? '编辑产品' : '新增产品' }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-form @submit="saveData">
          <q-card-section class="q-gutter-md">
            <q-input
              v-model="form.name"
              label="产品名称 *"
              :rules="[val => !!val || '名称不能为空', val => val.length <= 50 || '最多50字符']"
              outlined
              dense
            />
            <q-input
              v-model="form.code"
              label="编号 *"
              :rules="[val => !!val || '编号不能为空', val => val.length <= 20 || '最多20字符']"
              outlined
              dense
            />
            <q-input
              v-model="form.remark"
              label="备注"
              type="textarea"
              :rules="[val => val.length <= 100 || '最多100字符']"
              outlined
              dense
              rows="2"
            />
             <pid-select
              v-model="form.pid"
            />

            <q-select
              v-model="form.status"
              :options="statusOptions"
              label="状态"
              map-options
              emit-value
              outlined
              dense
            />
          </q-card-section>

          <q-card-actions align="right" class="text-primary">
            <q-btn flat label="取消" v-close-popup />
            <q-btn color="primary" label="保存提交" type="submit" :loading="submitting" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import { api } from 'boot/axios' // 使用预设的 axios 实例
import { useQuasar } from 'quasar'

import PidSelect from 'components/PidSelect.vue';

const $q = useQuasar()

// --- 状态定义 ---
const rows = ref([])
const loading = ref(false)
const submitting = ref(false)
const filter = ref('')

const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
  { name: 'name', label: '名称', field: 'name', align: 'left', sortable: true },
  { name: 'code', label: '编号', field: 'code', align: 'left', sortable: true },
  { name: 'pid', label: 'PID', field: 'pid', align: 'center' },
  { name: 'status', label: '状态', field: 'status', align: 'center' },
  { name: 'remark', label: '备注', field: 'remark', align: 'left' },
  { name: 'updated_at', label: '最后更新', field: 'updated_at', align: 'left' },
  { name: 'actions', label: '操作', align: 'center' }
]

const statusOptions = [
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 }
]

// 表单响应式对象
const dialog = reactive({
  show: false,
  isEdit: false
})

const form = reactive({
  id: null,
  name: '',
  remark: '',
  pid: null,
  status: 1
})

// --- 方法逻辑 ---

// 1. 查询数据
const fetchData = async () => {
  loading.value = true
  try {
    const response = await api.get('/api_v2/ThirdChannel/order_product')
    console.log(response);
    rows.value = response.data.data.data
  } catch (error) {
    console.log("加载失败",error)
    $q.notify({ color: 'negative', message: '数据加载失败' })
  } finally {
    loading.value = false
  }
}

// 2. 准备弹窗
const openDialog = (row = null) => {
  if (row) {
    // 编辑模式：浅拷贝数据到表单
    Object.assign(form, row)
    dialog.isEdit = true
  } else {
    // 新增模式：重置表单
    resetForm()
    dialog.isEdit = false
  }
  dialog.show = true
}

const resetForm = () => {
  form.id = null
  form.name = ''
  form.remark = ''
  form.pid = null
  form.status = 1
}

// 3. 新增/修改提交
const saveData = async () => {
  submitting.value = true
  try {
    if (dialog.isEdit) {
      // 修改：PUT 请求，触发后端审计记录
      await api.put(`/api_v2/ThirdChannel/order_product/${form.id}`, form)
      $q.notify({ color: 'positive', message: '更新成功' })
    } else {
      // 新增：POST 请求
      await api.post('/api_v2/ThirdChannel/order_product', form)
      $q.notify({ color: 'positive', message: '创建成功' })
    }
    dialog.show = false
    fetchData()
  } catch (error) {
    $q.notify({ color: 'negative', message: '提交失败：' + (error.response?.data?.message || '未知错误') })
  } finally {
    submitting.value = false
  }
}

// 4. 逻辑删除确认
const confirmDelete = (row) => {
  $q.dialog({
    title: '确认删除',
    message: `确定要删除产品 "${row.name}" 吗？此操作将进行逻辑删除并记录审计日志。`,
    cancel: true,
    persistent: true
  }).onOk(async () => {
    try {
      await api.delete(`/api_v2/ThirdChannel/order_product/${row.id}`)
      $q.notify({ color: 'orange-8', message: '已逻辑删除', icon: 'delete' })
      fetchData()
    } catch (error) {
      console.log("删除失败",error)
      $q.notify({ color: 'negative', message: '删除失败' })
    }
  })
}

onMounted(fetchData)
</script>
