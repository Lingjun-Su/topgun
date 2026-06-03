/**
 * Quasar项目的核心路由配置文件
 * 作用：定义URL路径与页面/布局组件的映射关系，决定访问不同URL时渲染哪个组件
 * 路由匹配规则：从上到下依次匹配，优先匹配先定义的规则
 */

// 定义路由规则数组，每个对象对应一条路由规则
const routes = [
  // 第一组路由：根路径（/）的主布局+首页规则
  {
    // 匹配的URL路径：网站根路径（http://localhost:8080/）
    path: '/',
    // 该路径对应的布局组件（懒加载方式）
    // 懒加载：组件仅在首次访问该路径时才加载，优化项目启动速度
    component: () => import('layouts/MainLayout.vue'),
    meta:{requiresAuth: true},
    // 子路由：嵌套在MainLayout.vue中的路由（对应MainLayout里的<router-view />）
    // 作用：MainLayout作为公共布局（含导航栏/侧边栏），子路由渲染具体业务页面
    children: [// 子路由规则：匹配根路径（/）的默认子路由
      {path: '',component: () => import('pages/IndexPage.vue'),meta:{title:"首页",requiresAuth: true},name:"home"},
      {path:'boards',component:()=>import('pages/HomePage.vue'),meta:{title:"仪表盘",requiresAuth: true},name:"仪表盘"},
      {path:'userInfo',component:()=>import('pages/users/UserInfoPage.vue'),meta:{title:"用户信息",requiresAuth: true},name:"用户信息"},
      {path:'dictionary',component:()=>import('pages/DictionaryManager.vue'),meta:{title:"数据字典",requiresAuth: true},name:"数据字典"},
      {path:'excelImport',component:()=>import('pages/ExcelImportPage.vue'),meta:{title:"数据字典",requiresAuth: true},name:"数据字典"},
      {path:'ThirdChannel',component:()=>import('pages/ThirdChannelManagerPage.vue'),meta:{title:"渠道接入管理",requiresAuth: true},name:"渠道接入管理"},

      {path:'organization',component:()=>import('pages/OrganizationManagementPage.vue'),meta:{title:"组织架构",requiresAuth: true},name:"组织架构"},
      {path:'company',component:()=>import('pages/CompanyManagerPage.vue'),meta:{title:"公司管理",requiresAuth: true},name:"公司管理"},


      {path:'OrderProduct',component:()=>import('pages/ProductManagerPage.vue'),meta:{title:"产品管理",requiresAuth: true},name:"产品管理"},
      {path:'Business',component:()=>import('pages/BusinessPage.vue'),meta:{title:"业务管理",requiresAuth: true},name:"业务管理"},
      {path:'Carrier',component:()=>import('pages/CarrierManagerPage.vue'),meta:{title:"运营商管理",requiresAuth: true},name:"运营商管理"},

      {path:'userList',component:()=>import('pages/UserManagerPage.vue'),meta:{title:"用户管理",requiresAuth: true},name:"用户管理"},

      //产品订单
      {path:'product-order',component:()=>import('pages/productOrders/ProductOrderManagerPage.vue'),meta:{title:"产品订单",requiresAuth: true},name:"产品订单"},
      {path:'product-order-details/:id',component:()=>import('pages/productOrders/ProductOrdersDetailsPage.vue'),meta:{title:"产品订单明细",requiresAuth: true},name:"产品订单明细"},
      {path:'receiverTest',component:()=>import('pages/receiverTestPage.vue'),meta:{title:"接收测试",requiresAuth: true},name:"接收测试"},

      //报表
      {path:'product-order-report',component:()=>import('pages/reports/ProductOrderReportPage.vue'),meta:{title:'业绩简报',requiresAuth:true},name:'业绩简报'},
      {path:'product-order-chart',component:()=>import('pages/charts/ProductOrderChartPage.vue'),meta:{title:'产品销量趋图',requiresAuth:true},name:'产品销量趋图'},
      {path:'product-order-business-chart',component:()=>import('pages/charts/BusinessStatsPage.vue'),meta:{title:'业务统计数据',requiresAuth:true},name:'业务统计数据'},

      //发票
      {path:'vat-invoice-input',component:()=>import('pages/invoice/VatinvoiceInputPage.vue'),meta:{title:'增值税发票输入',requiresAuth:true},name:'增值税发票输入'},
      //合同
      {path:'MasterContract',component:()=>import('pages/contract/MasterContractListPage.vue'),meta:{title:'框架合同列表',requiresAuth:true},name:'框架合同列表'},
      {path:'contract-form',component:()=>import('pages/contract/MasterContractFormPage.vue'),meta:{title:'修改框架合同',requiresAuth:true},name:'修改框架合同'},
      {path:'contract-form/:id',component:()=>import('pages/contract/MasterContractFormPage.vue'),meta:{title:'新增框架合同',requiresAuth:true},name:'新增框架合同'},
      {path:'contract-detail/:id',component:()=>import('pages/contract/MasterContractDetailPage.vue'),meta:{title:'框架合同明细',requiresAuth:true},name:'框架合同明细'},
      {path:'contract-audit/:id',component:()=>import('pages/contract/MasterContractAuditPage.vue'),meta:{title:'框架合同审批',requiresAuth:true},name:'框架合同审批'},
      {path:'contract-split/:id',component:()=>import('pages/contract/ContractSplitPage.vue'),meta:{title:'框架合同分割',requiresAuth:true},name:'框架合同分割'},

      //执行合同
      {path:'execution-contract',component:()=>import('pages/contract/ExecutionListPage.vue'),meta:{title:'执行合同列表',requiresAuth:true},name:'执行合同列表'},
      {path:'execution-detail/:id',component:()=>import('pages/contract/ExecutionDetailPage.vue'),meta:{title:'执行合同明细',requiresAuth:true},name:'执行合同明细'},
    ]
  },
  {
    path:'/login',
    name:'login',
    meta:{requiresAuth: false},
    component: () => import('pages/LoginPage.vue'),
  },

  // 第二组路由：404页面规则（必须放在最后！）
  // 作用：匹配所有未在上面定义的URL路径，返回“页面未找到”提示
  {
    // 通配符路径：
    // - :catchAll：自定义参数名（可任意命名）
    // - (.*)*：正则表达式，匹配任意字符（包括多级路径，如/a/b/c）
    // - 加*表示可选参数，兼容Vue Router最新语法
    path: '/:catchAll(.*)*',
    // 渲染404错误页面组件（懒加载）
    component: () => import('pages/ErrorNotFound.vue'),
  },
]

// 导出路由规则数组，供Quasar/Vue Router核心模块使用
export default routes
