<template>
  <q-page class="q-pa-md">
    <div class="text-h6">地址选择</div>
    <AreaSelector v-model="formData.area_id" />
    <div class="q-mt-md">当前选中的区域ID: {{ formData.area_id }}</div>
  </q-page>
  <q-btn label="显示提示" @click="showNotify" />
</template>

<script setup>
import { reactive } from 'vue'
import AreaSelector from 'components/AreaSelector.vue'
import { useQuasar } from 'quasar';
const $q = useQuasar();
const showNotify = () => {
  // 正确的notify调用方式
  $q.notify('abc');
}
const formData = reactive({
  area_id: null
})
</script>
