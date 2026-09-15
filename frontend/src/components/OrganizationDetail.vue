<template>
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
                @click="addSubOrganization(currentOrg.id)"
                :disable="!selectedNodeId"
              />
              <q-btn label="增加顶级机构" @click="addSubOrganization('')" ></q-btn>
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
                      v-model="currentOrg.short_name"
                      label="组织简称 *"
                      outlined
                      dense
                      :rules="[val => !!val || '组织简称不能为空']"
                      :readonly="!isEditing"
                      maxlength="50"
                    />
                  </div>
                </div>

                <div class="row q-col-gutter-md q-mt-md">
                  <div class="col-6">
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

                <!-- 地址信息 -->
                <div class="text-subtitle1 q-mt-lg q-mb-md">地址信息</div>

                <div class="row q-col-gutter-md">
                  <div class="col-3">
                    <q-select
                      v-model="currentOrg.province_code"
                      label="省"
                      :options="provinceOptions"
                      outlined
                      dense
                      emit-value
                      map-options
                      @update:model-value="onProvinceChange"
                      :readonly="!isEditing"
                      clearable
                    />
                  </div>
                  <div class="col-3">
                    <q-select
                      v-model="currentOrg.city_code"
                      label="市"
                      :options="cityOptions"
                      outlined
                      dense
                      emit-value
                      map-options
                      @update:model-value="onCityChange"
                      :readonly="!isEditing"
                      clearable
                    />
                  </div>
                  <div class="col-3">
                    <q-select
                      v-model="currentOrg.district_code"
                      label="区/县"
                      :options="districtOptions"
                      outlined
                      dense
                      emit-value
                      map-options
                      :readonly="!isEditing"
                      clearable
                    />
                  </div>
                  <div class="col-3">
                    <q-select
                      v-model="currentOrg.street_code"
                      label="街道"
                      :options="streetOptions"
                      outlined
                      dense
                      emit-value
                      map-options
                      :readonly="!isEditing"
                      clearable
                    />
                  </div>
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

                <q-card v-if="currentOrg.bank_accounts && currentOrg.bank_accounts.length > 0" class="q-mb-md">
                  <q-list bordered>
                    <q-item
                      v-for="(bank, index) in currentOrg.bank_accounts"
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
                              :disable="currentOrg.bank_accounts.length <= 1"
                            />
                          </div>
                        </div>
                        <div class="row items-center q-mt-sm">
                          <div class="col-6">
                            <q-toggle
                              v-model="bank.is_default"
                              :true-value="1"
                              :false-value="0"
                              label="默认账户"
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
        <div class="text-h6 text-grey">请选择或新增一个组织{{ isEditing }}</div>
        <div class="text-subtitle2 text-grey q-mt-sm">
          在左侧树状结构中点击选择一个组织，或者点击"新增"按钮创建组织
        </div>
      </q-card-section>
    </q-card>
  </div>
</template>

<script setup>
import { ref,  onMounted,watch } from 'vue'
import { useQuasar } from 'quasar'
import { organizationApi } from 'src/api/organization';

const isEditing = ref(false)
const $q = useQuasar()
const selectedNodeId = ref(null)

const props = defineProps({
  initialData: Number,
  isNew: Boolean
});
// 当前选中的组织
const currentOrg = ref(null)
//加载详情
const loadDetails=async (id)=>{
  const response =await organizationApi.show(id);
  currentOrg.value =response.data.data;
  selectedNodeId.value =true;
  isEditing.value =false;
}

// 当外部传入的数据改变时（切换了列表行），重置表单
watch(() => props.initialData, (newVal) => {
console.log("watch",newVal)
  if(newVal!==0){//有ID，编辑
    loadDetails(newVal);
  }
}, { deep: true ,immediate: true });



// 选项卡
const activeTab = ref('basic')


// 编辑状态
const saving = ref(false)
const orgForm = ref(null)

// 省市区街道数据
const provinceOptions = ref([
  { label: '北京市', value: '110000' },
  { label: '上海市', value: '310000' },
  { label: '广东省', value: '440000' },
  { label: '江苏省', value: '320000' },
  { label: '浙江省', value: '330000' }
])

const cityOptions = ref([])
const districtOptions = ref([])
const streetOptions = ref([])

// 组织类型选项
const orgTypeOptions = ref([
  { label: '甲方', value: 1 },
  { label: '总公司', value: 2 },
  { label: '分公司', value: 3 },
  { label: '合作公司', value: 4 },
  { label: '施工队', value: 5 },
])


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

// 开始编辑
const startEditing = () => {
  isEditing.value = true
}



// 取消编辑
const cancelEditing = () => {
  if (!selectedNodeId.value) {
    currentOrg.value = null
  } else {
    // onNodeSelected(selectedNodeId.value)
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
      response = await organizationApi.store(currentOrg.value)

      if (response.data && response.data.success) {
        $q.notify({
          type: 'positive',
          message: '组织添加成功'
        })

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
      response = await organizationApi.update(currentOrg.value.id, currentOrg.value)

      if (response.data && response.data.success) {
        $q.notify({
          type: 'positive',
          message: '组织信息已保存'
        })

        // 重新加载组织数据
        isEditing.value = false
      } else {
        throw new Error(response.data?.message || '保存组织信息失败')
      }
    }
  } catch (error) {
    console.error('保存组织失败:', error)
    $q.notify({
      type: 'negative',
      message: error.message || '保存失败，请稍后重试'
    })
  } finally {
    saving.value = false
  }
}


// 添加下级组织
const addSubOrganization = async (parent_id) => {

  if (!selectedNodeId.value) return
  let level =0;//顶级
  let messageInfo ='已添加顶级机构，请填写详细信息'
  if(parent_id!=''){//增加子单位
    level =currentOrg.value.level +1;
    messageInfo ='已添加下级机构，请填写详细信息';
  }
  // 创建新组织
  const newOrg = {
    id: null,
    parent_id: parent_id,//父ID
    level: level,
    province_code: '',
    city_code: '',
    district_code: '',
    street_code: '',
    name: '',
    short_name: '',
    social_credit_code: '',
    type: 4, // 默认为施工队
    status: 1,
    address: '',
    contact_person: '',
    contact_phone: '',
    remark: '',
    bank_accounts: [
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
  isEditing.value = true

  // 切换到基础信息选项卡
  activeTab.value = 'basic'

  $q.notify({
    type: 'info',
    message: messageInfo,
  })
}

// 添加银行账户
const addBankAccount = () => {
  if (currentOrg.value) {
    if (!currentOrg.value.bank_accounts) {
      currentOrg.value.bank_accounts = []
    }

    currentOrg.value.bank_accounts.push({
      bank_name: '',
      bank_account: '',
      account_name: currentOrg.value.name || '',
      is_default: currentOrg.value.bank_accounts.length === 0 ? 1 : 0,
      status: 1,
      remark: ''
    })
  }
}

// 移除银行账户
const removeBankAccount = (index) => {
  if (currentOrg.value && currentOrg.value.bank_accounts && currentOrg.value.bank_accounts.length > 1) {
    currentOrg.value.bank_accounts.splice(index, 1)
  }
}

// 省市区变更处理
const onProvinceChange = () => {
  if (currentOrg.value) {
    currentOrg.value.city_code = ''
    currentOrg.value.district_code = ''
    currentOrg.value.street_code = ''
  }
}

const onCityChange = () => {
  if (currentOrg.value) {
    currentOrg.value.district_code = ''
    currentOrg.value.street_code = ''
  }
}

// 初始化
onMounted(() => {
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
