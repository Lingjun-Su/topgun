<template>
  <q-page class="q-pa-md">
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row items-center q-gutter-sm">
        <q-input v-model="filter.name" label="渠道名称" dense outlined clearable style="width: 200px" />
        <q-select v-model="filter.status" :options="statusOptions" label="状态" dense outlined emit-value map-options style="width: 150px" />
        <q-button color="primary" icon="search" label="搜索" @click="getList" />
        <q-space />
        <q-btn color="primary" icon="add" label="新增渠道" @click="openDialog()" />
      </q-card-section>
    </q-card>

    <q-table
      :rows="rows"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      :pagination="pagination"
    >
      <template v-slot:body-cell-is_ip_restricted="props">
        <q-td :props="props">
          <q-chip :color="props.value === true ? 'positive' : 'negative'" text-color="white" dense>
            {{ props.value === true ? '启用' : '禁用' }}
          </q-chip>
        </q-td>
      </template>
      <template v-slot:body-cell-status="props">
        <q-td :props="props">
          <q-chip :color="props.value === 1 ? 'positive' : props.value === 0?'negative':'warning'" text-color="white" dense>
            {{ props.value === 1 ? '启用' : props.value === 0?'禁用':'测试' }}
          </q-chip>
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props" class="q-gutter-xs">
          <q-btn flat round color="blue" icon="settings" @click="manageProducts(props.row)">
            <q-tooltip>产品配置</q-tooltip>
          </q-btn>
          <q-btn flat round color="primary" icon="edit" @click="openDialog(props.row)">
            <q-tooltip>编辑</q-tooltip>
          </q-btn>
          <q-btn flat round color="primary" icon="content_copy" @click="copyContent(props.row)">
            <q-tooltip>复制渠道和产品信息</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="dialog.show" persistent>
      <q-card style="min-width: 500px">
        <q-card-section class="row items-center">
          <div class="text-h6">{{ dialog.isEdit ? '编辑渠道' : '新增渠道' }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveChannel" class="q-gutter-md">
            <q-input v-model="form.name" label="渠道名称 *" dense outlined :rules="[val => !!val || '必填']" />
            <q-input v-model="form.pid" label="PID *" dense outlined :disable="dialog.isEdit" :rules="[val => !!val || '必填']" />
            <q-input v-model="form.key" label="签名Key *" dense outlined :rules="[val => !!val || '必填']" />

            <q-toggle v-model="form.is_ip_restricted" label="强制使用IP白名单" />
            <template v-if="form.is_ip_restricted==true">
              <q-input v-model="form.ip_whitelist" type="textarea" label="IP白名单" dense outlined rows="2" />
            </template>

            <organization-tree-select v-model="form.organization_id" label="归属公司" />

            <q-select v-model="form.method" :options="['接收', '发送']" label="方法 *" dense outlined :rules="[val => !!val || '必填']" />
            <q-input v-model="form.remark" type="textarea" label="备注" dense outlined rows="2" />



            <q-radio v-model="form.status" :val="0" label="禁用" />
            <q-radio v-model="form.status" :val="2" label="测试" />
            <q-radio v-model="form.status" :val="1" label="启用" />
            <div class="row justify-end q-mt-md">
              <q-btn label="取消" flat v-close-popup />
              <q-btn label="提交" type="submit" color="primary" :loading="submitting" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <q-dialog v-model="productDialog.show" persistent>
  <q-card style="min-width: 800px; max-width: 90vw;">
    <q-card-section class="bg-primary text-white row items-center">
      <div class="text-h6">配置渠道产品: {{ productDialog.channelName }}</div>
      <q-space />
      <q-btn icon="close" flat round dense v-close-popup />
    </q-card-section>

    <q-card-section class="row q-col-gutter-md items-end bg-grey-1">
      <div class="col-4">
        <q-select
          v-model="selector.businessId"
          :options="businessOptions"
          label="1. 选择业务"
          option-label="name"
          option-value="id"
          emit-value
          map-options
          dense
          outlined
          @update:model-value="loadProductsByBusiness"
        />
      </div>
      <div class="col-4">
        <q-select
          v-model="selector.productId"
          :options="productOptions"
          label="2. 选择产品"
          option-label="name"
          option-value="id"
          emit-value
          map-options
          dense
          outlined
          :disable="!selector.businessId"
        />
      </div>
      <div class="col-4">
        <q-btn
          color="secondary"
          icon="add"
          label="添加至配置列表"
          class="full-width"
          :disable="!selector.productId"
          @click="addProductToConfig"
        />
      </div>
    </q-card-section>

    <q-separator />

    <q-card-section style="max-height: 400px" class="scroll q-pa-none">
      <q-table
        :rows="productDialog.items"
        :columns="configColumns"
        row-key="product_id"
        flat
        square
        dense
        :pagination="{ rowsPerPage: 0 }"
      >
        <template v-slot:body-cell-product_name="props">
          <q-td :props="props">
            {{props.row.product_name}}
          </q-td>
        </template>

        <template v-slot:body-cell-status="props">
          <q-td :props="props">
            <q-toggle v-model="props.row.status" :true-value="1" :false-value="0" dense />
          </q-td>
        </template>

        <template v-slot:body-cell-remark="props">
          <q-td :props="props">
            <q-input v-model="props.row.remark" dense borderless placeholder="点击输入备注" />
          </q-td>
        </template>

      </q-table>

      <div v-if="productDialog.items.length === 0" class="text-center q-pa-xl text-grey-6">
        暂未配置产品，请从上方选择添加
      </div>
    </q-card-section>

    <q-card-actions align="right" class="q-pa-md">
      <q-btn flat label="取消" v-close-popup />
      <q-btn color="primary" label="确认并保存配置" icon="save" @click="saveProductConfig" />
    </q-card-actions>
  </q-card>
</q-dialog>

  </q-page>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useQuasar,copyToClipboard } from 'quasar'
import { channelApi } from 'src/api/modules'
import { productApi } from 'src/api/modules'
import OrganizationTreeSelect from 'components/OrganizationTreeSelect.vue'

const $q = useQuasar()

// --- 状态定义 ---
const loading = ref(false)
const submitting = ref(false)
const rows = ref([])
const filter = reactive({ name: '', status: null })
const statusOptions = [
  { label: '全部', value: null },
  { label: '禁用', value: 0 },
  { label: '测试', value: 2 },
  { label: '启用', value: 1 },
]

// 表格列定义
const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left' },
  { name: 'name', label: '渠道名称', field: 'name', align: 'left' },
  { name: 'pid', label: 'PID', field: 'pid', align: 'left' },
  { name: 'method', label: '方法', field: 'method', align: 'center' },
  { name: 'is_ip_restricted', label: '强制IP白名单', field: 'is_ip_restricted', align: 'center' },
  { name: 'status', label: '状态', field: 'status', align: 'center' },
  { name: 'remark', label: '备注', field: 'remark', align: 'left' },
  { name: 'actions', label: '操作', field: 'actions', align: 'center' }
]

const businessOptions = ref([]) // 业务下拉
const productOptions = ref([])  // 产品下拉（受业务过滤）
const allProductNames = ref({}) // 用于 ID 到 Name 的映射显示

const selector = reactive({
  businessId: null,
  productId: null
})

const configColumns = [
  { name: 'product_id', label: '产品ID', field: 'product_id', align: 'left' },
  { name: 'bus_code', label: '业务标识', field: row => row.bus_code, align: 'left' },
  { name: 'sku_code', label: '产品标识', field: row => row.sku_code, align: 'left' },
  { name: 'product_name', label: '产品名称', align: 'left' },
  { name: 'remark', label: '备注', field: 'remark', align: 'left' },
  { name: 'status', label: '状态', field: 'status', align: 'center' },
]
// 渠道表单
const dialog = reactive({ show: false, isEdit: false })
const form = reactive({
  id: null,
  name: '',
  pid: '',
  key: '',
  organization_id: null,
  method: '发送',
  status: 1,
  remark: '',
  ip_whitelist:'',//白名单
  is_ip_restricted:false,//是否强制使用白名单
})

// 产品配置表单
const productDialog = reactive({
  show: false,
  channelId: null,
  channelName: '',
  items: [] // 对应 channel_products 表
})

// --- 逻辑处理 ---

// 获取数据列表
const getList = async () => {
  loading.value = true
  try {
    const res = await channelApi.list(filter)
    rows.value = res.data // 假设 Laravel 返回的是标准的 LengthAwarePaginator
  } catch (error) {
    console.log("加载失败",error);
  } finally {
    loading.value = false
  }
}

// 打开新增/编辑弹窗
const openDialog = (row = null) => {
  dialog.isEdit = !!row
  if (row) {
    Object.assign(form, row)
  } else {
    // 重置表单
    Object.assign(form, { id: null, name: '', pid: '', key: '', organization_id: null, method: '发送', remark: '', status: 1 })
  }
  dialog.show = true
}

// 提交渠道表单
const saveChannel = async () => {
  submitting.value = true
  try {
    if (dialog.isEdit) {
      await channelApi.update(form.id, form)
    } else {
      await channelApi.store(form)
    }
    dialog.show = false
    getList()
  } catch (error) {
    console.error(error)
  } finally {
    submitting.value = false
  }
}


// --- 产品配置逻辑 ---

const manageProducts = async (row) => {
  productDialog.channelId = row.id
  productDialog.channelName = row.name
  // 获取当前渠道的详情，包含已配置的产品
  const res = await channelApi.show(row.id)
  productDialog.items = res.products || []
  productDialog.show = true
}


const saveProductConfig = async () => {
  try {
    await channelApi.syncProducts(productDialog.channelId, productDialog.items)
    productDialog.show = false
  } catch (error) {
    console.log("保存失败",error);
  }
}
// 初始化加载业务列表
onMounted(async () => {
  const res = await productApi.businessList();
  businessOptions.value = res.data;
  getList()
})
// 当业务选择变化时，加载对应产品
const loadProductsByBusiness = async (bId) => {
  selector.productId = null // 重置产品选择
  if (!bId) return
  const res = await productApi.listByBusiness(bId)
  productOptions.value = res.data;
  console.log("products",res);
  // 缓存产品名称以便显示
  res.data.forEach(p => {
    allProductNames.value[p.id] = p.name
  })
}
// 将选择的产品添加到下方列表
const addProductToConfig = () => {
  // 校验是否已存在
  const exists = productDialog.items.some(i => i.product_id == selector.productId)
  if (exists) {
    $q.notify({ type: 'warning', message: '该产品已在配置列表中',position:'center' })
    return
  }

  productDialog.items.push({
    product_id: selector.productId,
    product_name:getProductName(selector.productId),
    status: 1,
    remark: ''
  })

  // 保持产品 ID 与名称映射（防止表格无法显示名称）
  const selectedProduct = productOptions.value.find(p => p.id === selector.productId)
  if (selectedProduct) {
    allProductNames.value[selectedProduct.id] = selectedProduct.name
  }

  selector.productId = null // 清空选择，方便选下一个
}

const getProductName = (id) => {
  return allProductNames.value[id] || `产品(ID:${id})`
}

//复制
const copyContent =async (row)=>{
  const res = await channelApi.show(row.id);
  let products ='';
  for(let i =0;i<res.products.length;i++)
  {
    products +=`产品名称：${res.products[i].product_name} bus_code:${res.products[i].bus_code} sku_code:${res.products[i].sku_code}\t\n`
  }
  const v =`
  公司名称：${row.organization.name}\t
  PID:${res.pid}\t
  KEY:${res.key}\t
  ${products}
  `

  copyToClipboard(v)
  .then(() => {
    // 成功，可以提示用户，例如使用 Quasar 的 Notify 插件
    $q.notify({ type: 'positive', message: '复制成功！',position:'center' })
  })
  .catch(() => {
    // 失败，给出相应提示
    $q.notify({ type: 'negative', message: '复制失败，请手动复制' ,position:'center'})
  })
}

</script>
