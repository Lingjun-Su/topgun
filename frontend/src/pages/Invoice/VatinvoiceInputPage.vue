<template>
  <q-page class="q-pa-sm bg-grey-3">
    <div class="row q-col-gutter-sm no-wrap" style="height: calc(100vh - 60px);">

      <div class="col-3 column bg-white shadow-2 rounded-borders">
        <q-toolbar class="bg-primary text-white dense">
          <q-toolbar-title class="text-subtitle1">发票队列 ({{ invoiceList.length }}/50)</q-toolbar-title>
          <q-btn flat round icon="add_box" @click="triggerUpload">
            <q-tooltip>批量添加 PDF (自动解析)</q-tooltip>
          </q-btn>
        </q-toolbar>

        <q-list class="col scroll" separator>
          <q-item v-for="(item, index) in invoiceList" :key="index"
                  clickable @click="currentIndex = index"
                  :active="currentIndex === index" active-class="bg-blue-1">
            <q-item-section avatar>
              <q-spinner-ios v-if="item.status === 'parsing'" color="primary" size="20px" />
              <q-icon v-else :name="item.status === 'done' ? 'check_circle' : 'error'"
                      :color="item.status === 'done' ? 'positive' : 'negative'" size="20px" />
            </q-item-section>
            <q-item-section>
              <q-item-label lines="3" class="text-caption text-weight-bold">{{ item.fileName }}</q-item-label>
              <q-item-label caption class="text-blue-8">{{ item.data.invoice_num || (item.status === 'parsing' ? '正在自动解析...' : '待解析') }}</q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-btn flat round dense icon="close" size="sm" @click.stop="removeItem(index)" />
            </q-item-section>
          </q-item>
        </q-list>

        <q-card-actions class="bg-grey-2">
          <q-btn color="green-7" label="全部保存" class="full-width" icon="save" @click="batchSave" :loading="isSaving" />
        </q-card-actions>
      </div>

      <div class="col-9 column no-wrap" v-if="currentInvoice">
        <q-scroll-area style="height: 420px;" class="bg-white shadow-1 q-mb-xs rounded-borders">
          <div class="q-pa-sm q-gutter-y-sm">

            <div class="text-weight-bold text-primary q-mb-xs flex items-center">
              <q-icon name="info" class="q-mr-xs" /> 基本信息
            </div>
            <div class="row q-col-gutter-xs">
              <q-input v-model="currentInvoice.data.invoice_num" label="发票号码" class="col-3" outlined dense bg-color="yellow-1" />
              <q-input v-model="currentInvoice.data.invoice_date" label="开票日期" class="col-3" outlined dense />
              <q-input v-model="currentInvoice.data.drawer" label="开票人" class="col-3" outlined dense />
              <div class="col-3"></div>
              <q-input v-model="currentInvoice.data.buyer_name" label="买方名称" class="col-6" outlined dense />
              <q-input v-model="currentInvoice.data.buyer_tax_no" label="买方信用代码" class="col-6" outlined dense />
              <q-input v-model="currentInvoice.data.seller_name" label="销售方名称" class="col-6" outlined dense />
              <q-input v-model="currentInvoice.data.seller_tax_no" label="销售方信用代码" class="col-6" outlined dense />
            </div>

            <div class="text-weight-bold text-green-9 q-mt-sm q-mb-xs flex items-center">
              <q-icon name="payments" class="q-mr-xs" /> 金额合计 (自动过滤 ¥ 符号)
            </div>
            <div class="row q-col-gutter-xs">
              <q-input v-model="currentInvoice.data.total_amount" label="金额合计" class="col-4" outlined dense prefix="¥" color="green-10" />
              <q-input v-model="currentInvoice.data.total_tax" label="税额合计" class="col-4" outlined dense prefix="¥" color="green-10" />
              <q-input v-model="currentInvoice.data.all_amount" label="价税合计" class="col-4" outlined dense bg-color="green-1" prefix="¥" />
            </div>

            <div class="text-weight-bold text-blue-grey-8 q-mt-sm q-mb-xs flex items-center">
              <q-icon name="list_alt" class="q-mr-xs" /> 项目明细 (多行合并识别)
            </div>
            {{ currentInvoice.data.dynamicColumns }}
            <q-table
              :rows="currentInvoice.data.items"
              :columns="currentInvoice.data.dynamicColumns"
              dense flat bordered
              hide-pagination
              separator="cell"
              class="q-mb-sm shadow-1"
            />

            <div class="text-weight-bold text-orange-9 q-mt-sm q-mb-xs flex items-center">
              <q-icon name="label_important" class="q-mr-xs" /> 备注审计提取
            </div>
            <div class="row q-col-gutter-xs">
              <q-input v-model="currentInvoice.data.ext_contract" label="合同号" class="col-3" outlined dense color="orange" />
              <q-input v-model="currentInvoice.data.ext_project" label="项目名称" class="col-3" outlined dense color="orange" />
              <q-input v-model="currentInvoice.data.ext_order" label="订单编号" class="col-3" outlined dense color="orange" />
              <q-input v-model="currentInvoice.data.ext_place" label="发生地" class="col-3" outlined dense color="orange" />
              <q-input v-model="currentInvoice.data.remarks" label="备注原文" class="col-12" outlined dense autogrow bg-color="grey-1" />
            </div>
          </div>
        </q-scroll-area>

        <q-card flat bordered class="col column bg-dark overflow-hidden relative-position">
          <q-toolbar class="bg-grey-9 text-white dense" style="min-height: 32px">
            <div class="text-caption">对照预览 (支持鼠标缩放)</div>
            <q-space />
            <q-btn flat round dense icon="zoom_in" size="sm" @click="zoomScale += 0.1" />
            <q-btn flat round dense icon="zoom_out" size="sm" @click="zoomScale -= 0.1" />
          </q-toolbar>
          <q-scroll-area class="col shadow-inner">
            <div class="flex flex-center q-pa-sm">
              <canvas ref="previewCanvas" :style="{ width: (100 * zoomScale) + '%', height: 'auto' }" class="bg-white shadow-10"></canvas>
            </div>
          </q-scroll-area>
        </q-card>
      </div>

      <div class="col-9 flex flex-center bg-white rounded-borders" v-else>
        <div class="text-center text-grey-4">
          <q-icon name="auto_awesome" size="100px" />
          <div class="text-h6">拖入或添加 PDF，系统将自动批量解析</div>
        </div>
      </div>
    </div>

    <input type="file" ref="fileInput" multiple accept=".pdf" class="hidden" @change="onFilesAdded" />
  </q-page>
</template>

<script setup>
import { ref, reactive , computed, watch, nextTick } from 'vue';
import { useQuasar } from 'quasar';

const $q = useQuasar();
const fileInput = ref(null);
const invoiceList = ref([]);
const currentIndex = ref(-1);
const previewCanvas = ref(null);
const zoomScale = ref(1.0);
const isSaving = ref(false);
const EPSILON = 3;
const titleGroups =ref([]);//项目标题

const currentInvoice = computed(() => (currentIndex.value >= 0 ? invoiceList.value[currentIndex.value] : null));
const triggerUpload = () => fileInput.value.click();

/**
 * 1. 自动解析队列逻辑
 */
const onFilesAdded = async (e) => {
  const files = Array.from(e.target.files);
  for (const file of files) {
    const isDuplicate = invoiceList.value.some(i => i.fileName === file.name && i.fileSize === file.size);
    if (isDuplicate || invoiceList.value.length >= 50) continue;

    // 使用 reactive 确保深度响应式
    const task = reactive({
      fileName: file.name, fileSize: file.size, fileObject: file,
      status: 'parsing',
      data: {
        invoice_num: '', invoice_date: '', drawer: '',
        buyer_name: '', buyer_tax_no: '', seller_name: '', seller_tax_no: '',
        total_amount: '', total_tax: '', all_amount: '',
        remarks: '', ext_contract: '', ext_project: '', ext_order: '', ext_place: '',
        items: [], dynamicColumns: []
      }
    });

    invoiceList.value.push(task);
    // 异步执行解析
    processPdf(task);
  }

  // 如果是第一次上传，自动选中第一个
  if (currentIndex.value === -1 && invoiceList.value.length > 0) {
    currentIndex.value = 0;
  }
  e.target.value = '';
};

const processPdf = async (task) => {
  try {
    const pdfjsLib = await import('/public/js/pdfjs/pdf.mjs');
    pdfjsLib.GlobalWorkerOptions.workerSrc = '/public/js/pdfjs/pdf.worker.mjs';

    const arrayBuffer = await task.fileObject.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
    const page = await pdf.getPage(1);
    const textContent = await page.getTextContent();
console.log("text",textContent);
    // 调用之前的专业解析逻辑 (runProfessionalParse)
    runProfessionalParse(textContent.items, task.data);

    task.status = 'done';

    // 如果当前解析完成的正是用户正在看的那一张，触发重新渲染
    if (currentInvoice.value === task) {
      // renderCanvas(page);
    }
  } catch (err) {
    console.log("Err",err);
    task.status = 'error';
  }
};

/**
 * 工业级解析引擎 - 修复响应式与逻辑缺陷
 */
const runProfessionalParse = (items, d) => {
  const isNear = (a, b) => Math.abs(a - b) <= EPSILON;//排除XY轴的误差
  const isNearInItem = (a, b) => Math.abs(a - b) <= 8;//排除项目中对齐X轴误差

  // 1. 辅助：向右寻值逻辑 (解决 ¥ 符号偏移)
  const findValueRightOf = (startX, targetY) => {
    const rightItems = items.filter(i => isNear(i.transform[5], targetY) && i.transform[4] > startX + 2)
                            .sort((a, b) => a.transform[4] - b.transform[4]);
    // 过滤掉符号，只取数字和点
    return rightItems.length > 0 ? rightItems[0].str.replace(/[^\d.]/g, '') : '';
  };

  // 2. 项目标题行识别 (45.42, 238.06)
  const titleY = 238.06;
  const rawTitles = items.filter(i => isNear(i.transform[5], titleY))
                         .sort((a, b) => a.transform[4] - b.transform[4]);

  if (rawTitles.length > 0 && rawTitles[0].str=="项目名称") {
    titleGroups.value=[];//重置
    titleGroups.value.push(
        {
          name:rawTitles[0].str,
          label:rawTitles[0].str,
          field:rawTitles[0].transform[4],
          align:'left',
          x:12.755,//固定项目的误差比较大
        }
      );

    for (let i = 1; i < rawTitles.length; i++) {
      const nextItem = rawTitles[i];//当前
      if(nextItem.str==" " || nextItem.str=="额")continue;//跳过空和额字，额自动补上
      if (nextItem.str=="金" || nextItem.str=="税") {//如果单独一个“额”加到上一项
        nextItem.str +='额';//补上额字
      }

      titleGroups.value.push(
        {
          name:nextItem.str,
          label:nextItem.str,
          field:nextItem.transform[4],
          align:'left',
          x:nextItem.transform[4],
        }
      );
    }
    d.dynamicColumns =titleGroups;//赋值
  }

  // 按照 Y 坐标从上到下排序，方便处理 hasEOL
  const sortedItems = [...items].sort((a, b) => b.transform[5] - a.transform[5]);

  sortedItems.forEach(i => {
    const x = i.transform[4], y = i.transform[5], v = i.str.trim();
    if (!v) return;

    // --- 基础信息 ---
    if (isNear(x, 481.882)) {
      if (isNear(y, 357.325)) d.invoice_num = v;
      if (isNear(y, 340.034)) d.invoice_date = v;
    }
    if (isNear(y, 293.303)) {
      if (isNear(x, 56.692)) d.buyer_name = v;
      if (isNear(x, 340.152)) d.seller_name = v;
    }
    if (isNear(y, 263.412)) {
      if (isNear(x, 152.939)) d.buyer_tax_no = v;
      if (isNear(x, 437.827)) d.seller_tax_no = v;
    }
    if(isNear(x,90.714) && isNear(y,20.909))d.drawer =v;//开票人

    // --- 金额合计逻辑 (寻值纠错) ---
    if (isNear(y, 128.609)) {
      // 如果拿到的是 ¥ 或者为空，则向右看
      if (isNear(x, 399.111)) d.total_amount = (v === '¥' || v === '') ? findValueRightOf(x, y) : v.replace('¥','');
      if (isNear(x, 549.593)) d.total_tax = (v === '¥' || v === '') ? findValueRightOf(x, y) : v.replace('¥','');
    }
    if (isNear(y, 110.577) && isNear(x, 447.426)) {
      d.all_amount = (v === '¥' || v === '') ? findValueRightOf(x, y) : v.replace('¥','');
    }

    // --- 动态明细与 hasEOL 拼接逻辑 ---
    if (y < 235 && y > 130 && d.dynamicColumns.length > 0) {
      if(isNear(x,12.755) && isNear(y,228.574))console.log('v',v);

      let col =null;
      if(v.includes('%')>0)//税率
      {
        col = d.dynamicColumns.find(c => c.label=='税率/征收率');
      }else{
        col = d.dynamicColumns.find(c => isNearInItem(c.x, x));
      }
      console.log("V",v,v.includes("%"),col);
      if (col) {

        let lastRow = d.items[d.items.length - 1];

        // 判断是否为新行：Y 坐标显著变化
        if (!lastRow || Math.abs(lastRow._y - y) > 7) {
          lastRow = { _y: y };
          d.items.push(lastRow);
        }

        const fieldKey = col.field;
        // 如果该单元格已有值，说明是多行拼接 (hasEOL 逻辑)
        if (lastRow[fieldKey]) {
          lastRow[fieldKey] += v;
        } else {
          lastRow[fieldKey] = v;
        }
      }


    }

    // --- 备注提取 ---
    if (isNear(x, 31.487) && y < 100) {
      d.remarks += v;
      if (v.includes('合同号')) d.ext_contract = v.split(/[:：]/)[1] || v.match(/CMGX-[\w-]+/)?.[0];
      if (v.includes('项目名称')) d.ext_project = v.split(/[:：]/)[1];
      if (v.includes('订单编号')) d.ext_order = v.split(/[:：]/)[1] || v.match(/APO\w+/)?.[0];
      if (v.includes('发生地')) d.ext_place = v.split(/[:：]/)[1];
    }
  });
};

const renderCanvas = async (page) => {
  await nextTick(); // 确保 DOM 已经根据 currentIndex 更新
  if (!previewCanvas.value) return;

  const canvas = previewCanvas.value;
  const context = canvas.getContext('2d');

  // 1. 强制设置 rotation 为 0，防止 PDF 元数据自带旋转干扰
  const viewport = page.getViewport({ scale: 2.0 * zoomScale.value, rotation: 0 });

  // 2. 物理像素适配
  canvas.height = viewport.height;
  canvas.width = viewport.width;

  // 3. 渲染前重置变换状态
  context.setTransform(1, 0, 0, 1, 0, 0);
  context.clearRect(0, 0, canvas.width, canvas.height);

  const renderContext = {
    canvasContext: context,
    viewport: viewport,
    intent: 'display'
  };

  try {
    await page.render(renderContext).promise;
  } catch (err) {
    console.error('渲染失败:', err);
  }
};

watch(currentIndex, async (n) => {
  if (n === -1 || !currentInvoice.value) return;
  const pdfjsLib = await import('/public/js/pdfjs/pdf.mjs');
  const pdf = await pdfjsLib.getDocument({ data: await currentInvoice.value.fileObject.arrayBuffer() }).promise;
  const page = await pdf.getPage(1);
  renderCanvas(page);
});

const removeItem = (i) => {
  invoiceList.value.splice(i, 1);
  if (currentIndex.value >= invoiceList.value.length) currentIndex.value = invoiceList.value.length - 1;
};

const batchSave = () => { $q.notify({ type: 'positive', message: '正在同步 50 条记录...' }); };
</script>

<style scoped>
.hidden { display: none; }
:deep(.q-field--dense .q-field__control) { height: 32px; font-size: 13px; }
.shadow-inner { box-shadow: inset 0 2px 8px rgba(0,0,0,0.4); }
:deep(.q-table th) { font-weight: bold; background: #eceff1; color: #455a64; }
:deep(.q-table td) { font-size: 12px; }
</style>
