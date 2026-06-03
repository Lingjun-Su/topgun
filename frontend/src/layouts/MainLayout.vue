<template>
  <q-layout view="lHh Lpr lFf">
    <q-header elevated class="bg-primary text-white">
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer" />

        <q-toolbar-title>
          唐贝管理系统 <span class="text-subtitle2">v1.1</span>
        </q-toolbar-title>

        <q-space />

        <div class="q-gutter-sm row items-center no-wrap">
          <q-btn round dense flat icon="notifications">
            <q-badge color="red" floating>5</q-badge>
            <q-menu>
              <q-list style="min-width: 200px">
                <messages />
                <q-separator />
                <q-item clickable v-close-popup class="text-center">
                  <q-item-section class="text-indigo-8">查看全部通知</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>

          <q-btn round flat>
            <q-avatar size="30px" class="bg-grey-3">
              <img src="https://cdn.quasar.dev/img/boy-avatar.png">
            </q-avatar>
            <q-menu transition-show="jump-down" transition-hide="jump-up">
              <q-list style="min-width: 150px">
                <q-item>
                  <q-item-section avatar>
                    <q-icon name="account_circle" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>{{ authStore.user?.name || '管理员' }}</q-item-label>
                    <q-item-label caption>{{ authStore.user?.role }}</q-item-label>
                  </q-item-section>
                </q-item>
                <q-separator />
                <q-item clickable v-close-popup @click="openPasswordDialog">
                  <q-item-section avatar><q-icon name="vpn_key" /></q-item-section>
                  <q-item-section>修改密码</q-item-section>
                </q-item>
                <q-item clickable v-close-popup @click="handleLogout" class="text-negative">
                  <q-item-section avatar><q-icon name="logout" /></q-item-section>
                  <q-item-section>退出登录</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
        </div>
      </q-toolbar>
    </q-header>

    <!--菜单-->
    <q-drawer v-model="leftDrawerOpen" show-if-above bordered class="bg-grey-1">
      <q-scroll-area class="fit">
        <q-list padding>
          <q-item-label header class="text-weight-bold text-uppercase">项目菜单</q-item-label>
          <template v-for="menu in menuList" :key="menu.id">
            <q-expansion-item
              expand-separator
              :icon="menu.icon"
              :label="menu.label"
              :caption="menu.caption"
              header-class="text-weight-medium"
            >
              <q-list class="q-pl-lg">
                <q-item
                  v-for="sub in menu.sub"
                  :key="sub.id"
                  clickable
                  v-ripple
                  :to="sub.link"
                  active-class="text-primary bg-blue-1"
                >
                  <q-item-section avatar>
                    <q-icon :name="sub.icon" size="xs" />
                  </q-item-section>
                  <q-item-section>{{ sub.label }}</q-item-section>
                </q-item>
              </q-list>
            </q-expansion-item>
          </template>
        </q-list>
      </q-scroll-area>
    </q-drawer>
    <!--菜单end-->

    <q-page-container>
      <!--页面标签-->
      <TabsLayout />
      <q-card>

      </q-card>
    </q-page-container>

    <!--密码修改-->
    <q-dialog v-model="pwdDialog.show" persistent>
      <q-card style="min-width: 400px">
        <q-card-section class="row items-center">
          <div class="text-h6">修改安全密码</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-form @submit="submitPassword" class="q-gutter-md">
            <q-input
              v-model="pwdDialog.form.oldPassword"
              type="password"
              label="原密码 *"
              lazy-rules
              :rules="[ val => val && val.length > 0 || '请输入原密码']"
            />
            <q-input
              v-model="pwdDialog.form.newPassword"
              type="password"
              label="新密码 *"
              hint="建议至少 6 位字符"
              lazy-rules
              :rules="[ val => val && val.length >= 6 || '新密码至少 6 位']"
            />
            <q-input
              v-model="pwdDialog.form.confirmPassword"
              type="password"
              label="确认新密码 *"
              lazy-rules
              :rules="[
                val => val === pwdDialog.form.newPassword || '两次输入的密码不一致'
              ]"
            />

            <div class="row justify-end q-mt-md">
              <q-btn label="取消" flat v-close-popup />
              <q-btn label="确认修改" type="submit" color="primary" :loading="pwdDialog.loading" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
    <!--密码修改end-->

  </q-layout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useQuasar } from 'quasar'
import messages from 'components/MessageList.vue'
import TabsLayout from 'components/TabsLayout.vue'
import { useAuthStore } from 'src/stores/auth'
import { userApi } from 'src/api/user' // 假设你已定义修改密码接口

const $q = useQuasar()
const authStore = useAuthStore()

// 状态管理
const leftDrawerOpen = ref(false)
const pwdDialog = reactive({
  show: false,
  loading: false,
  form: {
    oldPassword: '',
    newPassword: '',
    confirmPassword: ''
  }
})

// 菜单数据（建议移动到独立 config 文件）
const menuList = [
  {
    id:0,
    label:"手机增值管理",
    caption:"手机增值/视频彩铃",
    icon:'alarm_add',
    link:'',
    sub:[
      {
        id:1,
        label:"产品订单",
        icon:"add_shopping_cart",
        link:'/product-order'
      },
      {
        id:2,
        label:"运营商管理",
        icon:"sim_card",
        link:"/Carrier"
      },
      {
        id:3,
        label:"业务管理",
        icon:"warehouse",
        link:"/Business"
      },
      {
        id:4,
        label:"产品管理",
        icon:"shelves",
        link:"/OrderProduct"
      },
      {
        id:5,
        label:"渠道接入管理",
        icon:'sync_alt',
        link:'/ThirdChannel',
      },
      {
        id:6,
        label:'业绩简报',
        icon:'assignment',
        link:'/product-order-report',
      },
      {
        id:7,
        label:'产品销量图',
        icon:'troubleshoot',
        link:'/product-order-chart',
      },
      {
        id:8,
        label:'业务统计数据',
        icon:'troubleshoot',
        link:'/product-order-business-chart',
      },
    ]

  },
  {
    id:1,
    label:"工程项目管理",
    caption:"项目工程/投标/分包结算",
    icon :"card_travel",
    link:'',
    sub:[
      {
        id:1,
        label:"框架合同管理",
        icon:"assignment_turned_in",
        link:'/MasterContract',
      },
      {
        id:2,
        label:"执行合同/订单",
        icon:"cases",
        link:'/execution-contract',
      },
      {
        id:3,
        label:"项目结算",
        icon:"credit_score",
        link:'/ProjectSettlement',
      },
      {
        id:4,
        label:"收支管理",
        icon:"account_balance_wallet",
        link:'/IncomeAndExpenditure',
      },
      {
        id:5,
        label:"增值税发票输入",
        icon:"note",
        link:'/vat-invoice-input',
      },
    ]
  },
  {
    id:2,
    label:"公司管理",
    caption:"公司/部门/员工管理",
    icon:"apartment",
    sub:[
      {
        id:1,
        label:'组织架构',
        icon:'account_tree',
        link:'Organization'
      },
      {
        id:2,
        label:'公司管理',
        icon:'apartment',
        link:'company'
      },
    ]
  },
  {
    id:3,
    label:"系统管理",
    caption:"用户/字典",
    icon:"settings",
    sub:[
      // {
      //   id:1,
      //   label:"数据字典",
      //   icon:"apps",
      //   link:"/Dictionary"
      // },
      {
        id:2,
        label:"用户管理",
        icon:"assignment_ind",
        link:'/UserList'
      },
      // {
      //   id:3,
      //   label:"接收测试",
      //   icon:"assignment_ind",
      //   link:'/receiverTest'
      // },

    ]
  },
  // {
  //   id:4,
  //   label:'我的任务',
  //   caption:'发布/接收任务',
  //   icon:'grading',
  //   link:'',
  //   sub:[
  //     {
  //       id:1,
  //       label:"发布任务",
  //       icon:'post_add',
  //       link:'/PostTask',
  //     },
  //     {
  //       id:2,
  //       label:'未完成任务',
  //       icon:'hourglass_empty',
  //       link:'/UncompletedTask',
  //     },
  //     {
  //       id:3,
  //       label:'已完成任务',
  //       icon:'task_alt',
  //       link:'/CompletedTask',
  //     }
  //   ]
  // }
]

// 逻辑方法
const toggleLeftDrawer = () => {
  leftDrawerOpen.value = !leftDrawerOpen.value
}

const handleLogout = () => {
  $q.dialog({
    title: '退出确认',
    message: '您确定要退出当前系统吗？',
    cancel: true,
    persistent: true
  }).onOk(() => {
    authStore.logout()
  })
}

const openPasswordDialog = () => {
  pwdDialog.form = { oldPassword: '', newPassword: '', confirmPassword: '' }
  pwdDialog.show = true
}

const submitPassword = async () => {
  pwdDialog.loading = true
  try {
    // 假设后端接口为 updatePassword(data)
    await userApi.changePassword({
      oldPassword: pwdDialog.form.oldPassword,
      newPassword: pwdDialog.form.newPassword,
      newPassword_confirmation: pwdDialog.form.confirmPassword
    })

    pwdDialog.show = false
    authStore.logout() // 严谨起见，改密码后强制重登
  } catch (error) {
    console.log("密码修改失败",error);
  } finally {
    pwdDialog.loading = false
  }
}
</script>

<style scoped>
/* 针对 Quasar 默认样式的微调 */
.q-expansion-item--expanded {
  background: rgba(0, 0, 0, 0.02);
}
</style>
