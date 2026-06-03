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
            :disable="props.row.username === 'admin'"
            @click="confirmDelete(props.row.id)"
          />
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="showDialog">
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">{{ isEdit ? '编辑用户' : '新增用户' }}</div>
        </q-card-section>

        <q-card-section>
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

const $q = useQuasar()
const rows = ref([])
const showDialog = ref(false)
const isEdit = ref(false)
const form = ref({ id: null, username: '', password: '' })
const pagination = ref({ page: 1, rowsPerPage: 10, rowsNumber: 0 })

const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left' },
  { name: 'name', label: '用户姓名', field: 'name', align: 'left' },
  { name: 'phone', label: '电话', field: 'phone', align: 'left' },
  { name: 'actions', label: '操作', align: 'center' }
]

// 获取数据
const onRequest = async (props) => {
  const { page, rowsPerPage } = props.pagination
  const res = await userApi.list({ page, per_page: rowsPerPage })
  rows.value = res.data;
  pagination.value.rowsNumber = res.total
  pagination.value.page = page
  pagination.value.rowsPerPage = rowsPerPage
}

// 打开弹窗
const openDialog = (row = null) => {
  if (row) {
    isEdit.value = true
    form.value = { ...row, password: '' }
  } else {
    isEdit.value = false
    form.value = { id: null, username: '', password: '' }
  }
  showDialog.value = true
}

// 保存/更新
const saveUser = async () => {
  try {
    if (isEdit.value) {
      await userApi.update(form.value.id, form.value)
    } else {
      await userApi.store(form.value)
    }
    showDialog.value = false
    onRequest({ pagination: pagination.value })
  } catch (e) {
    console.log('操作失败',e);
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
