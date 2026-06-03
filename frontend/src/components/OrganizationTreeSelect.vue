<template>
  <q-select
    ref="selectRef"
    outlined
    dense
    v-model="selectedLabel"
    :label="label"
    readonly
    class="cursor-pointer"
    @click="showTree = true"
    :loading="loading"
    clearable
    @clear="handleClear"
  >
    <q-menu v-model="showTree" fit @show="syncExpanded">
      <q-card style="min-width: 300px; max-height: 450px;" class="column no-wrap">
        <q-card-section class="col scroll q-pa-sm">
          <q-tree
            :nodes="treeData"
            node-key="id"
            label-key="name"
            children-key="children_recursive"
            v-model:selected="localSelectedId"
            v-model:expanded="expanded"
            default-expand-all
            no-nodes-label="暂无数据"
            color="primary"
          >
            <template v-slot:default-header="prop">
              <div
                class="row items-center full-width cursor-pointer q-py-xs"
                @click.stop="handleNodeClick(prop.node)"
              >
                <q-icon
                  :name="prop.node.children_recursive?.length ? 'account_tree' : 'business_center'"
                  :color="Number(prop.node.id) === Number(modelValue) ? 'primary' : 'grey-7'"
                  size="20px"
                  class="q-mr-sm"
                />
                <div :class="{ 'text-primary text-weight-bold': Number(prop.node.id) === Number(modelValue) }">
                  {{ prop.node.name }}
                </div>
              </div>
            </template>
          </q-tree>
        </q-card-section>
      </q-card>
    </q-menu>

    <template v-slot:append v-if="!selectedLabel">
      <q-icon name="account_tree" />
    </template>
  </q-select>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { api } from 'boot/axios'

const props = defineProps({
  modelValue: { type: [Number, String], default: null },
  label: { type: String, default: '所属组织' }
})

const emit = defineEmits(['update:modelValue', 'select'])

// --- 状态变量 ---
const showTree = ref(false)
const loading = ref(false)
const treeData = ref([])
const expanded = ref([])
const selectedLabel = ref('')
const localSelectedId = ref(null) // 用于同步 q-tree 的选中状态

/**
 * 核心修正：手动处理节点点击
 * @param {Object} node 节点对象
 */
const handleNodeClick = (node) => {
  if (!node) return

  // 1. 更新 UI 显示
  selectedLabel.value = node.name
  localSelectedId.value = node.id

  // 2. 向上层抛出 ID (即 update:modelValue)
  emit('update:modelValue', node.id)

  // 3. 抛出完整对象
  emit('select', node)

  // 4. 关闭菜单
  showTree.value = false
}

/**
 * 加载数据
 */
const loadTreeData = async () => {
  loading.value = true
  try {
    const response = await api.get('v1/organizations/null/tree')
    if (response) {
      treeData.value = response
      if (props.modelValue) {
        initDisplay(props.modelValue)
      }
    }
  } catch (error) {
    console.error('Fetch error:', error)
  } finally {
    loading.value = false
  }
}

/**
 * 初始化回显逻辑
 */
const findNode = (nodes, id) => {
  for (const node of nodes) {
    if (Number(node.id) === Number(id)) return node
    if (node.children_recursive?.length) {
      const found = findNode(node.children_recursive, id)
      if (found) return found
    }
  }
  return null
}

const initDisplay = (id) => {
  const node = findNode(treeData.value, id)
  if (node) {
    selectedLabel.value = node.name
    localSelectedId.value = node.id
  }
}

/**
 * 清除选择
 */
const handleClear = () => {
  selectedLabel.value = ''
  localSelectedId.value = null
  emit('update:modelValue', null)
  emit('select', null)
}

// 确保展开状态同步
const syncExpanded = () => {
  if (props.modelValue) {
    localSelectedId.value = props.modelValue
  }
}

watch(() => props.modelValue, (val) => {
  if (!val) {
    selectedLabel.value = ''
    localSelectedId.value = null
  } else {
    initDisplay(val)
  }
})

onMounted(loadTreeData)
</script>
