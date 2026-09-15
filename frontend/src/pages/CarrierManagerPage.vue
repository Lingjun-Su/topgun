<template>
  <q-page class="q-pa-md">
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row items-center q-gutter-sm">
        <q-input
          v-model="filter.keyword"
          outlined
          dense
          placeholder="搜索名称或编号..."
          style="width: 300px"
          @keyup.enter="refreshTable"
        >
          <template v-slot:append>
            <q-icon name="search" class="cursor-pointer" @click="refreshTable" />
          </template>
        </q-input>

        <q-space />

        <q-btn color="primary" icon="add" label="新增运营商" @click="openDialog()" />
      </q-card-section>
    </q-card>

    <q-table
      flat
      bordered
      :rows="rows"
      :columns="columns"
      row-key="id"
      v-model:pagination="pagination"
      :loading="loading"
      @request="onRequest"
      binary-state-sort
    >
      <template v-slot:body-cell-actions="props">
        <q-td :props="props" class="q-gutter-xs">
          <q-btn
            flat
            round
            dense
            color="blue"
            icon="edit"
            @click="openDialog(props.row)"
          >
            <q-tooltip>修改</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 500px">
        <q-card-section class="row items-center">
          <div class="text-h6">
            {{ form.id ? '编辑运营商' : '新增运营商' }}
            <q-toggle
              :true-value="1"
              :false-value="0"
              :label="form.status === 1 ? '使用中' : '禁用中'"
              color="green"
              v-model="form.status"
            />

          </div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-separator />

        <q-form @submit="saveData">
          <q-card-section class="q-gutter-md">
            <div class="row q-col-gutter-sm">
              <q-input
                v-model="form.name"
                label="全称 *"
                class="col-8"
                outlined
                dense
                :rules="[val => !!val || '必填项目']"
              />
              <q-input
                v-model="form.short_name"
                label="简称"
                class="col-4"
                outlined
                dense
              />
            </div>

            <q-input
              v-model="form.code"
              label="人工唯一编号 *"
              outlined
              dense
              hint="保存后通常建议不可更改"
              :rules="[val => !!val || '必填项目']"
            />

            <div class="row q-col-gutter-sm">
              <q-input
                v-model="form.contact_person"
                label="联系人"
                class="col-6"
                outlined
                dense
              />
              <q-input
                v-model="form.contact_phone"
                label="联系电话"
                class="col-6"
                outlined
                dense
              />
            </div>

            <q-input
              v-model="form.address"
              label="办公地址"
              type="textarea"
              outlined
              dense
              rows="2"
            />

            <q-input
              v-model="form.contents"
              label="备注描述"
              type="textarea"
              outlined
              dense
              rows="3"
            />
          </q-card-section>

          <q-card-actions align="right" class="bg-grey-1 text-primary">
            <q-btn flat label="取消" v-close-popup />
            <q-btn type="submit" label="保存提交" color="primary" :loading="submitting" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import { api } from 'boot/axios' // 使用项目预设的 axios 实例
import { useQuasar } from 'quasar'

const $q = useQuasar()

// --- 表格状态 ---
const rows = ref([])
const loading = ref(false)
const filter = reactive({ keyword: '' })
const pagination = ref({
  sortBy: 'id',
  descending: true,
  page: 1,
  rowsPerPage: 15,
  rowsNumber: 0,
  status:false,
})

const columns = [
  { name: 'code', label: '编号', field: 'code', align: 'left', sortable: true },
  { name: 'status', label: '状态', field: row => row.status === 1 ? '使用中' : '禁用中', align: 'left', sortable: true },
  { name: 'name', label: '运营商名称', field: 'name', align: 'left', sortable: true },
  { name: 'contact_person', label: '联系人', field: 'contact_person', align: 'center' },
  { name: 'contact_phone', label: '电话', field: 'contact_phone', align: 'center' },
  { name: 'actions', label: '操作', align: 'center' }
]

// --- 表单状态 ---
const showDialog = ref(false)
const submitting = ref(false)
const initialForm = {
  id: null,
  name: '',
  short_name: '',
  code: '',
  contact_person: '',
  contact_phone: '',
  address: '',
  contents: '',
  status:1,
}
const form = reactive({ ...initialForm })

// --- 业务逻辑 ---

// 1. 获取列表数据 (CRUD - Read)
async function onRequest (props) {
  const { page, rowsPerPage, sortBy, descending } = props.pagination
  loading.value = true

  try {
    const response = await api.get('/v1/carriers', {
      params: {
        page: page,
        per_page: rowsPerPage,
        keyword: filter.keyword,
        sort_by: sortBy,
        desc: descending ? 'desc' : 'asc'
      }
    })

    // 适配 Laravel Paginate 返回结构

    rows.value = response.data.data || []
    pagination.value.rowsNumber = response.data.total || 0
    pagination.value.page = response.data.current_page
    pagination.value.rowsPerPage = response.data.per_page
    pagination.value.sortBy = sortBy
    pagination.value.descending = descending
  } catch (error) {
    console.log("加载失败",error);
    $q.notify({ type: 'negative', message: '数据加载失败' })
  } finally {
    loading.value = false
  }
}

// 刷新表格
function refreshTable () {
  onRequest({ pagination: pagination.value })
}

// 2. 打开弹窗 (新增/修改预处理)
function openDialog (row = null) {
  if (row) {
    // 编辑：深拷贝赋值
    Object.assign(form, row)
  } else {
    // 新增：重置表单
    Object.assign(form, initialForm)
    form.id = null
  }
  showDialog.value = true
}

// 3. 保存数据 (CRUD - Create & Update)
async function saveData () {
  submitting.value = true
  try {
    if (form.id) {
      // 修改
      await api.put(`/v1/carriers/${form.id}`, form)
    } else {
      // 新增
      await api.post('/v1/carriers', form)
    }
    showDialog.value = false
    refreshTable()
  } catch (error) {
    const msg = error.response?.data?.message || '操作失败'
    $q.notify({ type: 'negative', message: msg })
  } finally {
    submitting.value = false
  }
}


onMounted(() => {
  refreshTable()
})
</script>
