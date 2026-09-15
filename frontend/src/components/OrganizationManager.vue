<template>
    <div class="q-pa-md" style="height: 100vh">
    <q-splitter v-model="splitterModel" :limits="[20, 50]" style="height: 100%">

      <template v-slot:before>
        <div class="column full-height">
          <div class="q-pa-sm q-gutter-y-sm bg-grey-2">
            <q-input v-model="filter" dense outlined placeholder="搜索组织..." clearable>
              <template v-slot:append>
                <q-icon name="search" />
              </template>
            </q-input>

            <q-btn v-if="editable" color="primary" icon="add" label="新增顶级机构"
                   class="full-width" @click="createNewNode(null)" />
          </div>

          <q-scroll-area class="col q-pa-sm">
            <q-tree
              :nodes="orgData"
              node-key="id"
              label-key="name"
              :filter="filter"
              v-model:selected="selectedId"
              default-expand-all
            >
              <template v-slot:default-header="prop">
                <div class="row items-center justify-between full-width">
                  <div class="text-weight-bold">{{ prop.node.name }}</div>
                  <q-btn v-if="editable" flat round dense color="negative"
                         icon="delete" size="sm" @click.stop="deleteNode(prop.node.id)" />
                </div>
              </template>
            </q-tree>
          </q-scroll-area>
        </div>
      </template>

      <template v-slot:after>

        <div v-if="!editable" class="flex flex-center full-height text-grey-6 column">
          <q-icon name="account_tree" size="64px" class="q-mb-md" />
          请从左侧选择一个机构，或点击“新增”按钮
        </div>
        <div v-if="selectedNode || isAdding" class="q-pa-md">
          <div class="row items-center justify-between q-mb-md">
            <div class="text-h6">
              {{ isAdding ? '新增组织机构' : '机构详情: ' + formData.name }}
            </div>
            <q-chip :color="statusColor" text-color="white" dense>
              {{ statusLabel }}
            </q-chip>
          </div>

          <q-tabs v-model="tab" dense class="text-grey" active-color="primary" indicator-color="primary" align="justify">
            <q-tab name="basic" label="基本信息" />
            <q-tab name="finance" label="财务税务" />
            <q-tab name="salary" label="薪资申报" />
          </q-tabs>

          <q-separator />

          <q-tab-panels v-model="tab" animated>
            <q-tab-panel name="basic" class="q-gutter-y-sm" >
              <div class="row q-col-gutter-sm" >
                <q-input v-model="formData.name" label="组织名称" class="col-12 col-md-6" outlined dense :readonly="!editable" />
                <q-input v-model="formData.code" label="组织编码" class="col-12 col-md-6" outlined dense :readonly="!editable" />
                <OrganizationTypeSelect v-model="formData.organization_type" class="col-12 col-md-6" :readonly="!editable" />
                <q-select v-model="formData.status" :options="statusOptions" label="状态" class="col-12 col-md-6" outlined dense emit-value map-options :readonly="!editable" />
              </div>
              <div class="row q-col-gutter-sm q-mb-sm">
                  <RegionSelector v-model="formData.area" />
              </div>
              <div class="row q-col-gutter-sm">
                <q-input v-model="formData.contact_person" label="联系人" class="col-12 col-md-4" outlined dense :readonly="!editable" />
                <q-input v-model="formData.contact_phone" label="联系电话" class="col-12 col-md-4" outlined dense :readonly="!editable" />
                <q-input v-model="formData.credit_code" label="统一社会信用代码" class="col-12 col-md-4" outlined dense :readonly="!editable" />

                <q-input v-model="formData.business_scope" type="textarea" label="经营范围" class="col-12" outlined dense :readonly="!editable" />
              </div>
            </q-tab-panel>

            <q-tab-panel name="finance" class="q-gutter-y-sm">
              <div class="row q-col-gutter-sm">
                <q-select v-model="formData.tax_type" :options="['一般纳税人', '小规模纳税人', '非企业单位']"
                          label="税务类型" class="col-12 col-md-6" outlined dense :readonly="!editable" />
                <q-input v-model="formData.tax_register_no" label="税务登记证号" class="col-12 col-md-6" outlined dense :readonly="!editable" />
                <q-input v-model="formData.invoice_title" label="开票抬头" class="col-12" outlined dense :readonly="!editable" />

                <q-input v-model="formData.bank_name" label="开户银行" class="col-12 col-md-6" outlined dense :readonly="!editable" />
                <q-input v-model="formData.bank_account" label="银行账号" class="col-12 col-md-6" outlined dense :readonly="!editable" />
                <q-input v-model="formData.cost_center" label="成本中心编码" class="col-12 col-md-6" outlined dense :readonly="!editable" />
                <q-input v-model.number="formData.default_tax_rate" type="number" step="0.01" label="默认税率 (%)"
                          class="col-12 col-md-6" outlined dense :readonly="!editable" suffix="%" />
              </div>
            </q-tab-panel>

            <q-tab-panel name="salary" class="q-gutter-y-sm">
              <div class="row q-col-gutter-sm">
                <q-input v-model="formData.salary_issuer" label="薪资发放主体" class="col-12 col-md-8" outlined dense :readonly="!editable" />
                <q-input v-model.number="formData.salary_payday" type="number" label="薪资发放日"
                          class="col-12 col-md-4" outlined dense :readonly="!editable" suffix="号" />

                <q-separator class="col-12 q-my-sm" />
                <div class="text-subtitle2 col-12">个税申报信息</div>

                <q-input v-model="formData.tax_declaration_account" label="申报账号" class="col-12 col-md-6" outlined dense :readonly="!editable" />
                <q-input v-model="formData.tax_declaration_person" label="申报人姓名" class="col-12 col-md-6" outlined dense :readonly="!editable" />
              </div>
            </q-tab-panel>
          </q-tab-panels>

          <div v-if="editable" class="row q-gutter-sm q-mt-md justify-end">
            <q-btn label="保存数据" icon="save" color="primary" @click="saveData" />
            <q-btn v-if="!isAdding" label="新增下级" icon="add_business" color="secondary" outline @click="createNewNode(selectedId)" />
            <q-btn v-if="!isAdding" label="取消保存" icon="undo" color="secondary" outline @click="createNewNode(selectedId)" />
          </div>
        </div>
      </template>
    </q-splitter>
  </div>
</template>

<script setup>
import OrganizationTypeSelect from 'components/OrganizationTypeSelect.vue';
import RegionSelector from 'components/RegionSelector.vue';

import { ref, onMounted, watch,computed } from 'vue';
import { api } from 'boot/axios'; // 假设已配置 axios boot
import { useQuasar } from 'quasar';

// 接收 props，用于控制是否为只读模式
const props = defineProps({
  editable: { type: Boolean, default: true }
});

const $q = useQuasar();
const splitterModel = ref(30); // 初始分割比例
const filter = ref('');
const orgData = ref([]); // 树形数据源
const selectedId = ref(null); // 当前选中的 ID
const selectedNode = ref(null); // 当前选中的原始对象
const formData = ref({ id: null, name: '', code: '', description: '',organization_type:'', parent_id: 0 });
const tab = ref('basic');
const isAdding = ref(false);

const statusOptions = [
  { label: '启用/合作中', value: "1" },
  { label: '禁用/暂停', value: "0" },
  { label: '注销/终止', value: "2" }
];

// 状态计算属性（增加视觉严谨性）
const statusLabel = computed(() => {
  const opt = statusOptions.find(o => o.value === formData.value.status);
  return opt ? opt.label : '未知状态';
});

const statusColor = computed(() => {
  const colors = { 1: 'positive', 0: 'warning', 2: 'negative' };
  return colors[formData.value.status] || 'grey';
});

/**
 * 从 Laravel API 获取组织架构数据
 */
const fetchOrgData = async (parent_id=0) => {
  try {
    const response = await api.get('/sys-organizations?parentId='+parent_id);
    // 注意：Laravel 返回的数据需要处理成 q-tree 要求的嵌套格式
    if(parent_id==0){//无父级ID
      orgData.value = response.data.data;
    }else{//有父ID，赋值给对应父ID
      const target = orgData.value.find(item => item.id === parent_id);
      if (target) {
        target.children = response.data.data; // 或者设置为其他值
      }
    }
  } catch (error) {
    console.log("组织架构组件读取数据出错",error)
    $q.notify({ color: 'negative', message: '数据加载失败' });
  }
};

/**
 * 监听选择变化，填充表单
 */
watch(selectedId, (newId) => {

  if (newId) {
    fetchOrgData(newId);
    // 在本地递归查找选中的节点数据（或通过 API 另行请求）
    const findNode = (nodes) => {
      for (let n of nodes) {
        if (n.id === newId) return n;
        if (n.children) {
          const found = findNode(n.children);
          if (found) return found;
        }
      }
    };
    const node = findNode(orgData.value);
    selectedNode.value = node;
    formData.value = { ...node };
  } else {
    selectedNode.value = null;
  }
});

/**
 * 保存/更新数据
 */
const saveData = async () => {
  if (!props.editable) {
    $q.notify({ color: 'warning', message: '当前处于只读模式，无法保存' });
    return;
  }
  try {
    if (formData.value.id) {
      await api.put(`/sys-organizations/${formData.value.id}`, formData.value);
      console.log('has id');
    } else {
      await api.post('/sys-organizations', formData.value);
      console.log('no id');
    }
    console.log("success")
    $q.notify({ color: 'positive', message: '保存成功' });
    fetchOrgData(); // 刷新树
  } catch (error) {
    console.log("组织架构组件更新数据出错",error)
    $q.notify({ color: 'negative', message: '保存失败' });
  }
};

/**
 * 删除节点
 */
const deleteNode = (id) => {
  $q.dialog({
    title: '确认',
    message: '确定要删除该机构及其子机构吗？',
    cancel: true,
  }).onOk(async () => {
    await api.delete(`/sys-organizations/${id}`);
    fetchOrgData();
  });
};

/**
 * 准备新增节点
 */
const createNewNode = (parentId) => {

  // 1. 开启新增状态标识
  isAdding.value = true;

  // 2. 清除左侧树的选中状态（防止冲突）
  selectedId.value = null;
  selectedNode.value = null;

  // 3. 初始化表单数据，填入默认值
  formData.value = {
    id: null,
    name: '',
    code: '',
    organization_type: 'company', // 默认类型
    parent_id: parentId || 0,     // 顶级为 0
    status: "1",                    // 默认启用
    salary_payday: 15             // 默认发放日
    // 其他字段初始化为空字符串
  };

  // 4. 重置 UI 状态
  tab.value = 'basic';
};

onMounted(fetchOrgData);
</script>
