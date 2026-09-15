<template>
  <q-page class="q-pa-md">
    <q-splitter
      v-model="splitterModel"
      class="full-height"
    >
      <!-- 左侧面板 -->
      <template v-slot:before>
        <div class="column full-height">
          <!-- 搜索和新增区域 -->
          <div class="q-pb-md">
            <q-card class="q-pa-sm">
              <div class="row items-center q-gutter-sm">
                <q-input
                  v-model="searchText"
                  placeholder="搜索组织..."
                  dense
                  outlined
                  class="col"
                >
                  <template v-slot:append>
                    <q-icon name="search" />
                  </template>
                </q-input>
                <q-btn
                  color="primary"
                  icon="add"
                  label="新增"
                  @click="handleAddRoot"
                  dense
                />
              </div>
            </q-card>
          </div>

          <!-- 树状组织架构 -->
          <div class="col">
            <q-card class="full-height">
              <q-card-section class="q-pa-sm">
                <div class="text-h6">组织架构</div>
              </q-card-section>
              <q-separator />
              <q-card-section class="q-pa-none">
                <q-scroll-area style="height: 600px;">
                  <q-tree
                    :nodes="filteredTree"
                    node-key="id"
                    label-key="name"
                    children-key="children"
                    selected-color="primary"
                    v-model:selected="selectedNodeId"
                    v-model:expanded="expandedNodes"
                    default-expand-all
                    @update:selected="onNodeSelected"
                  >
                    <template v-slot:default-header="prop">
                      <div class="row items-center">
                        <q-icon
                          :name="getOrgTypeIcon(prop.node.type)"
                          class="q-mr-sm"
                          :color="prop.node.status === 1 ? 'primary' : 'grey'"
                        />
                        <div class="ellipsis">
                          {{ prop.node.name }}
                          <q-tooltip v-if="prop.node.name !== prop.node.short_name">
                            {{ prop.node.short_name }}
                          </q-tooltip>
                        </div>
                        <q-badge
                          v-if="prop.node.status === 0"
                          color="grey"
                          label="禁用"
                          class="q-ml-sm"
                        />
                        <q-badge
                          v-if="prop.node.level"
                          color="secondary"
                          :label="`L${prop.node.level}`"
                          class="q-ml-sm"
                        />
                      </div>
                    </template>
                  </q-tree>
                </q-scroll-area>
              </q-card-section>
            </q-card>
          </div>
        </div>
      </template>

      <!-- 右侧面板 -->
      <template v-slot:after>
        <div class="q-pl-md full-height">
          <q-card v-if="currentOrg" class="full-height">
            <q-card-section>
              <div class="row">
                <div class="text-h6 col">{{ isEditing ? '编辑组织' : '组织详情' }}</div>
                <!-- 操作按钮 -->
                <div align="right" class="q-gutter-sm">
                  <template v-if="!isEditing">
                    <q-btn
                      label="修改"
                      color="primary"
                      @click="startEditing"
                      :disable="!selectedNodeId"
                    />
                    <q-btn
                      label="增加下级"
                      color="positive"
                      @click="addSubOrganization"
                      :disable="!selectedNodeId"
                    />
                  </template>
                  <template v-else>
                    <q-btn
                      label="取消"
                      color="warning"
                      @click="cancelEditing"
                    />
                    <q-btn
                      label="保存"
                      color="primary"
                      type="submit"
                      :loading="saving"
                      @click="saveOrganization"
                    />
                  </template>
                </div>

              </div>
              <q-separator/>
              <div class="text-subtitle2 text-grey">
                {{ currentOrg.path || currentOrg.name }}
              </div>
            </q-card-section>

            <q-separator />

            <q-form
              @submit="saveOrganization"
              ref="orgForm"
              class="full-height"
            >
              <!-- 选项卡 -->
              <q-tabs
                v-model="activeTab"
                align="left"
                class="bg-primary text-white"
                active-color="white"
                indicator-color="yellow"
                inline-label
              >
                <q-tab name="basic" icon="info" label="基础信息" />
                <q-tab name="bank" icon="account_balance" label="银行账户" />
              </q-tabs>

              <q-separator />

              <!-- 选项卡面板 -->
              <q-tab-panels
                v-model="activeTab"
                animated
                class="full-height"
              >
                <!-- 基础信息面板 -->
                <q-tab-panel name="basic" class="q-pa-none">
                  <q-scroll-area style="height: 650px;">
                    <div class="q-pa-md">
                      <div class="row q-col-gutter-md">
                        <div class="col-6">
                          <q-input
                            v-model="currentOrg.name"
                            label="组织名称 *"
                            outlined
                            dense
                            :rules="[val => !!val || '组织名称不能为空']"
                            :readonly="!isEditing"
                            maxlength="50"
                          />
                        </div>
                        <div class="col-6">
                          <q-input
                            v-model="currentOrg.social_credit_code"
                            label="统一社会信用代码 *"
                            outlined
                            dense
                            :rules="[val => !!val || '统一社会信用代码不能为空']"
                            :readonly="!isEditing"
                            maxlength="18"
                          />
                        </div>
                      </div>

                      <div class="row q-col-gutter-md q-mt-md">
                        <div class="col-4">
                          <q-input
                            v-model="currentOrg.short_name"
                            label="组织简称 *"
                            outlined
                            dense
                            :rules="[val => !!val || '组织简称不能为空']"
                            :readonly="!isEditing"
                            maxlength="50"
                          />
                        </div>
                        <div class="col-4">
                          <q-input
                            v-model="currentOrg.legal_representative"
                            label="法人代表 *"
                            outlined
                            dense
                            :rules="[val => !!val || '组织简称不能为空']"
                            :readonly="!isEditing"
                            maxlength="50"
                          />
                        </div>
                        <div class="col-4">
                          <q-select
                            v-model="currentOrg.type"
                            label="组织类型 *"
                            :options="orgTypeOptions"
                            outlined
                            dense
                            emit-value
                            map-options
                            :rules="[val => val !== null && val !== '' || '请选择组织类型']"
                            :readonly="!isEditing"
                          />
                        </div>
                      </div>

                      <!-- 地址信息 -->
                      <div class="text-subtitle1 q-mt-lg q-mb-md">地址信息</div>

                      <div class="row q-col-gutter-md">
                        <AreaSelector
                        v-model="currentOrg"
                        :readonly="!isEditing"
                        @update:model-value="onChangeArea"
                        />
                      </div>

                      <div class="row q-col-gutter-md q-mt-md">
                        <div class="col-12">
                          <q-input
                            v-model="currentOrg.address"
                            label="详细地址"
                            type="textarea"
                            outlined
                            dense
                            autogrow
                            :readonly="!isEditing"
                          />
                        </div>
                      </div>

                      <!-- 联系信息 -->
                      <div class="text-subtitle1 q-mt-lg q-mb-md">联系信息</div>

                      <div class="row q-col-gutter-md">
                        <div class="col-6">
                          <q-input
                            v-model="currentOrg.contact_person"
                            label="联系人"
                            outlined
                            dense
                            :readonly="!isEditing"
                            maxlength="20"
                          />
                        </div>
                        <div class="col-6">
                          <q-input
                            v-model="currentOrg.contact_phone"
                            label="联系电话"
                            outlined
                            dense
                            :readonly="!isEditing"
                            maxlength="11"
                          />
                        </div>
                      </div>

                      <div class="row q-col-gutter-md q-mt-md">
                        <div class="col-12">
                          <q-input
                            v-model="currentOrg.remark"
                            label="备注"
                            type="textarea"
                            outlined
                            dense
                            autogrow
                            :readonly="!isEditing"
                          />
                        </div>
                      </div>

                      <div class="row q-col-gutter-md q-mt-md">
                        <div class="col-12">
                          <q-toggle
                            v-model="currentOrg.status"
                            :true-value="1"
                            :false-value="0"
                            label="是否启用"
                            :disable="!isEditing"
                          />
                        </div>
                      </div>
                    </div>
                  </q-scroll-area>
                </q-tab-panel>

                <!-- 银行账户面板 -->
                <q-tab-panel name="bank" class="q-pa-none">
                  <q-scroll-area style="height: 650px;">
                    <div class="q-pa-md">
                      <div class="text-subtitle1 q-mb-md">银行账户信息</div>

                      <q-card v-if="currentOrg.bankAccounts && currentOrg.bankAccounts.length > 0" class="q-mb-md">
                        <q-list bordered>
                          <q-item
                            v-for="(bank, index) in currentOrg.bankAccounts"
                            :key="index"
                            class="q-py-sm"
                          >
                            <q-item-section>
                              <div class="row items-center">
                                <div class="col-4">
                                  <q-input
                                    v-model="bank.bank_name"
                                    label="银行名称 *"
                                    outlined
                                    dense
                                    :rules="[val => !!val || '银行名称不能为空']"
                                    :readonly="!isEditing"
                                    maxlength="100"
                                  />
                                </div>
                                <div class="col-4">
                                  <q-input
                                    v-model="bank.bank_account"
                                    label="银行账号 *"
                                    outlined
                                    dense
                                    :rules="[val => !!val || '银行账号不能为空']"
                                    :readonly="!isEditing"
                                    maxlength="30"
                                  />
                                </div>
                                <div class="col-3">
                                  <q-input
                                    v-model="bank.account_name"
                                    label="账户名称 *"
                                    outlined
                                    dense
                                    :rules="[val => !!val || '账户名称不能为空']"
                                    :readonly="!isEditing"
                                    maxlength="50"
                                  />
                                </div>
                                <div class="col-1 text-right">
                                  <q-btn
                                    v-if="isEditing"
                                    icon="delete"
                                    color="negative"
                                    flat
                                    dense
                                    round
                                    @click="removeBankAccount(index)"
                                    :disable="currentOrg.bankAccounts.length <= 1"
                                  />
                                </div>
                              </div>
                              <div class="row items-center q-mt-sm">
                                <div class="col-6">
                                  <q-btn
                                    outline
                                    :flat="bank.is_default !== 1"
                                    :color="bank.is_default === 1 ? 'green' : 'grey'"
                                    :icon="bank.is_default === 1 ? 'check_circle' : 'radio_button_unchecked'"
                                    :label="bank.is_default === 1 ? '默认' : '设为默认'"
                                    @click="handleDefaultChange(bank)"
                                    size="sm"
                                    :disable="!isEditing"
                                  />
                                </div>
                                <div class="col-6">
                                  <q-toggle
                                    v-model="bank.status"
                                    :true-value="1"
                                    :false-value="0"
                                    label="启用"
                                    :disable="!isEditing"
                                  />
                                </div>
                              </div>
                              <div class="row q-mt-sm" v-if="bank.remark">
                                <div class="col-12">
                                  <q-input
                                    v-model="bank.remark"
                                    label="备注"
                                    type="textarea"
                                    outlined
                                    dense
                                    autogrow
                                    :readonly="!isEditing"
                                  />
                                </div>
                              </div>
                            </q-item-section>
                          </q-item>
                        </q-list>
                      </q-card>

                      <div v-else class="text-center q-py-lg text-grey">
                        <q-icon name="account_balance" size="xl" />
                        <div class="q-mt-sm">暂无银行账户信息</div>
                      </div>

                      <q-btn
                        v-if="isEditing"
                        icon="add"
                        label="添加银行账户"
                        color="primary"
                        outline
                        @click="addBankAccount"
                        class="full-width q-mt-md"
                      />
                    </div>
                  </q-scroll-area>
                </q-tab-panel>
              </q-tab-panels>


            </q-form>
          </q-card>

          <!-- 无选中组织时的提示 -->
          <q-card v-else class="full-height flex flex-center">
            <q-card-section class="text-center">
              <q-icon name="info" size="xl" color="grey" class="q-mb-md" />
              <div class="text-h6 text-grey">请选择或新增一个组织</div>
              <div class="text-subtitle2 text-grey q-mt-sm">
                在左侧树状结构中点击选择一个组织，或者点击"新增"按钮创建组织
              </div>
            </q-card-section>
          </q-card>
        </div>
      </template>
    </q-splitter>
  </q-page>
  <div>here</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { organizationApi } from 'src/api/organization';

import AreaSelector from 'src/components/AreaSelector.vue';

const $q = useQuasar()


// 分割器模型
const splitterModel = ref(30)

// 搜索文本
const searchText = ref('')

// 选项卡
const activeTab = ref('basic')

// 树状结构相关
const selectedNodeId = ref(null)
const expandedNodes = ref([])
const orgTree = ref([])

// 当前选中的组织
const currentOrg = ref(null)

// 编辑状态
const isEditing = ref(false)

const saving = ref(false)
const orgForm = ref(null)

const cityOptions = ref([])
const districtOptions = ref([])
const streetOptions = ref([])

// 组织类型选项
const orgTypeOptions = ref([
  { label: '甲方', value: 1 },
  { label: '总包', value: 2 },
  { label: '分包', value: 3 },
  { label: '施工队', value: 4 }
])

// 获取组织类型图标
const getOrgTypeIcon = (type) => {
  switch(type) {
    case 1: return 'person'
    case 2: return 'business'
    case 3: return 'groups'
    case 4: return 'engineering'
    default: return 'business'
  }
}

// 初始化省市区数据
const initRegionData = () => {
  // 模拟市数据
  cityOptions.value = [
    { label: '北京市', value: '110100' },
    { label: '上海市', value: '310100' },
    { label: '广州市', value: '440100' },
    { label: '深圳市', value: '440300' },
    { label: '杭州市', value: '330100' }
  ]

  // 模拟区数据
  districtOptions.value = [
    { label: '朝阳区', value: '110105' },
    { label: '海淀区', value: '110108' },
    { label: '浦东新区', value: '310115' },
    { label: '天河区', value: '440106' },
    { label: '南山区', value: '440305' }
  ]

  // 模拟街道数据
  streetOptions.value = [
    { label: '建国门街道', value: '110101001' },
    { label: '中关村街道', value: '110108001' },
    { label: '陆家嘴街道', value: '310115004' },
    { label: '天河南街道', value: '440106001' },
    { label: '粤海街道', value: '440305001' }
  ]
}

// 过滤后的树
const filteredTree = computed(() => {
  if (!searchText.value) return orgTree.value

  const filterNodes = (nodes) => {
    return nodes.filter(node => {
      const matches = node.name.toLowerCase().includes(searchText.value.toLowerCase()) ||
                     node.short_name?.toLowerCase().includes(searchText.value.toLowerCase())

      if (node.children && node.children.length > 0) {
        const filteredChildren = filterNodes(node.children)
        if (filteredChildren.length > 0) {
          node.children = filteredChildren
          return true
        }
      }

      return matches
    })
  }

  return filterNodes([...orgTree.value])
})

// 加载组织架构数据
const loadOrganizations = async () => {
  try {
    const response = await organizationApi.tree();
    orgTree.value = buildTree(response.data);

  } catch (error) {
    console.error('加载组织数据失败:', error)
    $q.notify({
      type: 'negative',
      message: '加载组织数据失败，请检查网络连接'
    })
  }
}

// 构建树形结构
const buildTree = (organizations) => {

  // 将对象转为 JSON 字符串，替换属性名，再转回对象
  var jsonStr = JSON.stringify(organizations);
  jsonStr = jsonStr.replace(/"children_recursive"/g, '"children"').replace(/"bank_accounts"/g,'"bankAccounts"'); // 全局替换 "a" 为 "c"
  return JSON.parse(jsonStr);
}



// 节点选择事件
const onNodeSelected = async (id) => {
  if (!id) {
    currentOrg.value = null
    return
  }

  // 查找选中的组织
  const findOrg = (nodes) => {
    for (const node of nodes) {
      if (node.id === id) {
        return node
      }
      if (node.children && node.children.length > 0) {
        const found = findOrg(node.children)
        if (found) return found
      }
    }
    return null
  }

  const selectedOrg = findOrg(orgTree.value)
  if (selectedOrg) {
    // 创建副本，避免直接修改原始数据
    currentOrg.value = JSON.parse(JSON.stringify(selectedOrg))
    isEditing.value = false

    // 切换到基础信息选项卡
    activeTab.value = 'basic'

  }
}

// 添加根组织
const handleAddRoot = () => {
  selectedNodeId.value = null
  currentOrg.value = {
    id: null,
    parent_id: null,
    level: 1,
    province_code: '',
    city_code: '',
    district_code: '',
    street_code: '',
    name: '',
    short_name: '',
    social_credit_code: '',
    type: 2,
    status: 1,
    address: '',
    contact_person: '',
    contact_phone: '',
    remark: '',
    bankAccounts: [
      // {
      //   bank_name: '',
      //   bank_account: '',
      //   account_name: '',
      //   is_default: 1,
      //   status: 1,
      //   remark: ''
      // }
    ]
  }
  isEditing.value = true
  // 切换到基础信息选项卡
  activeTab.value = 'basic'
}

// 开始编辑
const startEditing = () => {
  isEditing.value = true
}



// 取消编辑
const cancelEditing = () => {
  if (!selectedNodeId.value) {
    currentOrg.value = null
  } else {
    onNodeSelected(selectedNodeId.value)
  }
  isEditing.value = false
}

// 保存组织
const saveOrganization = async () => {
  if (orgForm.value) {
    const valid = await orgForm.value.validate()
    if (!valid) {
      $q.notify({
        type: 'negative',
        message: '请填写必填字段'
      })
      return
    }
  }

  saving.value = true

  try {
    let response

    // 如果是新增组织
    if (!currentOrg.value.id) {
      response = await organizationApi.store(currentOrg.value);

      if (response.data && response.data.success) {
        $q.notify({
          type: 'positive',
          message: '组织添加成功'
        })

        // 重新加载组织数据
        await loadOrganizations()

        // 选中新创建的组织
        const newOrgId = response.data.data.id
        selectedNodeId.value = newOrgId

        // 更新当前组织数据
        currentOrg.value.id = newOrgId
        isEditing.value = false
      } else {
        throw new Error(response.data?.message || '添加组织失败')
      }
    }
    // 如果是编辑现有组织
    else {
      response = await organizationApi.update(currentOrg.value.id, currentOrg.value);

      // 重新加载组织数据
      await loadOrganizations();
      isEditing.value = false;

    }
  } catch (error) {
    console.error('保存组织失败:', error)
    $q.notify({
      type: 'negative',
      message: error.response.data.message || '保存失败，请稍后重试'
    })
  } finally {
    saving.value = false
  }
}


// 添加下级组织
const addSubOrganization = async () => {
  console.log("new")
  if (!selectedNodeId.value) return

  // 查找父组织
  const findParentOrg = (nodes) => {
    for (const node of nodes) {
      if (node.id === selectedNodeId.value) {
        return node
      }
      if (node.children && node.children.length > 0) {
        const found = findParentOrg(node.children)
        if (found) return found
      }
    }
    return null
  }

  const parentOrg = findParentOrg(orgTree.value)
  if (!parentOrg) return

  // 创建新组织
  const newOrg = {
    id: null,
    parent_id: parentOrg.id,
    level: parentOrg.level + 1,
    province_code: parentOrg.province_code,
    city_code: parentOrg.city_code,
    district_code: parentOrg.district_code,
    street_code: parentOrg.street_code,
    name: '',
    short_name: '',
    social_credit_code: '',
    type: 4, // 默认为施工队
    status: 1,
    address: parentOrg.address,
    contact_person: '',
    contact_phone: '',
    remark: '',
    bankAccounts: [
      {
        bank_name: '',
        bank_account: '',
        account_name: '',
        is_default: 1,
        status: 1,
        remark: ''
      }
    ]
  }

  // 选中新组织
  selectedNodeId.value = null
  currentOrg.value = newOrg
  expandedNodes.value.push(parentOrg.id)
  isEditing.value = true

  // 切换到基础信息选项卡
  activeTab.value = 'basic'

  $q.notify({
    type: 'info',
    message: '已添加下级组织，请填写详细信息'
  })
}

// 添加银行账户
const addBankAccount = () => {
  if (currentOrg.value) {
    if (!currentOrg.value.bankAccounts) {
      currentOrg.value.bankAccounts = []
    }

    currentOrg.value.bankAccounts.push({
      bank_name: '',
      bank_account: '',
      account_name: currentOrg.value.name || '',
      is_default: currentOrg.value.bankAccounts.length === 0 ? 1 : 0,
      status: 1,
      remark: ''
    })
  }
}

// 移除银行账户
const removeBankAccount = (index) => {
  if (currentOrg.value && currentOrg.value.bankAccounts && currentOrg.value.bankAccounts.length > 1) {
    currentOrg.value.bankAccounts.splice(index, 1)
  }
}


const onChangeArea =(val)=>{
  currentOrg.value.province_code =val.province;
  currentOrg.value.city_code =val.city;
  currentOrg.value.district_code =val.district;
  currentOrg.value.street_code =val.street;
  console.log(val);
}
const handleDefaultChange = (targetRow) => {
  currentOrg.value.bankAccounts.forEach(row => {
    // 选中的行设为 1，其他全部强制设为 0
    row.is_default = (row === targetRow) ? 1 : 0;
  });
};
// 初始化
onMounted(() => {
  loadOrganizations()
  initRegionData()
})
</script>

<style scoped>
.full-height {
  height: calc(100vh - 100px);
}

.q-tree {
  min-width: 100%;
}

.q-tree >>> .q-tree__node--selected .q-tree__node-header-content {
  background-color: rgba(25, 118, 210, 0.1);
  border-radius: 4px;
}

/* 选项卡样式 */
.q-tab-panels {
  background-color: transparent;
}

/* 选项卡内容区域 */
.q-tab-panel {
  padding: 0 !important;
}

/* 表单输入框在只读状态下的样式 */
.q-field--readonly .q-field__control {
  background-color: #f5f5f5;
}

.q-field--readonly .q-field__native {
  color: #666;
}

/* 保存按钮加载状态 */
.q-btn--loading {
  opacity: 0.7;
}
</style>
