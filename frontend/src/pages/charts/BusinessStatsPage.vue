<template>
  <q-page padding class="bg-grey-2">
    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="text-subtitle2 q-mb-sm text-primary">查询条件</div>
        <div class="row q-col-gutter-md">
          <div class="col-12 col-md-6">
            <BusinessProductLinkage
              v-model:business-id="filter.business_id"
              v-model:product-id="filter.product_id"
              @linkage-change="fetchData"
            />
          </div>
          <div class="col-12 col-md-3">
            <q-input v-model="dateDisplay" label="统计周期" dense outlined readonly>
              <template v-slot:append>
                <q-icon name="event" class="cursor-pointer">
                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                    <q-date v-model="filter.dateRange" range mask="YYYY-MM-DD" @update:model-value="fetchData" />
                  </q-popup-proxy>
                </q-icon>
              </template>
            </q-input>
          </div>

          <div class="col-12 col-md-3">
            <q-btn-toggle
              v-model="filter.type"
              toggle-color="primary"
              flat bordered dense spread
              :options="[{label:'按日', value:'day'}, {label:'按月', value:'month'}, {label:'按年', value:'year'}]"
              @update:model-value="fetchData"
            />
          </div>

          <div class="col-12 col-md-4 q-gutter-sm">
            <q-toggle v-model="showChart" label="显示图表" />
            <q-toggle v-model="showTable" label="显示表格" class="q-ml-sm" />
          </div>
          <div class="col-12 col-md-2 text-right">
            <q-btn color="primary" icon="refresh" label="查询" :loading="loading" @click="fetchData" />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <q-card v-if="showChart" flat bordered class="q-mb-md">
      <q-card-section>
        <apexchart height="400" :type="filter.chartType" :options="chartOptions" :series="chartSeries" />
      </q-card-section>
    </q-card>

    <q-card v-if="showTable" flat bordered>
      <q-card-section horizontal>
        <q-card-section>
          <q-card-section>合计</q-card-section>
          <q-card-section>总数量：{{ total_quantity }}</q-card-section>
          <q-card-section>总金额：{{ total_amouts }}元</q-card-section>
        </q-card-section>

        <q-separator vertical />

        <q-card-section>
          <q-table
            flat
            :rows="rows"
            :columns="columns"
            :loading="loading"
            :pagination="{ rowsPerPage: 10 }"
            row-key="time_label"
          >
            <template v-slot:body-cell-total_value="props">
              <q-td :props="props">
                <q-badge outline color="blue" :label="'¥ ' + props.value.toLocaleString()" />
              </q-td>
            </template>
          </q-table>
        </q-card-section>
      </q-card-section>

    </q-card>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { productOrderReportApi } from 'src/api/reports';
import  BusinessProductLinkage from 'src/components/BusinessProductLinkage.vue';

// 状态控制
const loading = ref(false);
const showChart = ref(true);
const showTable = ref(true);
const rows = ref([]);
const total_quantity =ref(0);//合计数量
const total_amouts =ref(0);//合计金额

// 完整筛选条件
const filter = reactive({
  org_id: null,
  dept_id: null,
  employee_id: null,
  dateRange: { from: '2026-01-01', to: '2026-04-01' },
  type: 'day',
  chartType: 'line'
});

const dateDisplay = computed(() =>
  filter.dateRange?.from ? `${filter.dateRange.from} ~ ${filter.dateRange.to}` : '请选择日期'
);

// ApexCharts 配置
const chartSeries = ref([
  { name: '销售金额', type: 'bar', data: [] ,color:'#E14928'},
  { name: '销售数量', type: 'line', data: [] },
]);

const chartOptions = ref({
  chart: { stacked: false, toolbar: { show: true } },
  stroke: { width: [1, 2], curve: 'smooth' },
  xaxis: { categories: [] },
  yaxis: [
    { title: { text: "金额" }, labels: { formatter: (v) => v.toFixed(0) } },
    { opposite: true, title: { text: "数量" } }
  ],
  colors: ['#2196F3', '#4CAF50','#E14928'],
});

// 表格列定义
const columns = [
  { name: 'time_label', label: '周期', field: 'time_label', align: 'left', sortable: true },
  { name: 'total_quantity', label: '数量', field: 'total_quantity', align: 'right', sortable: true },
  { name: 'total_value', label: '金额', field: 'total_value', align: 'right', sortable: true },
];

// 数据请求逻辑
const fetchData = async () => {
  loading.value = true;
  try {
    const params = {
      ...filter,
      start_date: filter.dateRange.from,
      end_date: filter.dateRange.to
    };

    const res = await productOrderReportApi.getStats(params); // 自动解包 ApiResponse
    rows.value = res;

    // 确保这里的字段名与后端 JSON 里的 key 完全一致
    const values = res.map(i => i.total_value);    // 金额
    const quantities = res.map(i => i.total_quantity); // 数量

    //金额和数量合计
    total_quantity.value=0;
    total_amouts.value=0;
    for(let i =0;i<values.length;i++){
      total_amouts.value +=parseFloat(values[i]);
    }
    for(let i =0;i<quantities.length;i++){
      total_quantity.value +=parseInt(quantities[i]);
    }


    // 重新赋值整个 series 数组，触发 Vue 响应式更新
    chartSeries.value = [
      {
        name: '销售金额',
        type: 'bar',
        color:'#E14928',
        data: values
      },
      {
        name: '销售数量',
        type: 'line',
        data: quantities
      }
    ];

    // 更新 X 轴坐标
    chartOptions.value = {
      ...chartOptions.value,
      xaxis: { categories: res.map(i => i.time_label) }
    };

  } finally {
    loading.value = false;
  }
};

onMounted(fetchData);
</script>
