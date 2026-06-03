<template>
  <q-page padding class="bg-grey-2">
    <!-- 搜索栏 -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-md-2">
          <BusinessSelect
            v-model="search.business_id"
            @update:model-value="onBusinessChange"
          />
        </div>

        <div class="col-12 col-md-3">
          <q-input
            v-model="search.keyword"
            label="产品名称 / SKU代码"
            dense
            outlined
            clearable
            @keyup.enter="loadData"
          />
        </div>

        <div class="col-12 col-md-2">
          <ProvinceSelect
            v-model="search.province_code"
            @update:model-value="onProvinceChange"
          />
        </div>

        <div class="col-auto">
          <q-btn color="primary" icon="search" label="查询" @click="searchAndResetPage" />
          <q-btn
            flat
            color="grey-7"
            label="重置"
            icon="restart_alt"
            class="q-ml-sm"
            @click="resetSearch"
          />
        </div>

        <q-space />

        <div class="col-auto">
          <q-btn color="positive" icon="add" label="新增产品" @click="openFormDialog()" />
        </div>
      </q-card-section>
    </q-card>

    <!-- 产品列表表格 -->
    <q-table
      :rows="rows"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      v-model:pagination="pagination"
      @request="onRequest"
      binary-state-sort
    >
      <template v-slot:body-cell-status="props">
        <q-td :props="props">
          <q-badge :color="props.value === 1 ? 'positive' : 'grey-7'">
            {{ props.value === 1 ? '启用' : '禁用' }}
          </q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-pay_model="props">
        <q-td :props="props">
          {{ payModelMap[props.value] }}
        </q-td>
      </template>

      <template v-slot:body-cell-provinces="props">
        <q-td :props="props">
          <div class="row q-gutter-xs">
            <q-chip
              v-for="p in props.row.product_province"
              :key="p.province_code"
              size="xs"
              outline
              color="blue-8"
            >
              {{ p.areas?.name || p.province_code }}
            </q-chip>
            <span
              v-if="!props.row.product_province?.length"
              class="text-grey-5 text-caption"
            >
              未设省份
            </span>
          </div>
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props" class="q-gutter-x-xs">
          <q-btn
            flat
            round
            dense
            color="orange"
            icon="map"
            @click="openProvinceDialog(props.row)"
          >
            <q-tooltip>配置省份</q-tooltip>
          </q-btn>
          <q-btn
            flat
            round
            dense
            color="blue"
            icon="edit"
            @click="openFormDialog(props.row)"
          >
            <q-tooltip>编辑详情</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- 产品编辑/新增对话框 -->
    <q-dialog v-model="showForm" persistent>
      <q-card style="width: 800px; max-width: 90vw">
        <q-card-section class="bg-blue-grey-8 text-white row items-center">
          <div class="text-h6">
            {{ currentProduct.id ? '编辑产品 - ' + currentProduct.sku_code : '新增产品登记' }}
          </div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section class="q-pa-md">
          <q-form ref="productFormRef" class="row q-col-gutter-md">
            <div class="col-12">
              <BusinessSelect
                v-model="currentProduct.business_id"
                @update:model-value="onBusinessChange"
              />
            </div>

            <div class="col-md-6 col-12">
              <q-input
                v-model="currentProduct.name"
                label="产品名称 *"
                outlined
                dense
                :rules="[val => !!val || '必填']"
              />
            </div>
            <div class="col-md-6 col-12">
              <q-input
                v-model="currentProduct.sku_code"
                label="SKU / 产品编码 *"
                outlined
                dense
                :rules="[val => !!val || '必填']"
              />
            </div>

            <div class="col-md-4 col-12">
              <q-input v-model="currentProduct.specification" label="规格型号" outlined dense />
            </div>
            <div class="col-md-4 col-12">
              <q-input
                v-model="currentProduct.unit"
                label="计量单位 *"
                outlined
                dense
                :rules="[val => !!val || '必填']"
              />
            </div>
            <div class="col-md-4 col-12">
              <q-input
                v-model.number="currentProduct.base_price"
                type="number"
                :label="payUnit"
                outlined
                dense
                step="0.01"
              />
            </div>

            <div class="col-md-6 col-12">
              <div class="text-caption text-grey-7 q-mb-xs">收费模式</div>
              <q-option-group
                v-model="currentProduct.pay_model"
                :options="payModelOptions"
                type="radio"
                inline
                dense
              />
            </div>
            <div class="col-md-6 col-12 items-center row">
              <q-toggle
                v-model="currentProduct.status"
                :true-value="1"
                :false-value="0"
                label="启用此产品"
                color="positive"
              />
            </div>

            <div class="col-12">
              <q-input
                v-model="currentProduct.contents"
                type="textarea"
                label="产品描述/备注"
                outlined
                dense
                rows="3"
              />
            </div>
          </q-form>
        </q-card-section>

        <q-separator />

        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="取消" v-close-popup />
          <q-btn
            unelevated
            label="提交保存"
            color="primary"
            @click="submitForm"
            :loading="submitting"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- 省份配置对话框 -->
    <q-dialog v-model="showProvinceDialog" persistent>
      <q-card style="min-width: 100px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">配置销售省份: {{ currentProduct.name }}</div>
        </q-card-section>
        <q-card-section class="q-py-lg">
          <area-multiple v-model="selectedProvinceCodes" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="取消" v-close-popup />
          <q-btn
            color="primary"
            label="更新省份配置"
            @click="saveProvinceConfig"
            :loading="submitting"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, reactive ,onMounted,watch} from 'vue'
import { useQuasar } from 'quasar'
import { productApi, productProvinceAPI } from 'src/api/modules'
import BusinessSelect from 'src/components/BusinessSelect.vue'
import ProvinceSelect from 'src/components/ProvinceSelect.vue'
import areaMultiple from 'src/components/areaMultiple.vue'

const $q = useQuasar()

// ==================== 常量定义 ====================
const payModelMap = { 0: '按次', 1: '按月', 2: '按年' ,3:'按百分比' }
let payUnit ='单价 (元)';
const payModelOptions = [
  { label: '按次', value: 0 },
  { label: '按月', value: 1 },
  { label: '按年', value: 2 },
  { label: '按百分比', value: 3 }
]

// 表格列定义（保持原有字段）
const columns = [
  { name: 'status', label: '状态', field: 'status', align: 'center', sortable: true },
  {
    name: 'bus_code',
    label: '业务代码',
    field: row => row.business?.code || '未关联',
    align: 'left',
    sortable: true
  },
  { name: 'sku_code', label: '产品代码', field: 'sku_code', align: 'left', sortable: true },
  { name: 'name', label: '产品名称', field: 'name', align: 'left', sortable: true },
  {
    name: 'business_name',
    label: '所属业务',
    field: row => row.business?.name || '未关联',
    align: 'left',
    sortable: true
  },
  {
    name: 'company_name',
    label: '所属公司',
    field: row => row.business?.organization?.short_name || '未关联',
    align: 'left',
    sortable: true
  },
  {
    name: 'carrier_name',
    label: '运营商',
    field: row => row.business?.carrier?.short_name || '未关联',
    align: 'left',
    sortable: true
  },
  { name: 'pay_model', label: '收费模式', field: 'pay_model', align: 'center', sortable: true },
  {
    name: 'base_price',
    label: '单价',
    field: 'base_price',
    align: 'right',
    format:(val, row) => {
      // 根据 pay_model 决定显示格式
      if (row.pay_model === 3) {       // 假设 percent 表示百分比模式
        return `${Number(val).toFixed(2)}%`;
      } else {                                 // 默认显示货币
        return `￥${Number(val).toFixed(2)}`;
      }
    },
    sortable: true
  },
  { name: 'unit', label: '单位', field: 'unit', align: 'center', sortable: true },
  { name: 'provinces', label: '销售省份', align: 'left' },
  { name: 'actions', label: '操作', align: 'center' }
]

// ==================== 响应式状态 ====================
const rows = ref([])
const loading = ref(false)
const submitting = ref(false)

// 搜索条件
const search = reactive({
  keyword: '',
  business_id: null,
  province_code: null
})

// 分页配置
const pagination = ref({
  sortBy: 'id',
  descending: true,
  page: 1,
  rowsPerPage: 10,
  rowsNumber: 0
})

// 弹窗状态
const showForm = ref(false)
const showProvinceDialog = ref(false)
const currentProduct = ref({
  status: 1,
  business_id: null,
  name: '',
  sku_code: '',
  specification: '',
  pay_model: 0,
  base_price: 0,
  unit: '条',
  contents: ''
})
const selectedProvinceCodes = ref([])

// 表单引用
const productFormRef = ref(null)

// ==================== 核心方法 ====================

/**
 * 加载产品列表（含分页、排序、筛选）
 */
const loadData = async () => {
  loading.value = true;
  try {
    const params = {
      page: pagination.value.page,
      per_page: pagination.value.rowsPerPage,
      sort_by: pagination.value.sortBy,
      sort_order: pagination.value.descending ? 'desc' : 'asc',
      keyword: search.keyword,
      business_id: search.business_id,
      province_code: search.province_code,
    };

    const response = await productApi.list(params);
    // ✅ 关键：分页数据在 response.data.data
    const paginatedData = response.data;
    // 提取列表
    rows.value = paginatedData || [];
    // 设置总记录数
    pagination.value.rowsNumber = response.total || 0;

    // 可选：同步每页条数
    if (response.per_page) {
      pagination.value.rowsPerPage = response.per_page;
    }
    console.log("pagination",pagination);
  } catch (error) {
    console.error('加载失败', error);
    $q.notify({ type: 'negative', message: '加载数据失败' });
    rows.value = [];
    pagination.value.rowsNumber = 0;
  } finally {
    loading.value = false;
  }
};

/**
 * 表格排序/分页变化时触发
 */
const onRequest = (props) => {
  const { page, rowsPerPage, sortBy, descending } = props.pagination
  pagination.value.page = page
  pagination.value.rowsPerPage = rowsPerPage
  pagination.value.sortBy = sortBy
  pagination.value.descending = descending
  loadData()
}

/**
 * 搜索时重置页码到第一页
 */
const searchAndResetPage = () => {
  pagination.value.page = 1
  loadData()
}

/**
 * 重置所有搜索条件
 */
const resetSearch = () => {
  Object.assign(search, {
    keyword: '',
    business_id: null,
    province_code: null
  })
  pagination.value.page = 1
  loadData()
}

/**
 * 业务选择变化时的处理（用于搜索）
 */
const onBusinessChange = (businessId) => {
  search.business_id = businessId
  searchAndResetPage()
}

/**
 * 省份选择变化时的处理（用于搜索）
 */
const onProvinceChange = (provinceCode) => {
  search.province_code = provinceCode
  searchAndResetPage()
}

// ==================== 产品增删改 ====================

/**
 * 打开产品表单（新增/编辑）
 */
const openFormDialog = (row = null) => {
  if (row) {
    // 深拷贝原始数据，避免修改原对象
    currentProduct.value = {
      id: row.id,
      status: row.status,
      business_id: row.business_id,
      name: row.name,
      sku_code: row.sku_code,
      specification: row.specification || '',
      pay_model: row.pay_model,
      base_price: row.base_price,
      unit: row.unit,
      contents: row.contents || ''
    }
  } else {
    // 重置为默认值
    currentProduct.value = {
      status: 1,
      business_id: null,
      name: '',
      sku_code: '',
      specification: '',
      pay_model: 0,
      base_price: 0,
      unit: '条',
      contents: ''
    }
  }
  showForm.value = true
}

/**
 * 提交产品表单（新增/编辑）
 */
const submitForm = async () => {
  // 表单校验
  const isValid = await productFormRef.value?.validate()
  if (!isValid) return

  submitting.value = true
  try {
    const submitData = { ...currentProduct.value }
    // 移除 id 字段，后端通过是否存在 id 判断更新或新增（这里统一处理）
    if (submitData.id) {
      await productApi.update(submitData.id, submitData)
    } else {
      await productApi.store(submitData)
    }
    showForm.value = false
    // 刷新列表（保持在当前页）
    loadData()
  } catch (error) {
    console.error('保存产品失败:', error)
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message || '保存失败，请检查输入'
    })
  } finally {
    submitting.value = false
  }
}

// ==================== 省份配置 ====================

/**
 * 打开省份配置对话框
 */
const openProvinceDialog = (row) => {
  currentProduct.value = { ...row } // 浅拷贝即可，用于展示产品名称
  // 提取已选省份代码（注意：后端返回的 product_province 每个对象包含 province_code 字段）
  selectedProvinceCodes.value = row.product_province?.map(p => p.province_code) || []
  showProvinceDialog.value = true
}

/**
 * 保存省份配置
 */
const saveProvinceConfig = async () => {
  submitting.value = true
  try {
    await productProvinceAPI.update(currentProduct.value.id, selectedProvinceCodes.value)
    showProvinceDialog.value = false
    // 刷新列表以更新省份显示
    loadData()
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message || '配置失败，请稍后重试'
    })
  } finally {
    submitting.value = false
  }
}
// 初始化
onMounted(() => {
  loadData();
})


// 方法1：使用 getter 函数，只监听 pay_model
watch(
  () => currentProduct.value.pay_model,
  (newVal) => {
    if(newVal==3){payUnit='百分比';}else{payUnit='单价 (元)'}
  }
)
</script>

<style scoped>
.q-table__card {
  border-radius: 4px;
}
</style>
