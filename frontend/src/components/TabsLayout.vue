<template>
  <div class="tabs-layout q-mt-sm">
    <!-- 标签页头部 -->
    <q-tabs
      v-model="activeTab"
      dense
      align="left"
      inline-label
      class="bg-indigo-1 shadow-2"
    >
      <q-tab
        v-for="tab in tabs"
        :key="tab.id"
        :name="tab.id"
        :label="tab.title"
        :icon="tab.icon"
      >
        <q-btn
          v-if="!tab.isHome"
          dense
          flat
          round
          icon="close"
          size="xs"
          class="q-ml-xs"
          @click.stop="closeTab(tab.id)"
        />
      </q-tab>
    </q-tabs>

    <!-- 标签页内容 -->
    <q-tab-panels
      v-model="activeTab"
      animated
      keep-alive
      class="bg-grey-1"
    >
      <q-tab-panel
        v-for="tab in tabs"
        :key="tab.id"
        :name="tab.id"
        class="q-pa-none"
      >
        <component
          :is="tab.component"
          :key="tab.id"
          v-bind="tab.props"
        />
      </q-tab-panel>
    </q-tab-panels>
  </div>
</template>

<script setup>
import { ref,  markRaw,  watch } from 'vue'
import { useRoute } from 'vue-router'


const route = useRoute()
// const router = useRouter()

// 标签页列表
const tabs = ref([])

// 当前激活的标签页
const activeTab = ref('home')

// 添加标签页
function addTab(routeInfo) {
  // 检查是否已存在相同路由的标签页
  const existingTab = tabs.value.find(tab =>
    tab.id === routeInfo.name ||
    tab.path === routeInfo.path
  )

  if (existingTab) {
    activeTab.value = existingTab.id
    return existingTab.id
  }

  const tabId = `tab-${Date.now()}`

  tabs.value.push({
    id: tabId,
    title: routeInfo.meta?.title || routeInfo.name || '新标签',
    component: markRaw(routeInfo.component),
    icon: routeInfo.meta?.icon,
    path: routeInfo.path,
    props: { ...routeInfo.params, ...routeInfo.query },
    isHome: false
  })

  activeTab.value = tabId
  return tabId
}

// 关闭标签页
function closeTab(tabId) {
  if (tabId === 'home') return // 首页不能关闭

  const index = tabs.value.findIndex(tab => tab.id === tabId)
  if (index > -1) {
    tabs.value.splice(index, 1)

    // 如果关闭的是当前激活的标签页，激活前一个标签页
    if (activeTab.value === tabId) {
      activeTab.value = tabs.value[Math.max(0, index - 1)].id
    }
  }
}

// 监听路由变化，自动打开新标签页
watch(
  () => route,
  (newRoute) => {
    if (newRoute.meta?.openInNewTab !== false && newRoute.name !== 'home') {
      addTab({
        name: newRoute.name,
        path: newRoute.path,
        component: newRoute.matched[newRoute.matched.length - 1].components.default,
        meta: newRoute.meta,
        params: newRoute.params,
        query: newRoute.query
      })
    }
  },
  { immediate: true, deep: true }
)
</script>
