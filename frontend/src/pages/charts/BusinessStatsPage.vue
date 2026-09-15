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

    <!-- 同期对比摘要 -->
    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <div class="text-caption text-grey-6">本期金额</div>
            <div class="text-h5 text-weight-bold text-primary">{{ formatAmount(summary.current_total_value) }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <div class="text-caption text-grey-6">同期金额</div>
            <div class="text-h5 text-weight-bold text-grey-8">{{ formatAmount(summary.previous_total_value) }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <div class="text-caption text-grey-6">金额同比</div>
            <div class="text-h5 text-weight-bold" :class="summary.value_change_percent >= 0 ? 'text-positive' : 'text-negative'">
              <q-icon :name="summary.value_change_percent >= 0 ? 'arrow_drop_up' : 'arrow_drop_down'" size="md" />
              {{ summary.value_change_percent }}%
            </div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <div class="text-caption text-grey-6">数量同比</div>
            <div class="text-h5 text-weight-bold" :class="summary.quantity_change_percent >= 0 ? 'text-positive' : 'text-negative'">
              <q-icon :name="summary.quantity_change_percent >= 0 ? 'arrow_drop_up' : 'arrow_drop_down'" size="md" />
              {{ summary.quantity_change_percent }}%
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <q-card v-if="showChart" flat bordered class="q-mb-md">
      <q-card-section>
        <apexchart height="400" :type="filter.chartType" :options="chartOptions" :series="chartSeries" />
      </q-card-section>
    </q-card>

    <q-card v-if="showTable" flat bordered>
      <q-card-section horizontal>
        <q-card-section>
          <q-card-section class="text-weight-bold">本期数据</q-card-section>
          <q-card-section>总数量：{{ currentTotalQty }}</q-card-section>
          <q-card-section>总金额：{{ formatAmount(currentTotalValue) }}元</q-card-section>
        </q-card-section>

        <q-separator vertical />

        <q-card-section>
          <q-card-section class="text-weight-bold">同期数据</q-card-section>
          <q-card-section>总数量：{{ previousTotalQty }}</q-card-section>
          <q-card-section>总金额：{{ formatAmount(previousTotalValue) }}元</q-card-section>
        </q-card-section>

        <q-separator vertical />

        <q-card-section>
          <q-card-section class="text-weight-bold">对比</q-card-section>
          <q-card-section>
            金额：<span :class="summary.value_change_percent >= 0 ? 'text-positive' : 'text-negative'">{{ summary.value_change_percent }}%</span>
          </q-card-section>
          <q-card-section>
            数量：<span :class="summary.quantity_change_percent >= 0 ? 'text-positive' : 'text-negative'">{{ summary.quantity_change_percent }}%</span>
          </q-card-section>
        </q-card-section>
      </q-card-section>

      <q-separator />

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
    </q-card>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { productOrderReportApi } from 'src/api/reports';
import BusinessProductLinkage from 'src/components/BusinessProductLinkage.vue';

// 状态控制
const loading = ref(false);
const showChart = ref(true);
const showTable = ref(true);
const rows = ref([]);
const currentTotalValue = ref(0);
const currentTotalQty = ref(0);
const previousTotalValue = ref(0);
const previousTotalQty = ref(0);

// 同期对比摘要
const summary = reactive({
  current_total_value: 0,
  current_total_quantity: 0,
  previous_total_value: 0,
  previous_total_quantity: 0,
  value_change_percent: 0,
  quantity_change_percent: 0,
});

// 默认最近一个月
const formatDate = (d) => {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
};
const today = new Date();
const thirtyDaysAgo = new Date(today);
thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

// 完整筛选条件
const filter = reactive({
  org_id: null,
  dept_id: null,
  employee_id: null,
  dateRange: { from: formatDate(thirtyDaysAgo), to: formatDate(today) },
  type: 'day',
  chartType: 'line'
});

const dateDisplay = computed(() =>
  filter.dateRange?.from ? `${filter.dateRange.from} ~ ${filter.dateRange.to}` : '请选择日期'
);

// ApexCharts 配置
const chartSeries = ref([
  { name: '本期金额', type: 'bar', data: [], color: '#E14928' },
  { name: '本期数量', type: 'line', data: [] },
  { name: '同期金额', type: 'bar', data: [], color: '#90CAF9' },
  { name: '同期数量', type: 'line', data: [], color: '#A5D6A7' },
]);

const chartOptions = ref({
  chart: { stacked: false, toolbar: { show: true } },
  stroke: { width: [1, 2, 1, 2], curve: 'smooth', dashArray: [0, 0, 5, 5] },
  xaxis: { categories: [] },
  yaxis: [
    { title: { text: '金额' }, labels: { formatter: (v) => v.toFixed(0) } },
    { opposite: true, title: { text: '数量' } }
  ],
  colors: ['#E14928', '#4CAF50', '#90CAF9', '#A5D6A7'],
  legend: { position: 'top' },
});

// 表格列定义 — 加同期对比列
const columns = [
  { name: 'time_label', label: '周期', field: 'time_label', align: 'left', sortable: true },
  { name: 'current_quantity', label: '本期数量', field: 'current_quantity', align: 'right', sortable: true },
  { name: 'current_value', label: '本期金额', field: 'current_value', align: 'right', sortable: true },
  { name: 'previous_quantity', label: '同期数量', field: 'previous_quantity', align: 'right', sortable: true },
  { name: 'previous_value', label: '同期金额', field: 'previous_value', align: 'right', sortable: true },
];

const formatAmount = (val) => {
  const num = parseFloat(val || 0);
  return '¥' + num.toLocaleString('zh-CN', { minimumFractionDigits: 2 });
};

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
    const currentData = res.data.current || [];
    const previousData = res.data.previous || [];

    // 合并当前和同期数据为表格行
    const mergedMap = {};
    currentData.forEach((item) => {
      mergedMap[item.time_label] = {
        time_label: item.time_label,
        current_quantity: item.total_quantity,
        current_value: item.total_value,
        previous_quantity: 0,
        previous_value: 0,
      };
    });
    previousData.forEach((item) => {
      if (mergedMap[item.time_label]) {
        mergedMap[item.time_label].previous_quantity = item.total_quantity;
        mergedMap[item.time_label].previous_value = item.total_value;
      } else {
        mergedMap[item.time_label] = {
          time_label: item.time_label,
          current_quantity: 0,
          current_value: 0,
          previous_quantity: item.total_quantity,
          previous_value: item.total_value,
        };
      }
    });
    rows.value = Object.values(mergedMap).sort((a, b) => a.time_label.localeCompare(b.time_label));

    // 汇总数据
    currentTotalValue.value = summary.current_total_value = res.data.summary?.current_total_value || 0;
    currentTotalQty.value = summary.current_total_quantity = res.data.summary?.current_total_quantity || 0;
    previousTotalValue.value = summary.previous_total_value = res.data.summary?.previous_total_value || 0;
    previousTotalQty.value = summary.previous_total_quantity = res.data.summary?.previous_total_quantity || 0;
    summary.value_change_percent = res.data.summary?.value_change_percent || 0;
    summary.quantity_change_percent = res.data.summary?.quantity_change_percent || 0;

    // 图表系列
    chartSeries.value = [
      {
        name: '本期金额',
        type: 'bar',
        color: '#E14928',
        data: currentData.map((i) => i.total_value),
      },
      {
        name: '本期数量',
        type: 'line',
        data: currentData.map((i) => i.total_quantity),
      },
      {
        name: '同期金额',
        type: 'bar',
        color: '#90CAF9',
        data: previousData.map((i) => i.total_value),
      },
      {
        name: '同期数量',
        type: 'line',
        color: '#A5D6A7',
        data: previousData.map((i) => i.total_quantity),
      },
    ];

    chartOptions.value = {
      ...chartOptions.value,
      xaxis: { categories: currentData.map((i) => i.time_label) },
    };
  } finally {
    loading.value = false;
  }
};

onMounted(fetchData);
</script>

<style lang="scss" scoped>
// 保证卡片在不同高度下的一致性
.full-height {
  min-height: 385px;
}
</style>