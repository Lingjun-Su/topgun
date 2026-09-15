<template>
  <q-page padding>
    <q-card flat bordered>
      <q-card-section class="row items-center">
        <div class="text-h6">待执行同步任务管理</div>
        <q-space />
        <q-btn icon="refresh" label="刷新列表" color="primary" @click="fetchData" :loading="loading" />
      </q-card-section>

      <q-separator />

      <q-table
        :rows="rows"
        :columns="columns"
        row-key="id"
        flat
        :loading="loading"
        no-data-label="当前没有待执行的任务"
      >
        <template v-slot:body-cell-actions="props">
          <q-td :props="props">
            <q-btn
              flat
              round
              dense
              color="negative"
              icon="delete_forever"
              @click="confirmCancel(props.row)"
            >
              <q-tooltip>取消同步任务</q-tooltip>
            </q-btn>
          </q-td>
        </template>

        <template v-slot:body-cell-sync_status="props">
          <q-td :props="props">
            <q-chip size="sm" color="warning" text-white>待执行</q-chip>
          </q-td>
        </template>
      </q-table>
    </q-card>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

const $q = useQuasar()
const rows = ref([])
const loading = ref(false)

const columns = [
  { name: 'order_no', label: '订单号', field: 'order_no', align: 'left' },
  { name: 'mobile', label: '手机号', field: 'mobile', align: 'left' },
  { name: 'create_time', label: '订购时间', field: 'create_time', align: 'center' },
  { name: 'sync_status', label: '状态', field: 'sync_status', align: 'center' },
  { name: 'actions', label: '操作', field: 'actions', align: 'center' }
]

const fetchData = async () => {
  loading.value = true
  try {
    const res = await api.get('/api_v2/ThirdChannel/pendingOrders')
    if (res.data.code === 0) {
      rows.value = res.data.data
    }
  } catch (error) {
    console.log("获取数据失败",error);
    $q.notify({ color: 'negative', message: '获取数据失败' })
  } finally {
    loading.value = false
  }
}

const confirmCancel = (row) => {
  $q.dialog({
    title: '确认取消',
    message: `确定要取消订单 [${row.order_no}] 的推送任务吗？该操作不可撤销。`,
    cancel: true,
    persistent: true
  }).onOk(async () => {
    try {
      const res = await api.post('/api_v2/ThirdChannel/cancelOrder', { id: row.id })
      if (res.data.code === 0) {
        $q.notify({ color: 'positive', message: '任务已取消' })
        fetchData() // 刷新列表
      }
    } catch (error) {
      console.log("取消失败",error)
      $q.notify({ color: 'negative', message: '取消失败' })
    }
  })
}

onMounted(fetchData)
</script>
