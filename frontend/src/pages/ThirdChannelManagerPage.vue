<template>
  <q-page class="q-pa-md">
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row items-center q-gutter-sm">
        <q-input v-model="filter.name" label="渠道名称" dense outlined clearable style="width: 200px" />
        <q-select v-model="filter.status" :options="statusOptions" label="状态" dense outlined emit-value map-options style="width: 150px" />
        <q-btn color="primary" icon="search" label="搜索" @click="getList" />
        <q-space />
        <q-btn color="primary" icon="add" label="新增渠道" @click="openDialog()" />
      </q-card-section>
    </q-card>

    <q-table
      :rows="rows"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      :pagination="pagination"
    >
      <template v-slot:body-cell-is_ip_restricted="props">
        <q-td :props="props">
          <q-chip :color="props.value === true ? 'positive' : 'negative'" text-color="white" dense>
            {{ props.value === true ? '启用' : '禁用' }}
          </q-chip>
        </q-td>
      </template>
      <template v-slot:body-cell-status="props">
        <q-td :props="props">
          <q-chip :color="props.value === 1 ? 'positive' : props.value === 0?'negative':'warning'" text-color="white" dense>
            {{ props.value === 1 ? '启用' : props.value === 0?'禁用':'测试' }}
          </q-chip>
        </q-td>
      </template>

      <template v-slot:body-cell-role="props">
        <q-td :props="props">
          <q-chip :color="roleColorOf(props.value)" text-color="white" dense>
            {{ roleLabelOf(props.value) }}
          </q-chip>
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props" class="q-gutter-xs">
          <q-btn flat round color="blue" icon="settings" @click="manageProducts(props.row)">
            <q-tooltip>产品配置</q-tooltip>
          </q-btn>
          <q-btn flat round color="primary" icon="edit" @click="openDialog(props.row)">
            <q-tooltip>编辑</q-tooltip>
          </q-btn>
          <q-btn flat round color="primary" icon="content_copy" @click="copyContent(props.row)">
            <q-tooltip>复制渠道和产品信息</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- 渠道编辑弹窗 -->
    <q-dialog v-model="dialog.show" persistent maximized>
      <q-card class="column" style="min-width: 700px; max-width: 900px;">
        <q-card-section class="row items-center bg-primary text-white">
          <div class="text-h6">{{ dialog.isEdit ? '编辑渠道' : '新增渠道' }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section class="col q-pa-none">
          <q-tabs v-model="dialog.tab" class="bg-grey-2" align="left" dense>
            <q-tab name="basic" label="基本配置" />
            <q-tab name="receive" label="接收配置" />
            <q-tab name="push" label="推送配置" />
            <q-tab name="stop" label="停止条件配置" />
          </q-tabs>
          <q-separator />

          <q-tab-panels v-model="dialog.tab" animated class="col">
            <!-- ===== 基本配置 ===== -->
            <q-tab-panel name="basic">
              <q-form @submit="saveChannel" class="q-gutter-md">
                <q-input v-model="form.name" label="渠道名称 *" dense outlined :rules="[val => !!val || '必填']" />
                <q-input v-model="form.pid" label="PID *" dense outlined :disable="dialog.isEdit" :rules="[val => !!val || '必填']" />
                <q-input v-model="form.key" label="签名Key *" dense outlined :rules="[val => !!val || '必填']" />

                <q-toggle v-model="form.is_ip_restricted" label="强制使用IP白名单" />
                <template v-if="form.is_ip_restricted">
                  <q-input v-model="form.ip_whitelist" type="textarea" label="IP白名单" dense outlined rows="2" />
                </template>

                <organization-tree-select v-model="form.organization_id" label="归属公司" />
                <q-select v-model="form.role"
                  :options="roleOptions"
                  label="身份角色 *" dense outlined emit-value map-options
                  :rules="[val => !!val || '必填']" />
                <q-input v-model="form.remark" type="textarea" label="备注" dense outlined rows="2" />

                <q-radio v-model="form.status" :val="0" label="禁用" />
                <q-radio v-model="form.status" :val="2" label="测试" />
                <q-radio v-model="form.status" :val="1" label="启用" />

                <div class="row justify-end q-mt-md">
                  <q-btn label="取消" flat v-close-popup />
                  <q-btn label="提交" type="submit" color="primary" :loading="submitting" @click="saveChannel" />
                </div>
              </q-form>
            </q-tab-panel>

            <!-- ===== 推送配置 ===== -->
            <q-tab-panel name="push">
              <div class="q-gutter-md">
                <div class="text-subtitle2 text-grey-8 q-mb-sm">推送目标地址配置</div>
                <q-input v-model="form.push_base_url" label="推送基础URL" dense outlined placeholder="如: http://api.other.com" />
                <q-input v-model="form.push_endpoint" label="推送接口路径" dense outlined placeholder="如: /api_v2/ThirdChannel/syncUserData" />
                <q-input v-model="form.push_timeout" label="推送超时(秒)" dense outlined type="number" :min="1" :max="300" />

                <q-separator class="q-my-md" />
                <div class="text-subtitle2 text-grey-8 q-mb-sm">结果通知地址</div>
                <q-input v-model="form.callback_url" label="通知地址" dense outlined
                  placeholder="如: http://promoter.com/order-notify" />
                <div class="text-caption text-grey-6 q-mt-xs">
                  B系统处理完订单后，将结果推送至此地址通知对方渠道。
                </div>

                <q-separator class="q-my-md" />
                <div class="text-subtitle2 text-grey-8 q-mb-sm">推送成功/失败判断规则</div>
                <div class="text-caption text-grey-6 q-mb-sm">
                  配置对方API返回数据中哪些字段表示成功/失败。未配置时默认使用 code=0 为成功。
                </div>
                <div class="row q-col-gutter-md">
                  <div class="col-6">
                    <q-input v-model="pushSuccessRuleText" label="成功判断规则(JSON)" dense outlined type="textarea" rows="3"
                      placeholder='{"field":"code","value":0,"operator":"eq"}'
                      :error="pushSuccessRuleError" :error-message="pushSuccessRuleErrorMsg" />
                  </div>
                  <div class="col-6">
                    <q-input v-model="pushFailRuleText" label="失败判断规则(JSON)" dense outlined type="textarea" rows="3"
                      placeholder='{"field":"code","value":1,"operator":"eq"}'
                      :error="pushFailRuleError" :error-message="pushFailRuleErrorMsg" />
                  </div>
                </div>
                <div class="text-caption text-grey-6">
                  支持的运算符: eq(等于), neq(不等于), gt(大于), gte(大于等于), lt(小于), lte(小于等于), in(在列表中), contains(包含), regex(正则)
                </div>

                <q-separator class="q-my-md" />
                <div class="text-subtitle2 text-grey-8 q-mb-sm">扩展配置 (JSON)</div>
                <q-input v-model="extConfigText" label="ext_config" type="textarea" dense outlined rows="5"
                  placeholder='如: {"rate_limit": 100, "push_conditions": {"order_status": [1,2]}}'
                  :error="extConfigError" :error-message="extConfigErrorMsg" />

                <q-separator class="q-my-md" />
                <div class="text-subtitle2 text-grey-8 q-mb-sm">多步骤推送配置</div>
                <div class="text-caption text-grey-6 q-mb-sm">
                  若对方系统需要分步调用（如先获取验证码再提交订单），可在此配置多步骤流程。
                  启用后，系统将按顺序依次执行每个步骤，上一步的输出可映射到下一步的请求参数。
                </div>
                <q-toggle v-model="useMultiStep" label="启用多步骤推送" class="q-mb-md" />

                <template v-if="useMultiStep">
                  <q-btn flat color="primary" icon="add" label="添加步骤" size="sm" @click="addPushStep" class="q-mb-sm" />
                  <div v-for="(step, si) in pushSteps" :key="si" class="q-mb-md">
                    <q-card flat bordered>
                      <q-card-section class="row items-center q-py-sm bg-grey-1">
                        <q-icon name="repeat" color="primary" size="sm" />
                        <span class="q-ml-sm text-weight-medium">步骤 {{ si + 1 }}: {{ step.name || '未命名' }}</span>
                        <q-space />
                        <q-btn flat round dense icon="delete" color="negative" size="sm" @click="removePushStep(si)" />
                      </q-card-section>
                      <q-card-section class="q-pt-sm">
                        <div class="row q-col-gutter-sm">
                          <div class="col-4">
                            <q-input v-model="step.name" label="步骤名称" dense outlined placeholder="如: 获取验证码" />
                          </div>
                          <div class="col-4">
                            <q-input v-model="step.endpoint" label="接口路径" dense outlined placeholder="如: /api/getCode" />
                          </div>
                          <div class="col-2">
                            <q-select v-model="step.method" :options="['POST','GET','PUT']" label="方法" dense outlined />
                          </div>
                          <div class="col-2">
                            <q-input v-model="step.timeout" label="超时(秒)" dense outlined type="number" :min="1" :max="300" />
                          </div>
                        </div>
                        <!-- 请求参数映射 → 可视化编辑器 -->
                        <div class="row q-mt-sm">
                          <div class="col-12">
                            <div class="text-caption text-weight-medium q-mb-xs">请求参数映射（API参数名 → 来源字段/固定值）</div>
                            <div v-for="(item, ri) in step.requestMappingItems" :key="ri" class="row q-col-gutter-xs q-mb-xs items-center">
                              <div class="col-5">
                                <q-input v-model="item.key" dense outlined placeholder="API参数名" />
                              </div>
                              <div class="col-1 text-center text-grey-5" style="font-size: 18px;">→</div>
                              <div class="col-5">
                                <q-input v-model="item.value" dense outlined placeholder="订单字段名或固定值" />
                              </div>
                              <div class="col-1">
                                <q-btn flat round dense icon="delete" size="sm" color="negative" @click="removeRequestMappingItem(si, ri)" />
                              </div>
                            </div>
                            <q-btn flat dense icon="add" size="sm" color="primary" label="添加映射" @click="addRequestMappingItem(si)" />
                          </div>
                        </div>

                        <!-- 输出映射 → 可视化编辑器 -->
                        <div class="row q-mt-sm">
                          <div class="col-12">
                            <div class="text-caption text-weight-medium q-mb-xs">输出映射（键名 → 响应JSON路径）</div>
                            <div v-for="(item, oi) in step.outputMappingItems" :key="oi" class="row q-col-gutter-xs q-mb-xs items-center">
                              <div class="col-5">
                                <q-input v-model="item.key" dense outlined placeholder="键名(供下一步引用)" />
                              </div>
                              <div class="col-1 text-center text-grey-5" style="font-size: 18px;">→</div>
                              <div class="col-5">
                                <q-input v-model="item.value" dense outlined placeholder="如: data.linkId" />
                              </div>
                              <div class="col-1">
                                <q-btn flat round dense icon="delete" size="sm" color="negative" @click="removeOutputMappingItem(si, oi)" />
                              </div>
                            </div>
                            <q-btn flat dense icon="add" size="sm" color="primary" label="添加映射" @click="addOutputMappingItem(si)" />
                          </div>
                        </div>

                        <!-- 成功/失败规则 → 可视化编辑器 -->
                        <div class="row q-col-gutter-sm q-mt-sm">
                          <div class="col-6">
                            <div class="text-caption text-weight-medium q-mb-xs">成功判断规则</div>
                            <div class="row q-col-gutter-xs items-center">
                              <div class="col-4">
                                <q-input v-model="step.successRule.field" dense outlined placeholder="字段路径" />
                              </div>
                              <div class="col-3">
                                <q-select v-model="step.successRule.operator" :options="ruleOperators" dense outlined />
                              </div>
                              <div class="col-5">
                                <q-input v-model="step.successRule.value" dense outlined placeholder="预期值" />
                              </div>
                            </div>
                          </div>
                          <div class="col-6">
                            <div class="text-caption text-weight-medium q-mb-xs">失败判断规则</div>
                            <div class="row q-col-gutter-xs items-center">
                              <div class="col-4">
                                <q-input v-model="step.failRule.field" dense outlined placeholder="字段路径" />
                              </div>
                              <div class="col-3">
                                <q-select v-model="step.failRule.operator" :options="ruleOperators" dense outlined />
                              </div>
                              <div class="col-5">
                                <q-input v-model="step.failRule.value" dense outlined placeholder="预期值" />
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="text-caption text-grey-6 q-mt-xs">
                          来源字段支持: 订单字段名(如 mobile, total_amount)、固定值(如 gxhjy004)、上一步输出引用(如 {step0.linkId})
                        </div>
                      </q-card-section>
                    </q-card>
                  </div>
                </template>

                <q-separator class="q-my-md" />
                <div class="text-subtitle2 text-grey-8 q-mb-sm">定时回调推送</div>
                <div class="text-caption text-grey-6 q-mb-sm">
                  系统每隔 5 分钟自动扫描待回调订单，在设定的时间段内推送。
                </div>
                <q-toggle v-model="form.auto_callback_enabled" label="启用定时回调推送" class="q-mb-md" />
                <template v-if="form.auto_callback_enabled">
                  <div class="row q-col-gutter-md">
                    <div class="col-6">
                      <q-input v-model="form.auto_callback_time_start" label="开始时间" dense outlined
                        mask="##:##" placeholder="08:00" hint="格式: HH:MM" />
                    </div>
                    <div class="col-6">
                      <q-input v-model="form.auto_callback_time_end" label="结束时间" dense outlined
                        mask="##:##" placeholder="22:00" hint="格式: HH:MM" />
                    </div>
                  </div>
                  <div class="text-caption text-grey-6 q-mt-sm">
                    留空时间范围表示全天执行。时间基于服务器时区。
                  </div>
                </template>

                <q-separator class="q-my-md" />
                <div class="text-subtitle2 text-grey-8 q-mb-sm">推送签名配置</div>
                <q-select v-model="form.sign_algorithm" :options="signAlgorithms" label="签名算法" dense outlined emit-value map-options />
                <q-input v-model="form.sign_key" label="推送签名密钥" dense outlined
                  placeholder="与接收key不同时可单独设置，留空则使用签名Key" />
                <q-input v-model="form.service_class" label="渠道Service类名" dense outlined
                  placeholder="如: App\Services\ThirdParty\SomeService" />
                <div class="text-caption text-grey-6 q-mt-sm">
                  * 签名算法支持: SHA256, MD5, HMAC-SHA256
                </div>

                <div class="row justify-end q-mt-md">
                  <q-btn label="取消" flat v-close-popup />
                  <q-btn label="保存" color="primary" :loading="submitting" @click="saveChannel" />
                </div>
              </div>
            </q-tab-panel>

            <!-- ===== 接收配置（对方→B） ===== -->
            <q-tab-panel name="receive">
              <div class="q-gutter-md">
                <div class="text-subtitle2 text-grey-8 q-mb-sm">接收接口设置</div>
                <q-select v-model="form.receive_auth_mode" :options="receiveAuthModes" label="接收认证方式" dense outlined
                  class="q-mt-md" emit-value map-options />
                <div class="text-caption text-grey-6 q-mt-xs">
                  对方调用时使用的认证方式: signature(签名认证)、header(请求头认证)、none(无认证)
                </div>

                <q-separator class="q-my-md" />
                <div class="text-subtitle2 text-grey-8 q-mb-sm">接收结果字段映射</div>
                <q-toggle v-model="callbackConfig.enabled" label="启用接收字段映射" left-label
                  hint="开启后，将对方回调数据翻译为系统内部字段" />
                <template v-if="callbackConfig.enabled">
                  <q-separator class="q-my-md" />
                  <div class="text-subtitle2 text-grey-8 q-mb-sm">字段映射</div>
                  <div class="text-caption text-grey-6 q-mb-sm">
                    定义外部字段名到系统内部字段的对应关系
                  </div>
                  <q-list bordered separator>
                    <q-item v-for="(item, index) in callbackConfig.field_mapping" :key="index" dense>
                      <q-item-section>
                        <q-input v-model="item.internal" label="内部字段" dense outlined class="q-mb-xs" />
                      </q-item-section>
                      <q-item-section class="q-mx-sm">
                        <q-input v-model="item.external" label="外部字段名" dense outlined class="q-mb-xs" />
                      </q-item-section>
                      <q-item-section side>
                        <q-btn flat round dense color="negative" icon="delete" @click="removeCallbackFieldMapping(index)" />
                      </q-item-section>
                    </q-item>
                  </q-list>
                  <q-btn flat dense color="primary" icon="add" label="添加映射" @click="addCallbackFieldMapping" class="q-mt-sm" />

                  <q-separator class="q-my-md" />
                  <div class="text-subtitle2 text-grey-8 q-mb-sm">成功判定规则</div>
                  <div class="text-caption text-grey-6 q-mb-sm">
                    根据回调参数判定本次推送是否成功
                  </div>
                  <div class="row q-col-gutter-md">
                    <div class="col-4">
                      <q-input v-model="callbackConfig.success_rule.field" label="判定字段" dense outlined
                        placeholder="如: resCode" />
                    </div>
                    <div class="col-4">
                      <q-select v-model="callbackConfig.success_rule.operator" :options="ruleOperatorOptions" label="运算符" dense outlined emit-value map-options />
                    </div>
                    <div class="col-4">
                      <q-input v-model="callbackConfig.success_rule.value" label="期望值" dense outlined
                        placeholder="如: 00000" />
                    </div>
                  </div>
                </template>

                <q-separator class="q-my-md" />
                <!-- 入站字段映射（新） -->
                <ReceiveMappingEditor v-model="receiveMappingUI" />

                <div class="row justify-end q-mt-md">
                  <q-btn label="取消" flat v-close-popup />
                  <q-btn label="保存" color="primary" :loading="submitting" @click="saveChannel" />
                </div>
              </div>
            </q-tab-panel>

            <!-- ===== 停止条件配置 ===== -->
            <q-tab-panel name="stop">
              <div class="q-gutter-md">
                <StopRuleEditor v-model="stopRulesUI" :products="editProducts" :key="dialog.isEdit ? form.id : 'new'" />
                <div class="row justify-end q-mt-md">
                  <q-btn label="取消" flat v-close-popup />
                  <q-btn label="保存" color="primary" :loading="submitting" @click="saveChannel" />
                </div>
              </div>
            </q-tab-panel>

          </q-tab-panels>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- 产品配置弹窗 -->
    <q-dialog v-model="productDialog.show" persistent>
      <q-card style="min-width: 800px; max-width: 90vw;">
        <q-card-section class="bg-primary text-white row items-center">
          <div class="text-h6">配置渠道产品: {{ productDialog.channelName }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section class="row q-col-gutter-md items-end bg-grey-1">
          <div class="col-4">
            <q-select
              v-model="selector.businessId"
              :options="businessOptions"
              label="1. 选择业务"
              option-label="name"
              option-value="id"
              emit-value
              map-options
              dense
              outlined
              @update:model-value="loadProductsByBusiness"
            />
          </div>
          <div class="col-4">
            <q-select
              v-model="selector.productId"
              :options="productOptions"
              label="2. 选择产品"
              option-label="name"
              option-value="id"
              emit-value
              map-options
              dense
              outlined
              :disable="!selector.businessId"
            />
          </div>
          <div class="col-4">
            <q-btn
              color="secondary"
              icon="add"
              label="添加至配置列表"
              class="full-width"
              :disable="!selector.productId"
              @click="addProductToConfig"
            />
          </div>
        </q-card-section>

        <q-separator />

        <q-card-section style="max-height: 400px" class="scroll q-pa-none">
          <q-table
            :rows="productDialog.items"
            :columns="configColumns"
            row-key="product_id"
            flat
            square
            dense
            :pagination="{ rowsPerPage: 0 }"
          >
            <template v-slot:body-cell-product_name="props">
              <q-td :props="props">
                {{props.row.product_name}}
              </q-td>
            </template>

            <template v-slot:body-cell-status="props">
              <q-td :props="props">
                <q-toggle v-model="props.row.status" :true-value="1" :false-value="0" dense />
              </q-td>
            </template>

            <template v-slot:body-cell-remark="props">
              <q-td :props="props">
                <q-input v-model="props.row.remark" dense borderless placeholder="点击输入备注" />
              </q-td>
            </template>
          </q-table>

          <div v-if="productDialog.items.length === 0" class="text-center q-pa-xl text-grey-6">
            暂未配置产品，请从上方选择添加
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="取消" v-close-popup />
          <q-btn color="primary" label="确认并保存配置" icon="save" @click="saveProductConfig" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useQuasar, copyToClipboard } from 'quasar'
import { channelApi, productApi, businessApi } from 'src/api/modules'
import OrganizationTreeSelect from 'components/OrganizationTreeSelect.vue'
import ReceiveMappingEditor from 'components/Channel/ReceiveMappingEditor.vue'
import StopRuleEditor from 'components/Channel/StopRuleEditor.vue'
import {
  createReceiveMappingState,
  loadReceiveMapping,
  extractReceiveMapping,
  validateReceiveMapping,
} from 'src/composables/useReceiveMapping'

const $q = useQuasar()

// --- 状态定义 ---
const loading = ref(false)
const submitting = ref(false)
const rows = ref([])
const filter = reactive({ name: '', status: null })
const statusOptions = [
  { label: '全部', value: null },
  { label: '禁用', value: 0 },
  { label: '测试', value: 2 },
  { label: '启用', value: 1 },
]

// 渠道身份角色选项：与后端 third_channels.role 字段对齐（supplier_a / channel_c / unset）
const roleOptions = [
  { label: 'A 上游供应商', value: 'supplier_a' },
  { label: 'C 下游推广', value: 'channel_c' },
  { label: '未分类', value: 'unset' },
]
// 未知/空值按 unset 兜底展示
const roleLabelOf = (v) => (v === 'supplier_a' ? 'A 上游供应商' : v === 'channel_c' ? 'C 下游推广' : '未分类')
const roleColorOf = (v) => (v === 'supplier_a' ? 'primary' : v === 'channel_c' ? 'secondary' : 'grey')

// 签名算法选项
const signAlgorithms = [
  { label: 'SHA256', value: 'sha256' },
  { label: 'MD5', value: 'md5' },
  { label: 'HMAC-SHA256', value: 'hmac_sha256' },
]

// 接收认证方式选项
const receiveAuthModes = [
  { label: '签名认证(signature)', value: 'signature' },
  { label: '请求头认证(header)', value: 'header' },
  { label: '无认证(none)', value: 'none' },
]

// 表格列定义
const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left' },
  { name: 'name', label: '渠道名称', field: 'name', align: 'left' },
  { name: 'pid', label: 'PID', field: 'pid', align: 'left' },
  { name: 'role', label: '身份角色', field: 'role', align: 'center' },
  { name: 'is_ip_restricted', label: '强制IP白名单', field: 'is_ip_restricted', align: 'center' },
  { name: 'status', label: '状态', field: 'status', align: 'center' },
  { name: 'remark', label: '备注', field: 'remark', align: 'left' },
  { name: 'actions', label: '操作', field: 'actions', align: 'center' }
]

const businessOptions = ref([])
const productOptions = ref([])
const allProductNames = ref({})

const selector = reactive({
  businessId: null,
  productId: null
})

const configColumns = [
  { name: 'product_id', label: '产品ID', field: 'product_id', align: 'left' },
  { name: 'bus_code', label: '业务标识', field: row => row.bus_code, align: 'left' },
  { name: 'sku_code', label: '产品标识', field: row => row.sku_code, align: 'left' },
  { name: 'product_name', label: '产品名称', align: 'left' },
  { name: 'remark', label: '备注', field: 'remark', align: 'left' },
  { name: 'status', label: '状态', field: 'status', align: 'center' },
]

// 渠道表单
const dialog = reactive({ show: false, isEdit: false, tab: 'basic' })

const form = reactive({
  id: null,
  name: '',
  pid: '',
  key: '',
  organization_id: null,
  role: 'unset',
  status: 1,
  remark: '',
  ip_whitelist: '',
  is_ip_restricted: false,
  // 推送配置
  push_base_url: '',
  push_endpoint: '',
  push_timeout: 10,
  // 签名配置
  sign_algorithm: 'sha256',
  sign_key: '',
  // 回调配置
  callback_url: '',
  // 接收认证方式
  receive_auth_mode: 'signature',
  // 向下家推送信息配置
  push_notify_url: '',
  push_max_retries: 3,
  push_retry_delay: 10,
  // 定时回调推送
  auto_callback_enabled: false,
  auto_callback_time_start: '',
  auto_callback_time_end: '',
  // 服务映射
  service_class: '',
  // 扩展配置
  ext_config: null,
  // 推送成功/失败判断规则
  push_success_rule: null,
  push_fail_rule: null,
  // 入站映射配置
  receive_mapping: null,
})

// 回调接收配置
const callbackConfig = reactive({
  enabled: false,
  type: 'province_order_result',
  field_mapping: [],
  success_rule: { field: '', operator: 'eq', value: '' },
})

// 入站字段映射 UI 状态（order/verify 分段）
const receiveMappingUI = createReceiveMappingState()

// 停止条件配置 UI 状态
const stopRulesUI = ref([])
// 编辑渠道时的产品选项（用于停止条件按产品配置）
const editProducts = ref([])

// 规则运算符选项（接收配置专用）
const ruleOperatorOptions = [
  { label: '等于(eq)', value: 'eq' },
  { label: '不等于(neq)', value: 'neq' },
]

// 字段映射操作
const addCallbackFieldMapping = () => {
  callbackConfig.field_mapping.push({ internal: '', external: '' })
}
const removeCallbackFieldMapping = (index) => {
  callbackConfig.field_mapping.splice(index, 1)
}

// ext_config 文本编辑
const extConfigText = ref('')
const extConfigError = ref(false)
const extConfigErrorMsg = ref('')

// push_success_rule / push_fail_rule 文本编辑
const pushSuccessRuleText = ref('')
const pushSuccessRuleError = ref(false)
const pushSuccessRuleErrorMsg = ref('')
const pushFailRuleText = ref('')
const pushFailRuleError = ref(false)
const pushFailRuleErrorMsg = ref('')

// 产品配置表单
const productDialog = reactive({
  show: false,
  channelId: null,
  channelName: '',
  items: []
})

// --- 多步骤推送状态 ---
const useMultiStep = ref(false)
const pushSteps = ref([])

// 规则运算符选项
const ruleOperators = [
  { label: '等于(eq)', value: 'eq' },
  { label: '不等于(neq)', value: 'neq' },
  { label: '大于(gt)', value: 'gt' },
  { label: '大于等于(gte)', value: 'gte' },
  { label: '小于(lt)', value: 'lt' },
  { label: '小于等于(lte)', value: 'lte' },
  { label: '包含(contains)', value: 'contains' },
  { label: '正则(regex)', value: 'regex' },
]

/**
 * 添加一个空步骤
 */
const addPushStep = () => {
  pushSteps.value.push({
    name: '',
    endpoint: '',
    method: 'POST',
    timeout: 10,
    requestMappingItems: [],
    outputMappingItems: [],
    successRule: { field: 'state', operator: 'eq', value: 'success' },
    failRule: { field: 'state', operator: 'neq', value: 'success' },
  })
}

/**
 * 删除指定步骤
 */
const removePushStep = (index) => {
  pushSteps.value.splice(index, 1)
}

/**
 * 为指定步骤添加一行请求参数映射
 */
const addRequestMappingItem = (stepIndex) => {
  pushSteps.value[stepIndex].requestMappingItems.push({ key: '', value: '' })
}

/**
 * 删除指定步骤的某行请求参数映射
 */
const removeRequestMappingItem = (stepIndex, itemIndex) => {
  pushSteps.value[stepIndex].requestMappingItems.splice(itemIndex, 1)
}

/**
 * 为指定步骤添加一行输出映射
 */
const addOutputMappingItem = (stepIndex) => {
  pushSteps.value[stepIndex].outputMappingItems.push({ key: '', value: '' })
}

/**
 * 删除指定步骤的某行输出映射
 */
const removeOutputMappingItem = (stepIndex, itemIndex) => {
  pushSteps.value[stepIndex].outputMappingItems.splice(itemIndex, 1)
}

/**
 * 将 JSON 对象形式的 request_mapping 转为可视化数组格式
 * 如: {mobile:"mobile", cpid:"gxhjy004"} → [{key:"mobile", value:"mobile"}, {key:"cpid", value:"gxhjy004"}]
 */
const mappingToItems = (mapping) => {
  if (!mapping) return []
  return Object.entries(mapping).map(([k, v]) => ({ key: k, value: String(v) }))
}

/**
 * 将可视化数组格式的 request_mapping 转回 JSON 对象
 */
const itemsToMapping = (items) => {
  if (!items || items.length === 0) return null
  const obj = {}
  items.forEach(item => {
    if (item.key.trim()) {
      obj[item.key.trim()] = item.value
    }
  })
  return Object.keys(obj).length > 0 ? obj : null
}

/**
 * 将 push_steps 数组加载到前端表单（JSON → 可视化格式）
 */
const loadPushSteps = (steps) => {
  if (steps && Array.isArray(steps) && steps.length > 0) {
    useMultiStep.value = true
    pushSteps.value = steps.map(s => ({
      name: s.name || '',
      endpoint: s.endpoint || '',
      method: s.method || 'POST',
      timeout: s.timeout ?? 10,
      requestMappingItems: mappingToItems(s.request_mapping),
      outputMappingItems: mappingToItems(s.output_mapping),
      successRule: s.success_rule
        ? { field: s.success_rule.field || '', operator: s.success_rule.operator || 'eq', value: String(s.success_rule.value ?? '') }
        : { field: 'state', operator: 'eq', value: 'success' },
      failRule: s.fail_rule
        ? { field: s.fail_rule.field || '', operator: s.fail_rule.operator || 'neq', value: String(s.fail_rule.value ?? '') }
        : { field: 'state', operator: 'neq', value: 'success' },
    }))
  } else {
    useMultiStep.value = false
    pushSteps.value = []
  }
}

/**
 * 从前端表单提取 push_steps（可视化格式 → JSON）
 */
const extractPushSteps = () => {
  if (!useMultiStep.value || pushSteps.value.length === 0) {
    return null
  }
  return pushSteps.value.map(s => ({
    name: s.name,
    endpoint: s.endpoint,
    method: s.method,
    timeout: s.timeout ?? 10,
    request_mapping: itemsToMapping(s.requestMappingItems),
    output_mapping: itemsToMapping(s.outputMappingItems),
    success_rule: (s.successRule.field && s.successRule.field.trim())
      ? { field: s.successRule.field.trim(), operator: s.successRule.operator, value: s.successRule.value }
      : null,
    fail_rule: (s.failRule.field && s.failRule.field.trim())
      ? { field: s.failRule.field.trim(), operator: s.failRule.operator, value: s.failRule.value }
      : null,
  }))
}

// --- 逻辑处理 ---

// 加载回调接收配置
const loadCallbackConfig = (data) => {
  const config = (typeof data === 'string') ? JSON.parse(data) : (data || {})
  callbackConfig.enabled = config.enabled ?? false
  callbackConfig.type = config.type || 'province_order_result'
  const fieldMapping = config.field_mapping || {}
  callbackConfig.field_mapping = Object.entries(fieldMapping).map(([internal, external]) => ({
    internal,
    external,
  }))
  if (callbackConfig.field_mapping.length === 0) {
    callbackConfig.field_mapping = []
  }
  callbackConfig.success_rule = config.success_rule || { field: '', operator: 'eq', value: '' }
}

// 获取数据列表
const getList = async () => {
  loading.value = true
  try {
    const res = await channelApi.list(filter)
    rows.value = res.data.data || []
  } catch (error) {
    console.log('加载失败', error)
  } finally {
    loading.value = false
  }
}

// 打开新增/编辑弹窗
const openDialog = async (row = null) => {
  dialog.isEdit = !!row
  dialog.tab = 'basic'
  extConfigError.value = false
  extConfigErrorMsg.value = ''
  pushSuccessRuleError.value = false
  pushSuccessRuleErrorMsg.value = ''
  pushFailRuleError.value = false
  pushFailRuleErrorMsg.value = ''

  if (row) {
    // 编辑时加载完整详情
    try {
      const res = await channelApi.show(row.id)
      const data = res.data
      Object.assign(form, {
        id: data.id,
        name: data.name || '',
        pid: data.pid || '',
        key: data.key || '',
        organization_id: data.organization_id || null,
        role: data.role || 'unset',
        status: data.status ?? 1,
        remark: data.remark || '',
        ip_whitelist: data.ip_whitelist || '',
        is_ip_restricted: !!data.is_ip_restricted,
        push_base_url: data.push_base_url || '',
        push_endpoint: data.push_endpoint || '',
        push_timeout: data.push_timeout ?? 10,
        sign_algorithm: data.sign_algorithm || 'sha256',
        sign_key: data.sign_key || '',
        callback_url: data.callback_url || '',
        // 接收认证方式
        receive_auth_mode: data.receive_auth_mode || 'signature',
        // 向下家推送信息配置
        push_notify_url: data.push_notify_url || '',
        push_max_retries: data.push_max_retries ?? 3,
        push_retry_delay: data.push_retry_delay ?? 10,
        // 定时回调推送
        auto_callback_enabled: !!data.auto_callback_enabled,
        auto_callback_time_start: data.auto_callback_time_start || '',
        auto_callback_time_end: data.auto_callback_time_end || '',
        service_class: data.service_class || '',
        ext_config: data.ext_config || null,
        push_success_rule: data.push_success_rule || null,
        push_fail_rule: data.push_fail_rule || null,
      })
      // 兼容处理：数据库可能存了双倍编码的 JSON 字符串，需统一转为对象
      const normalizeJson = (v) => { if (!v) return null; if (typeof v === 'string') { try { return JSON.parse(v) } catch { return v } } return v }
      extConfigText.value = normalizeJson(data.ext_config) ? JSON.stringify(normalizeJson(data.ext_config), null, 2) : ''
      pushSuccessRuleText.value = normalizeJson(data.push_success_rule) ? JSON.stringify(normalizeJson(data.push_success_rule), null, 2) : ''
      pushFailRuleText.value = normalizeJson(data.push_fail_rule) ? JSON.stringify(normalizeJson(data.push_fail_rule), null, 2) : ''
      // 加载回调接收配置
      loadCallbackConfig(data.callback_config)
      // 加载多步骤推送配置
      loadPushSteps(data.push_steps)
      // 加载入站字段映射
      loadReceiveMapping(receiveMappingUI, data.receive_mapping)
      // 加载停止条件配置
      stopRulesUI.value = (data.stop_rules && data.stop_rules.conditions) ? data.stop_rules.conditions : []
      editProducts.value = data.products || []
    } catch {
      $q.notify({ type: 'negative', message: '加载渠道详情失败', position: 'center' })
      return
    }
  } else {
    Object.assign(form, {
      id: null, name: '', pid: '', key: '', organization_id: null,
      role: 'unset', remark: '', status: 1, ip_whitelist: '', is_ip_restricted: false,
      push_base_url: '', push_endpoint: '', push_timeout: 10,
      sign_algorithm: 'sha256', sign_key: '', callback_url: '', service_class: '',
      ext_config: null, push_success_rule: null, push_fail_rule: null,
      // 接收认证方式
      receive_auth_mode: 'signature',
      // 向下家推送信息配置
      push_notify_url: '', push_max_retries: 3, push_retry_delay: 10,
      // 定时回调推送
      auto_callback_enabled: false, auto_callback_time_start: '', auto_callback_time_end: '',
    })
    extConfigText.value = ''
    pushSuccessRuleText.value = ''
    pushFailRuleText.value = ''
    // 重置回调接收配置
    loadCallbackConfig(null)
    // 重置多步骤推送
    loadPushSteps(null)
    // 重置入站字段映射
    loadReceiveMapping(receiveMappingUI, null)
    // 重置停止条件配置
    stopRulesUI.value = []
    editProducts.value = []
  }
  dialog.show = true
}

// 提交渠道表单
const saveChannel = async () => {
  // 处理 ext_config JSON
  if (extConfigText.value.trim()) {
    try {
      form.ext_config = JSON.parse(extConfigText.value)
      extConfigError.value = false
    } catch {
      extConfigError.value = true
      extConfigErrorMsg.value = 'JSON 格式不正确，请检查'
      $q.notify({ type: 'negative', message: 'ext_config JSON 格式错误', position: 'center' })
      return
    }
  } else {
    form.ext_config = null
  }

  // 处理 push_success_rule JSON
  if (pushSuccessRuleText.value.trim()) {
    try {
      form.push_success_rule = JSON.parse(pushSuccessRuleText.value)
      pushSuccessRuleError.value = false
    } catch {
      pushSuccessRuleError.value = true
      pushSuccessRuleErrorMsg.value = 'JSON 格式不正确，请检查'
      $q.notify({ type: 'negative', message: '成功判断规则 JSON 格式错误', position: 'center' })
      return
    }
  } else {
    form.push_success_rule = null
  }

  // 处理 push_fail_rule JSON
  if (pushFailRuleText.value.trim()) {
    try {
      form.push_fail_rule = JSON.parse(pushFailRuleText.value)
      pushFailRuleError.value = false
    } catch {
      pushFailRuleError.value = true
      pushFailRuleErrorMsg.value = 'JSON 格式不正确，请检查'
      $q.notify({ type: 'negative', message: '失败判断规则 JSON 格式错误', position: 'center' })
      return
    }
  } else {
    form.push_fail_rule = null
  }

  // 处理多步骤推送配置
  form.push_steps = extractPushSteps()

  // 处理回调接收配置
  if (callbackConfig.enabled) {
    const fieldMapping = {}
    callbackConfig.field_mapping.forEach(item => {
      if (item.internal && item.external) {
        fieldMapping[item.internal] = item.external
      }
    })
    form.callback_config = {
      enabled: callbackConfig.enabled,
      type: callbackConfig.type,
      field_mapping: fieldMapping,
      success_rule: callbackConfig.success_rule,
    }
  } else {
    form.callback_config = { enabled: false }
  }

  // 处理入站字段映射：校验 + 提取为 receive_mapping
  const mappingErrors = validateReceiveMapping(receiveMappingUI)
  if (mappingErrors.length > 0) {
    $q.notify({ type: 'negative', message: '入站字段映射配置有误: ' + mappingErrors[0], position: 'center' })
    return
  }
  form.receive_mapping = extractReceiveMapping(receiveMappingUI)

  // 处理停止条件配置
  form.stop_rules = stopRulesUI.value.length ? { conditions: stopRulesUI.value } : null

  submitting.value = true
  try {
    if (dialog.isEdit) {
      await channelApi.update(form.id, form)
      $q.notify({ type: 'positive', message: '渠道更新成功', position: 'center' })
    } else {
      await channelApi.store(form)
      $q.notify({ type: 'positive', message: '渠道创建成功', position: 'center' })
    }
    dialog.show = false
    getList()
  } catch (error) {
    const errors = error.response?.data?.errors
    let detailMsg = error.response?.data?.message || error.message
    if (errors) {
      const errList = Object.values(errors).flat().join('; ')
      detailMsg += ' (' + errList + ')'
      console.error('验证错误详情:', errors)
    }
    $q.notify({ type: 'negative', message: '保存失败: ' + detailMsg, position: 'center' })
  } finally {
    submitting.value = false
  }
}

// --- 产品配置逻辑 ---

const manageProducts = async (row) => {
  productDialog.channelId = row.id
  productDialog.channelName = row.name
  const res = await channelApi.show(row.id)
  productDialog.items = res.data.products || []
  productDialog.show = true
}

const saveProductConfig = async () => {
  try {
    await channelApi.syncProducts(productDialog.channelId, productDialog.items)
    productDialog.show = false
    $q.notify({ type: 'positive', message: '产品配置保存成功', position: 'center' })
  } catch (error) {
    console.log('保存失败', error)
  }
}

// 复制
const copyContent = async (row) => {
  const res = await channelApi.show(row.id)
  const data = res.data || {}
  let products = ''
  const items = data.products || []
  for (let i = 0; i < items.length; i++) {
    products += `产品名称：${items[i].product_name} bus_code:${items[i].bus_code} sku_code:${items[i].sku_code}\t\n`
  }
  const v = `
  公司名称：${row.organization?.name || ''}\t
  PID:${data.pid || ''}\t
  KEY:${data.key || ''}\t
  ${products}
  `
  copyToClipboard(v)
    .then(() => {
      $q.notify({ type: 'positive', message: '复制成功！', position: 'center' })
    })
    .catch(() => {
      $q.notify({ type: 'negative', message: '复制失败，请手动复制', position: 'center' })
    })
}

// 初始化加载业务列表
onMounted(async () => {
  const res = await businessApi.list()
  businessOptions.value = res.data.data || []
  getList()
})

const loadProductsByBusiness = async (bId) => {
  selector.productId = null
  if (!bId) return
  const res = await productApi.listByBusiness(bId)
  productOptions.value = res.data.data || []
  ;(res.data.data || []).forEach(p => {
    allProductNames.value[p.id] = p.name
  })
}

const addProductToConfig = () => {
  const exists = productDialog.items.some(i => i.product_id == selector.productId)
  if (exists) {
    $q.notify({ type: 'warning', message: '该产品已在配置列表中', position: 'center' })
    return
  }
  productDialog.items.push({
    product_id: selector.productId,
    product_name: getProductName(selector.productId),
    status: 1,
    remark: ''
  })
  const selectedProduct = productOptions.value.find(p => p.id === selector.productId)
  if (selectedProduct) {
    allProductNames.value[selectedProduct.id] = selectedProduct.name
  }
  selector.productId = null
}

const getProductName = (id) => {
  return allProductNames.value[id] || `产品(ID:${id})`
}
</script>