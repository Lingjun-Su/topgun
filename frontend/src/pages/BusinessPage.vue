<template>
  <q-page padding class="q-pa-lg">
    <q-table
      title="业务管理"
      :rows="rows"
      :columns="columns"
      row-key="id"
      :loading="loading"
      :pagination="pagination"
      flat
      bordered
      binary-state-sort
    >
      <template v-slot:top-right>
        <q-input
          v-model="filterText"
          dense
          outlined
          debounce="300"
          placeholder="搜索名称/编号"
          class="q-mr-md"
        >
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>

        <q-btn
          color="primary"
          icon="add"
          label="新增业务"
          @click="openDialog()"
          unelevated
        />
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props" class="q-gutter-x-sm">
          <q-btn
            flat
            round
            dense
            color="primary"
            icon="edit"
            @click="openDialog(props.row)"
          >
            <q-tooltip>编辑业务信息</q-tooltip>
          </q-btn>
        </q-td>
      </template>

      <template v-slot:body-cell-updated_at="props">
        <q-td :props="props">
          <div class="text-caption text-grey-8">
            {{ props.value }}
          </div>
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="dialog.show" persistent backdrop-filter="blur(4px)">
      <q-card style="width: 700px; max-width: 90vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6 text-weight-bold">
            {{ dialog.isEdit ? '编辑业务' : '登记新业务' }}
            <q-toggle
              :true-value="1"
              :false-value="0"
              :label="formData.status === 1 ? '使用中' : '禁用中'"
              color="green"
              v-model="formData.status"
            />
          </div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-separator q-my-md />

        <q-card-section>
          <q-form @submit="handleSave" ref="businessForm" class="row q-col-gutter-md">
            <div class="col-12 col-md-6">
              <OrganizationTreeSelect
                v-model="formData.org_id"
                @select="onOrgSelect"

              />
            </div>
            <div class="col-12 col-md-6">
              <CarrierSelect
                v-model="formData.carrier_id"
                @select="onCarrierSelect"

              />
            </div>

            <div class="col-12 col-md-3">
              <q-input
                v-model="formData.code"
                label="业务编号 *"
                :disable="dialog.isEdit"
                :rules="[val => !!val || '编号不能为空']"
                outlined
                dense
              />
            </div>

            <div class="col-12 col-md-5">
              <q-input
                v-model="formData.name"
                label="业务全称 *"
                :rules="[val => !!val || '全称不能为空']"
                outlined
                dense
              />
            </div>

            <div class="col-12 col-md-4">
              <q-input
                v-model="formData.short_name"
                label="简称 *"
                counter
                maxlength="20"
                :rules="[val => !!val || '简称不能为空']"
                outlined
                dense
              />
            </div>

            <div class="col-12 col-md-6">
              <q-input
                v-model="formData.contact_person"
                label="联系人"
                outlined
                dense
              />
            </div>

            <div class="col-12 col-md-6">
              <q-input
                v-model="formData.contact_phone"
                label="联系电话"
                outlined
                dense
              />
            </div>

            <div class="col-12">
              <q-input
                v-model="formData.address"
                type="textarea"
                label="办公地址"
                rows="2"
                outlined
                dense
              />
            </div>

            <div class="col-12">
              <q-input
                v-model="formData.contents"
                type="textarea"
                label="业务描述/备注"
                rows="3"
                outlined
                dense
              />
            </div>

            <div class="col-12 text-right q-gutter-sm q-mt-md">
              <q-btn label="取消" flat v-close-popup />
              <q-btn
                :label="dialog.isEdit ? '确认更新' : '提交保存'"
                color="primary"
                type="submit"
                :loading="submitting"
                unelevated
              />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { businessApi } from 'src/api/modules' // 引入您定义的接口文件

import OrganizationTreeSelect from 'components/OrganizationTreeSelect.vue';//组织架构
import CarrierSelect from 'components/CarriersSelect.vue';//运营商
const $q = useQuasar()

// --- 状态数据 ---
const rows = ref([])
const loading = ref(false)
const submitting = ref(false)
const filterText = ref('')
const businessForm = ref(null)

// 分页配置
const pagination = ref({
  sortBy: 'id',
  descending: true,
  page: 1,
  rowsPerPage: 10
})

// 表头定义（对应 Laravel Migration 字段）
const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
  { name: 'status', label: '状态', field: row => row.status === 1 ? '使用中' : '禁用中', align: 'left', sortable: true },
  { name: 'company', label: '所属公司', field: row => row.organization?.short_name || '未指定', align: 'left' },
  { name: 'carrier', label: '运营商', field: row => row.carrier?.short_name || '未指定', align: 'left' },
  { name: 'code', label: '编号', field: 'code', align: 'left' },
  { name: 'name', label: '业务名称', field: 'name', align: 'left', sortable: true },
  { name: 'short_name', label: '简称', field: 'short_name', align: 'left' },
  { name: 'contact_person', label: '联系人', field: 'contact_person', align: 'left' },
  { name: 'updated_at', label: '最后变动时间', field: 'updated_at', align: 'center' },
  { name: 'actions', label: '操作', align: 'center' }
]

// 弹窗与表单状态
const dialog = reactive({
  show: false,
  isEdit: false
})

const initialForm = {
  id: null,
  org_id: null,
  code: '',
  name: '',
  short_name: '',
  contents: '',
  contact_person: '',
  contact_phone: '',
  address: '',
  status:1,
}

const formData = reactive({ ...initialForm })

// --- 核心方法 ---

/**
 * 获取列表数据
 * 调用 businessApi.list
 */
const loadData = async () => {
  loading.value = true
  try {
    console.log("load")
    const res = await businessApi.list({
      search: filterText.value // 假设后端支持搜索参数
    })
    console.log('res',res);
    // 根据 Laravel 分页结构，通常数据在 res.data.data
    rows.value = res.data.data || res.data
  } catch (error) {
    console.log('load false',error);
    $q.notify({ color: 'negative', message: '获取数据失败，请检查网络或权限', icon: 'error' })
  } finally {
    loading.value = false
  }
}

/**
 * 打开弹窗
 * @param {Object} row - 行数据，若为空则为新增
 */
const openDialog = (row = null) => {
  if (row) {
    dialog.isEdit = true
    Object.assign(formData, row)
  } else {
    dialog.isEdit = false
    Object.assign(formData, initialForm)
  }
  dialog.show = true
}

const onOrgSelect = (node) => {
  console.log('选中的完整节点信息:', node)
  formData.org_id = node.id // 这里可以拿到 node.code, node.type 等
}
const onCarrierSelect = (node) => {
  console.log('选中的完整节点信息:', node)
  formData.carrier_id = node.id // 这里可以拿到 node.code, node.type 等
}

/**
 * 保存/更新处理
 * 调用 businessApi.store 或 businessApi.update
 */
const handleSave = async () => {
  submitting.value = true
  try {
    if (dialog.isEdit) {
      // 执行更新：后端会记录 updated_by 和 audits 审计
      await businessApi.update(formData.id, formData)
    } else {
      // 执行新增：后端会记录 created_by
      await businessApi.store(formData)
    }
    dialog.show = false
    loadData()
  } catch (error) {
    // 处理后端抛出的严谨校验错误（如 org_id+code 冲突）
    console.log(error);
  } finally {
    submitting.value = false
  }
}


// 初始化加载
onMounted(() => {
  loadData()
})
</script>

<style lang="scss" scoped>
// 针对 SQL Server 迁移文件中的 text 类型字段在表格中的展示优化
.text-caption {
  font-size: 0.75rem;
  line-height: 1.25rem;
}
</style>
