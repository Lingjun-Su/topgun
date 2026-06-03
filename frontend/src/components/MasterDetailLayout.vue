<template>
  <div class="master-detail-container q-pa-none">
    <template v-if="$q.screen.gt.sm">
      <q-splitter
        v-model="splitterModel"
        :limits="[0, 100]"
        style="height: calc(100-vh - 100px); min-height: 500px"
        unit="%"
      >
        <template #before>
          <div class="q-pa-md">
            <div class="row items-center q-mb-md">
              <slot name="filter"></slot>
            </div>
            <slot name="list"></slot>
          </div>
        </template>

        <template #separator>
          <q-avatar color="primary" text-color="white" size="24px" icon="drag_indicator" class="cursor-pointer" />
        </template>

        <template #after>
          <div v-if="hasSelection" class="q-pa-md relative-position">
            <div class="row items-center justify-between q-mb-md">
              <div class="text-h6">详情信息</div>
              <q-btn flat round icon="close" @click="$emit('close-detail')" />
            </div>
            <q-scroll-area style="height: calc(100vh - 200px)">
              <slot name="detail"></slot>
            </q-scroll-area>
          </div>
          <div v-else class="flex flex-center full-height text-grey-6">
            <q-icon name="arrow_back" size="lg" class="q-mr-sm" />
            请从左侧列表选择一项查看详情
          </div>
        </template>
      </q-splitter>
    </template>

    <template v-else>
      <div class="q-pa-md">
        <div class="q-mb-md">
          <slot name="filter"></slot>
        </div>
        <slot name="list"></slot>
      </div>

      <q-dialog v-model="internalShowDialog" maximized transition-show="slide-up" transition-hide="slide-down">
        <q-card>
          <q-bar class="bg-primary text-white">
            <span>详情编辑</span>
            <q-space />
            <q-btn dense flat icon="close" v-close-popup>
              <q-tooltip>关闭</q-tooltip>
            </q-btn>
          </q-bar>
          <q-card-section class="q-pa-md">
            <slot name="detail"></slot>
          </q-card-section>
        </q-card>
      </q-dialog>
    </template>
  </div>
</template>

<script setup>
import { ref,  computed } from 'vue';
import { useQuasar } from 'quasar';

const $q = useQuasar();
const props = defineProps({
  hasSelection: Boolean, // 是否选中了某行
  showDialog: Boolean,   // 窄屏下是否显示弹窗
});

const emit = defineEmits(['update:showDialog', 'close-detail']);

// 默认左右比例 40% : 60%
const splitterModel = ref(40);

// 同步 Dialog 状态
const internalShowDialog = computed({
  get: () => props.showDialog,
  set: (val) => emit('update:showDialog', val)
});

// 如果你想提供一个方法给外部强制隐藏左边
// const toggleFullScreenDetail = () => {
//   splitterModel.value = 0;
// };
</script>

<style scoped>
.master-detail-container {
  background: white;
  border-radius: 8px;
  overflow: hidden;
}
</style>
