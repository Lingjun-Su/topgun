<template>
  <q-page padding>
    <q-table
      title="用户管理"
      :rows="rows"
      :columns="columns"
      row-key="id"
      v-model:pagination="pagination"
      @request="onRequest"
    >
      <template v-slot:top-right>
        <q-btn color="primary" label="新增用户" @click="openDialog()" />
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat round color="blue" icon="edit" @click="openDialog(props.row)" />

          <q-btn
            flat round color="red"
            icon="delete"
            :disable="props.row.name === 'admin'"
            @click="confirmDelete(props.row.id)"
          />
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="showDialog" maximized>
      <q-card style="min-width: 500px">
        <q-card-section>
          <div class="text-h6">{{ isEdit ? '编辑用户' : '新增用户' }}</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-input
            dense
            v-model="form.name"
            label="用户姓名"
            :disable="isEdit && (form.name === 'admin' || form.name==='Admin')"
          />
          <q-input
            dense
            v-model="form.phone"
            label="手机号"
          />
          <q-input
            dense
            v-model="form.password"
            label="密码"
            type="password"
            :placeholder="isEdit ? '留空表示不修改' : ''"
          />

          <q-separator class="q-my-md" />

          <div class="text-subtitle2 q-mb-sm">数据权限设置</div>

          <q-toggle
            v-model="enableRestriction"
            label="限制页面和数据访问"
            color="primary"
          />

          <template v-if="enableRestriction">
            <div class="text-caption text-grey q-mb-sm">允许访问的页面：</div>
            <q-checkbox v-model="form.data_permissions.page_restrictions" val="product-order" label="产品订单" />

            <div class="text-caption text-grey q-mt-sm q-mb-sm">允许查看的渠道：</div>
            <q-select
              v-model="form.data_permissions.channel_ids"
              :options="channelOptions"
              option-value="pid"
              option-label="name"
              label="选择渠道"
              outlined
              dense
              multiple
              use-chips
              stack-label
              emit-value
              map-options
              :loading="channelLoading"
              @filter="filterChannels"
              use-input
              input-debounce="300"
            >
              <template v-slot:no-option>
                <q-item>
                  <q-item-section class="text-grey">无匹配渠道</q-item-section>
                </q-item>
              </template>
            </q-select>
          </template>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="取消" v-close-popup />
          <q-btn color="primary" label="提交" @click="saveUser" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { userApi } from 'src/api/user'
import { channelApi } from 'src/api/modules'

const $q = useQuasar()
const rows = ref([])
const showDialog = ref(false)
const isEdit = ref(false)
const form = ref({ id: null, name: '', phone: '', password: '', data_permissions: null })
const enableRestriction = ref(false)
const channelOptions = ref([])
const channelLoading = ref(false)
const pagination = ref({ page: 1, rowsPerPage: 10, rowsNumber: 0 })

const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left' },
  { name: 'name', label: '用户姓名', field: 'name', align: 'left' },
  { name: 'phone', label: '电话', field: 'phone', align: 'left' },
  { name: 'actions', label: '操作', align: 'center' }
]

// 获取渠道列表
const fetchChannels = async () => {
  channelLoading.value = true
  try {
    const res = await channelApi.list({ per_page: 999 })
    channelOptions.value = res.data.data || []
  } catch {
    // 静默失败
  } finally {
    channelLoading.value = false
  }
}

// 渠道搜索过滤
const filterChannels = (val, update) => {
  update(() => {
    // 前端过滤，不需要额外请求
  })
}

// 获取数据
const onRequest = async (props) => {
  const { page, rowsPerPage } = props.pagination
  const res = await userApi.list({ page, per_page: rowsPerPage })
  rows.value = res.data.data || [];
  pagination.value.rowsNumber = res.data.total || 0
  pagination.value.page = page
  pagination.value.rowsPerPage = rowsPerPage
}

// 打开弹窗
const openDialog = (row = null) => {
  fetchChannels()
  if (row) {
    isEdit.value = true
    const dp = row.data_permissions
      ? (typeof row.data_permissions === 'string' ? JSON.parse(row.data_permissions) : row.data_permissions)
      : null
    form.value = {
      id: row.id,
      name: row.name,
      phone: row.phone,
      password: '',
      data_permissions: dp || { page_restrictions: [], channel_ids: [] }
    }
    enableRestriction.value = !!dp
  } else {
    isEdit.value = false
    form.value = {
      id: null,
      name: '',
      phone: '',
      password: '',
      data_permissions: { page_restrictions: [], channel_ids: [] }
    }
    enableRestriction.value = false
  }
  showDialog.value = true
}

// 保存/更新
const saveUser = async () => {
  try {
    const submitData = {
      ...form.value,
      data_permissions: enableRestriction.value ? form.value.data_permissions : null
    }
    if (isEdit.value) {
      await userApi.update(form.value.id, submitData)
    } else {
      await userApi.store(submitData)
    }
    showDialog.value = false
    onRequest({ pagination: pagination.value })
  } catch (e) {
    console.log('操作失败', e)
  }
}

// 删除确认
const confirmDelete = (id) => {
  $q.dialog({
    title: '确认',
    message: '确定要删除该用户吗？（此操作为逻辑删除）',
    cancel: true
  }).onOk(async () => {
    await userApi.remove(id)
    onRequest({ pagination: pagination.value })
  })
}

onMounted(() => onRequest({ pagination: pagination.value }))
</script>
