<template>
  <q-card class="my-chart-card shadow-1 rounded-borders" flat bordered>

    <q-item class="q-pb-none">
      <q-item-section>
        <div class="text-subtitle1 text-weight-bold text-primary">
          {{ title }}
          <q-badge v-if="subtitle" outline color="grey-7" class="q-ml-sm text-caption">
            {{ subtitle }}
          </q-badge>
        </div>
      </q-item-section>

      <q-item-section side>
        <div class="row items-center q-gutter-xs">
          <q-btn
            flat
            round
            dense
            icon="refresh"
            color="grey-7"
            @click="$emit('refresh')"
          >
            <q-tooltip>刷新数据</q-tooltip>
          </q-btn>
          <q-btn
            flat
            round
            dense
            icon="open_in_new"
            color="grey-7"
            @click="$emit('more')"
          >
            <q-tooltip>查看详情</q-tooltip>
          </q-btn>
        </div>
      </q-item-section>
    </q-item>

    <q-separator inset />

    <q-card-section class="relative-position" style="min-height: 300px">
      <slot></slot>

      <q-inner-loading :showing="loading">
        <q-spinner-gears size="50px" color="primary" />
        <div class="text-grey-7 q-mt-sm">数据加载中...</div>
      </q-inner-loading>

      <div v-if="!loading && noData" class="absolute-center text-grey-5 column items-center">
        <q-icon name="bar_chart" size="48px" />
        <span>暂无相关数据</span>
      </div>
    </q-card-section>
  </q-card>
</template>

<script setup>
/**
 * ChartCard - 通用图表容器组件
 * 优化版：移除未使用的变量赋值，消除 ESLint 警告
 */
import { defineProps, defineEmits } from 'vue'

// 直接定义，无需赋值给变量 props
defineProps({
  title: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    default: ''
  },
  loading: {
    type: Boolean,
    default: false
  },
  noData: {
    type: Boolean,
    default: false
  }
})

// 直接定义，无需赋值给变量 emit
defineEmits(['refresh', 'more'])
</script>

<style lang="scss" scoped>
.my-chart-card {
  transition: all 0.3s ease;
  &:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
  }
}
</style>
