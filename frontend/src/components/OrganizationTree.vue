<template>
  <div class="org-tree-container">
    <q-input
      v-model="filter"
      label="搜索组织机构"
      dense
      outlined
      class="q-mb-md"
    >
      <template v-slot:append>
        <q-icon name="search" />
      </template>
    </q-input>

    <q-tree
      :nodes="treeData"
      node-key="id"
      label-key="name"
      children-key="children_recursive"
      :filter="filter"
      default-expand-all
      no-connectors
      v-model:selected="selectedId"
      @update:selected="handleSelection"
    >
      <template v-slot:default-header="prop">
        <div class="row items-center full-width">
          <q-icon
            :name="getOrgIcon(prop.node.type)"
            :color="prop.node.status ? 'primary' : 'grey'"
            size="24px"
            class="q-mr-sm"
          />

          <div :class="{ 'text-strike text-grey': !prop.node.status }">
            {{ prop.node.name }}
            <q-badge v-if="!prop.node.status" color="red" label="已停用" outline class="q-ml-xs" />
          </div>

          <q-space />

          <q-btn
            v-if="showDeleteAction && prop.node.status"
            flat
            round
            dense
            color="negative"
            icon="delete_outline"
            @click.stop="confirmDelete(prop.node)"
          >
            <q-tooltip>逻辑删除</q-tooltip>
          </q-btn>
        </div>
      </template>
    </q-tree>
  </div>
</template>

<script setup>
import { ref, onMounted, defineProps, defineEmits } from 'vue';
import { useQuasar } from 'quasar';
import {organizationApi} from 'src/api/organization'

// --- 属性与事件定义 ---
defineProps({
  // 是否显示删除按钮
  showDeleteAction: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['select', 'deleted']);

// --- 响应式数据 ---
const $q = useQuasar();
const treeData = ref([]);
const filter = ref('');
const selectedId = ref(null);

/**
 * 获取组织架构数据
 * 对应后端接口：api/v1/organizations/null/tree
 */
const fetchTreeData = async () => {
  $q.loading.show({ message: '正在加载组织架构...' });
  try {
    const response = await organizationApi.tree();
    console.log(response);
    // 假设后端返回的是处理好的树形 JSON 结构
    treeData.value = response.data.data;
  } catch (error) {
    console.error('获取组织数据失败:', error);
    $q.notify({ color: 'negative', message: '数据加载失败，请检查网络或权限' });
  } finally {
    $q.loading.hide();
  }
};

/**
 * 根据类型映射图标
 * @param {String} type - HEAD, SUB, PARTNER, ATTACH
 */
const getOrgIcon = (type) => {
  const iconMap = {
    '0': 'domain',           // 总部：大楼图标
    '1': 'corporate_fare',   // 子公司
    '2': 'handshake',     // 合作伙伴
    '3': 'account_tree'    // 挂靠单位
  };
  return iconMap[type] || 'apartment';
};

/**
 * 处理节点选中事件
 */
const handleSelection = (targetId) => {
  if (targetId) {
    // 寻找被选中的节点对象并返回给父组件
    emit('select', targetId);
  }
};

/**
 * 逻辑删除确认
 * 严格遵守“数据审计”和“逻辑删除”规范
 */
const confirmDelete = (node) => {
  $q.dialog({
    title: '确认停用',
    message: `确定要逻辑删除（停用）机构「${node.name}」吗？该操作将被审计记录。`,
    cancel: true,
    persistent: true,
    ok: { color: 'negative', label: '确定删除' }
  }).onOk(async () => {
    try {
      // 执行逻辑删除接口 (通常是 PATCH 或 DELETE，后端将 status 设为 0)
      await organizationApi.destroy(node.id);
      $q.notify({ color: 'success', message: '已成功执行逻辑删除' });

      // 刷新数据以体现变动
      await fetchTreeData();
      emit('deleted', node.id);
    } catch (error) {
      console.log("删除失败",error)
      $q.notify({ color: 'negative', message: '删除失败' });
    }
  });
};

onMounted(() => {
  console.log("onMounted")
  fetchTreeData();
});
</script>

<style scoped>
.org-tree-container {
  max-width: 600px;
  background: white;
  padding: 16px;
  border-radius: 8px;
}
</style>
