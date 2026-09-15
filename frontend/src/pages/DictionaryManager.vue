<template>
  <q-page class="q-pa-md">
    <master-detail-layout
      :has-selection="!!selectedItem"
      v-model:showDialog="showMobileDialog"
      @close-detail="selectedItem = null"
    >
      <template #filter>
        <div class="row q-gutter-sm full-width items-center">
          <q-input dense outlined v-model="filter.type" placeholder="类型编码" class="col" />
          <q-input dense outlined v-model="filter.label" placeholder="字典名称" class="col" />
          <q-btn color="primary" icon="search" @click="loadData" />
          <q-btn color="positive" icon="add" label="新增" @click="prepareCreate" />
        </div>
      </template>

      <template #list>
        <q-table
          :rows="rows"
          :columns="columns"
          row-key="id"
          flat
          bordered
          :loading="loading"
          @row-click="onRowClick"
          selection="single"
          v-model:selected="selectedRows"
        >
          <template v-slot:body-cell-status="props">
            <q-td :props="props">
              <q-badge :color="props.value === 1 ? 'green' : 'red'">
                {{ props.value === 1 ? '启用' : '禁用' }}
              </q-badge>
            </q-td>
          </template>
        </q-table>
      </template>

      <template #detail>
        <dictionary-detail
          :initial-data="selectedItem"
          :is-new="isCreateMode"
          @saved="handleSaved"
          @cancel="onDetailCancel"
        />
      </template>
    </master-detail-layout>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import MasterDetailLayout from 'components/MasterDetailLayout.vue';
import DictionaryDetail from 'components/DictionaryDetail.vue';
import { useQuasar } from 'quasar'
import { dictionaryApi } from 'src/api/dictionary'; // 导入刚才写的 API 模块

const rows = ref([]);
const loading = ref(false);
const filter = ref({ type: '', label: '' });
const selectedRows = ref([]); // QTable 的选中状态
const selectedItem = ref(null); // 当前正在编辑的条目对象
const isCreateMode = ref(false);
const showMobileDialog = ref(false);

const columns = [
  { name: 'type', label: '类型', field: 'type', align: 'left', sortable: true },
  { name: 'code', label: '编码', field: 'code', align: 'left' },
  { name: 'label', label: '名称', field: 'label', align: 'left' },
  { name: 'status', label: '状态', field: 'status', align: 'center' },
];

const $q = useQuasar();
// 分页配置
const pagination = ref({
  sortBy: 'sort',
  descending: false,
  page: 1,
  rowsPerPage: 10,
  rowsNumber: 0 // 后端返回的总条数
});

// 加载数据
const loadData = async (props) => {
  loading.value = true;
  const { page, rowsPerPage, sortBy, descending } = props?.pagination || pagination.value;

  try {
    const response = await dictionaryApi.index({
      page,
      per_page: rowsPerPage,
      sort_by: sortBy,
      order: descending ? 'desc' : 'asc',
      ...filter.value // 展开查询条件
    });

    // 假设 Laravel 使用了 LengthAwarePaginator
    rows.value = response.data.data;
    pagination.value.rowsNumber = response.data.total;
    pagination.value.page = page;
    pagination.value.rowsPerPage = rowsPerPage;
  } catch (error) {
    console.log("加载失败",error);
    $q.notify({ color: 'negative', message: '加载失败' });
  } finally {
    loading.value = false;
  }
};

// 监听表格翻页/排序事件
// const onRequest = (props) => {
//   loadData(props);
// };


const onRowClick = (evt, row) => {
  isCreateMode.value = false;
  selectedItem.value = { ...row };
  selectedRows.value = [row];
  showMobileDialog.value = true; // 在窄屏下自动打开弹窗
};

const prepareCreate = () => {
  isCreateMode.value = true;
  selectedItem.value = { type: '', code: '', label: '', sort: 0, status: 1 };
  showMobileDialog.value = true;
};

const handleSaved = (newData) => {
  // 保存成功后的逻辑：刷新列表
  loadData();
  if (isCreateMode.value) {
    selectedItem.value = null;
    showMobileDialog.value = false;
  }
  console.log(newData);
};

const onDetailCancel = () => {
  if (isCreateMode.value) {
    selectedItem.value = null;
    showMobileDialog.value = false;
  }
};

onMounted(() => loadData());
</script>
