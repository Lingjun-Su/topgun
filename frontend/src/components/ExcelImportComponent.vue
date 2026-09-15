<template>
  <div class="excel-import-component">
    <!-- 文件选择区域 -->
    <div class="file-input-section q-mb-lg">
      <q-card class="bg-grey-2">
        <q-card-section>
          <div class="text-h6 text-primary">Excel文件导入</div>
          <div class="text-caption text-grey-7 q-mb-md">
            支持.xlsx, .xls, .csv格式，最大文件大小：10MB
          </div>

          <div class="row items-center q-gutter-md">
            <q-file
              v-model="selectedFile"
              outlined
              dense
              label="选择Excel文件"
              accept=".xlsx,.xls,.csv,.application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,.application/vnd.ms-excel"
              max-file-size="10485760"
              @rejected="onFileRejected"
              class="col-grow"
              bg-color="white"
            >
              <template v-slot:prepend>
                <q-icon name="attach_file" />
              </template>
            </q-file>

            <q-btn
              color="primary"
              icon="upload"
              label="导入文件"
              :disable="!selectedFile"
              @click="importExcel"
              :loading="isLoading"
            />

            <q-btn
              color="grey-7"
              icon="refresh"
              label="重置"
              @click="resetComponent"
              flat
              v-if="excelData.length > 0"
            />
          </div>

          <!-- 编辑模式开关 -->
          <div class="row items-center q-mt-md">
            <q-toggle
              v-model="internalEditable"
              label="启用编辑模式"
              color="primary"
              left-label
              class="q-mr-md"
            />

            <q-toggle
              v-model="internalAutoSave"
              label="自动保存修改"
              color="green"
              left-label
              :disable="!internalEditable"
              class="q-mr-md"
            />

            <q-badge v-if="totalUnsavedChanges > 0" color="warning" class="q-pa-xs">
              {{ totalUnsavedChanges }} 处未保存修改
            </q-badge>
          </div>

          <div v-if="errorMessage" class="error-message q-mt-sm">
            <q-icon name="error" color="negative" size="sm" />
            <span class="text-negative q-ml-xs">{{ errorMessage }}</span>
          </div>
        </q-card-section>
      </q-card>
    </div>

    <!-- 数据展示区域 -->
    <div v-if="excelData.length > 0" class="data-display-section">
      <q-card>
        <q-card-section>
          <div class="row justify-between items-center">
            <div class="text-h6 text-grey-8">
              数据预览 (共 {{ totalRows }} 行)
              <q-badge v-if="totalUnsavedChanges > 0" color="warning" class="q-ml-sm">
                有 {{ totalUnsavedChanges }} 处修改
              </q-badge>
            </div>
            <div>
              <q-badge color="primary" class="q-mr-sm">
                {{ sheetNames.length }} 个Sheet
              </q-badge>

              <q-btn-dropdown
                color="secondary"
                icon="download"
                label="获取数据"
                :disable="!hasAnyData"
                class="q-mr-sm"
                size="sm"
                outline
              >
              <q-btn
                color="positive"
                icon="file_download"
                label="导出Excel"
                :disable="!hasAnyData"
                @click="exportToExcel"
                size="sm"
                outline
                class="q-mr-sm"
              />
                <q-list>
                  <q-item clickable v-close-popup @click="emitDataToParent(false)">
                    <q-item-section>
                      <q-item-label>获取原始数据</q-item-label>
                    </q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="emitDataToParent(true)">
                    <q-item-section>
                      <q-item-label>获取修改后数据</q-item-label>
                    </q-item-section>
                  </q-item>
                </q-list>
              </q-btn-dropdown>

              <q-btn
                v-if="totalUnsavedChanges > 0"
                color="warning"
                icon="save"
                label="保存所有修改"
                @click="saveAllChanges"
                size="sm"
                class="q-mr-sm"
              />

              <q-btn
                v-if="totalUnsavedChanges > 0"
                color="negative"
                icon="undo"
                label="撤销所有修改"
                @click="discardAllChanges"
                size="sm"
                outline
              />
            </div>
          </div>
        </q-card-section>

        <!-- Sheet标签页 -->
        <q-tabs
          v-model="currentSheet"
          align="left"
          class="bg-primary text-white"
          active-color="white"
          indicator-color="yellow"
        >
          <q-tab
            v-for="(sheetName, index) in sheetNames"
            :key="index"
            :name="index"
            :label="getTabLabel(sheetName, index)"
            :icon="getSheetIcon(index)"
          />
        </q-tabs>

        <!-- Sheet内容 -->
        <q-tab-panels
          v-model="currentSheet"
          animated
          class="sheet-panels"
        >
          <q-tab-panel
            v-for="(sheet, sheetIndex) in excelData"
            :key="sheetIndex"
            :name="sheetIndex"
            class="q-pa-none"
          >
            <div class="sheet-info q-pa-md bg-grey-3">
              <div class="row items-center">
                <div class="col">
                  <span class="text-weight-medium">Sheet名称: </span>
                  <span class="text-primary">{{ sheetNames[sheetIndex] }}</span>
                  <span class="q-ml-md">
                    <span class="text-weight-medium">数据行数: </span>
                    <span class="text-blue">{{ sheet.data.length }}</span>
                  </span>
                  <span class="q-ml-md">
                    <span class="text-weight-medium">列数: </span>
                    <span class="text-blue">{{ sheet.headers.length }}</span>
                  </span>
                  <span v-if="hasUnsavedChanges(sheetIndex)" class="q-ml-md">
                    <q-badge color="warning">
                      {{ unsavedChanges[sheetIndex]?.length || 0 }} 处未保存修改
                    </q-badge>
                  </span>
                </div>
                <div class="col-auto">
                  <q-btn
                    color="grey-7"
                    icon="content_copy"
                    label="复制表头"
                    size="sm"
                    @click="copyHeaders(sheet.headers)"
                    outline
                    class="q-mr-sm"
                  />

                  <q-btn
                    v-if="hasUnsavedChanges(sheetIndex)"
                    color="warning"
                    icon="save"
                    label="保存修改"
                    size="sm"
                    @click="saveSheetChanges(sheetIndex)"
                    class="q-mr-sm"
                  />

                  <q-btn
                    v-if="hasUnsavedChanges(sheetIndex)"
                    color="negative"
                    icon="undo"
                    label="撤销修改"
                    size="sm"
                    @click="discardSheetChanges(sheetIndex)"
                    outline
                  />
                </div>
              </div>
            </div>

            <!-- 数据表格 -->
            <div class="table-container">
              <q-table
                :rows="getFilteredData(sheet.data)"
                :columns="sheet.headers"
                row-key="__index"
                :rows-per-page-options="[10, 20, 50, 100]"
                :pagination="{ rowsPerPage: 20 }"
                class="excel-table"
                dense
                flat
                bordered
              >
                <template v-slot:body="props">
                  <q-tr :props="props">
                    <q-td
                      v-for="col in props.cols"
                      :key="col.name"
                      :props="props"
                      class="excel-cell"
                      :class="{
                        'editing-cell': isEditing(props.row.__originalIndex, col.field, sheetIndex),
                        'modified-cell': isCellModified(props.row.__originalIndex, col.field, sheetIndex),
                        'selected-cell': isCellSelected(props.row.__originalIndex, col.field, sheetIndex)
                      }"
                    >
                      <!-- 可编辑单元格 -->
                      <div v-if="isEditing(props.row.__originalIndex, col.field, sheetIndex)"
                           class="editing-cell-content">
                        <q-input
                          v-model="editValue"
                          dense
                          autofocus
                          borderless
                          @blur="finishEditing"
                          @keyup.enter="finishEditing"
                          @keyup.esc="cancelEditing"
                          class="edit-input"
                          :rules="cellValidationRules"
                        />
                      </div>

                      <!-- 显示单元格内容 -->
                      <div v-else
                           class="cell-content"
                           @click="onCellClick(props.row.__originalIndex, col.field, sheetIndex, $event)"
                           @dblclick="startEditing(props.row.__originalIndex, col.field, sheetIndex)">
                        {{ formatCellValue(props.row[col.field]) }}
                        <q-tooltip v-if="internalEditable" class="text-caption">
                          单击选中，双击编辑
                        </q-tooltip>
                      </div>
                    </q-td>
                  </q-tr>
                </template>

                <template v-slot:top-right>
                  <q-input
                    v-model="searchText"
                    outlined
                    dense
                    placeholder="搜索表格内容..."
                    class="q-mr-sm"
                    style="width: 250px"
                  >
                    <template v-slot:append>
                      <q-icon name="search" />
                    </template>
                  </q-input>

                  <q-btn
                    v-if="selectedCell"
                    color="info"
                    icon="edit"
                    label="编辑选中单元格"
                    @click="startEditing(selectedCell.rowIndex, selectedCell.colField, selectedCell.sheetIndex)"
                    size="sm"
                    class="q-mr-sm"
                  />

                  <q-btn
                    color="grey-7"
                    icon="fullscreen"
                    @click="toggleFullScreen(sheetIndex)"
                    size="sm"
                    flat
                    :title="isFullScreen[sheetIndex] ? '退出全屏' : '全屏查看'"
                  />
                </template>
              </q-table>
            </div>
          </q-tab-panel>
        </q-tab-panels>
      </q-card>
    </div>

    <!-- 空状态 -->
    <div v-else class="empty-state text-center q-pa-xl">
      <q-icon name="table_view" size="80px" color="grey-4" />
      <div class="text-h6 text-grey-6 q-mt-md">暂无Excel数据</div>
      <div class="text-body1 text-grey-5 q-mt-sm">
        请选择并导入Excel文件查看数据预览
      </div>
    </div>

    <!-- 全屏查看模态框 -->
    <q-dialog
      v-model="fullScreenDialog"
      full-width
      full-height
      maximized
    >
      <q-card>
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">全屏预览 - {{ currentSheetName }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <q-table
            v-if="fullScreenSheet"
            :rows="fullScreenSheet.data"
            :columns="fullScreenSheet.headers"
            row-key="__index"
            :pagination="{ rowsPerPage: 50 }"
            class="fullscreen-table"
            flat
            bordered
            dense
          >
            <template v-slot:body="props">
              <q-tr :props="props">
                <q-td
                  v-for="col in props.cols"
                  :key="col.name"
                  :props="props"
                >
                  {{ formatCellValue(props.row[col.field]) }}
                </q-td>
              </q-tr>
            </template>
          </q-table>
        </q-card-section>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
import { ref, computed, watch } from 'vue';
import * as XLSX from 'xlsx';
import { useQuasar } from 'quasar';

export default {
  name: 'ExcelImportComponent',

  props: {
    // 是否自动解析文件（当选择文件后立即解析）
    autoParse: {
      type: Boolean,
      default: false
    },
    // 是否显示原始数据类型（在表格中显示）
    showDataTypes: {
      type: Boolean,
      default: false
    },
    // 最大允许的行数（0表示无限制）
    maxRows: {
      type: Number,
      default: 10000
    },
    // 是否启用编辑模式
    editable: {
      type: Boolean,
      default: true
    },
    // 是否自动保存修改
    autoSave: {
      type: Boolean,
      default: false
    },
    // 单元格验证规则
    cellValidation: {
      type: Function,
      default: null
    }
  },

  setup(props, { emit }) {
    const $q = useQuasar();

    // 响应式数据
    const selectedFile = ref(null);
    const excelData = ref([]);
    const sheetNames = ref([]);
    const currentSheet = ref(0);
    const isLoading = ref(false);
    const errorMessage = ref('');
    const searchText = ref('');
    const isFullScreen = ref({});
    const fullScreenDialog = ref(false);
    const fullScreenSheet = ref(null);

    //导出
    const exportLoading = ref(false);
    const exportFormat = ref('xlsx'); // 导出格式：xlsx 或 csv

    // 编辑相关数据 - 使用内部变量避免与props冲突
    const internalEditable = ref(props.editable);
    const internalAutoSave = ref(props.autoSave);
    const editingCell = ref({
      sheetIndex: null,
      rowIndex: null,
      colField: null,
      originalValue: null
    });
    const editValue = ref('');
    const unsavedChanges = ref({});
    const selectedCell = ref(null);

    // 计算属性
    const totalRows = computed(() => {
      return excelData.value.reduce((total, sheet) => total + sheet.data.length, 0);
    });

    //导出
    const exportFileName = computed(() => {
      if (!selectedFile.value) return 'data_export';
      const originalName = selectedFile.value.name.replace(/\.[^/.]+$/, "");
      const timestamp = new Date().getTime();
      return `${originalName}_modified_${timestamp}`;
    });

    const currentSheetName = computed(() => {
      return sheetNames.value[currentSheet.value] || '';
    });

    const totalUnsavedChanges = computed(() => {
      let total = 0;
      Object.values(unsavedChanges.value).forEach(changes => {
        total += changes.length;
      });
      return total;
    });

    const hasAnyData = computed(() => {
      return excelData.value.length > 0;
    });

    // 单元格验证规则
    const cellValidationRules = computed(() => {
      return props.cellValidation ? [validateCellValue] : [];
    });

    // 监听文件选择变化（如果启用自动解析）
    watch(selectedFile, (newFile) => {
      if (newFile && props.autoParse) {
        importExcel();
      }
    });

    // 监听props变化，更新内部变量
    watch(() => props.editable, (newVal) => {
      internalEditable.value = newVal;
      emit('editable-changed', newVal);
    });

    watch(() => props.autoSave, (newVal) => {
      internalAutoSave.value = newVal;
      emit('auto-save-changed', newVal);
    });

    // 监听内部编辑模式变化
    watch(internalEditable, (newVal) => {
      emit('editable-changed', newVal);
    });

    // 监听内部自动保存变化
    watch(internalAutoSave, (newVal) => {
      emit('auto-save-changed', newVal);
    });

    // 方法定义
    const importExcel = async () => {
      if (!selectedFile.value) {
        errorMessage.value = '请先选择文件';
        return;
      }

      isLoading.value = true;
      errorMessage.value = '';

      try {
        // 读取文件
        const data = await readFile(selectedFile.value);

        // 解析Excel
        const workbook = XLSX.read(data, {
          type: 'array',
          cellDates: true, // 处理日期类型
          cellText: false,
          cellNF: false
        });

        // 获取sheet名称
        sheetNames.value = workbook.SheetNames;

        // 清空之前的数据
        excelData.value = [];
        isFullScreen.value = {};
        unsavedChanges.value = {};
        selectedCell.value = null;

        // 处理每个sheet
        for (let i = 0; i < workbook.SheetNames.length; i++) {
          const sheetName = workbook.SheetNames[i];
          const worksheet = workbook.Sheets[sheetName];

          // 将sheet转换为JSON
          let jsonData = XLSX.utils.sheet_to_json(worksheet, {
            header: 1, // 获取原始数据
            defval: '', // 空单元格的默认值
            raw: false, // 获取格式化后的值
          });

          // 提取表头（第一行）
          const headers = jsonData.length > 0 ?
            jsonData[0].map((header, index) => {
              // 如果表头为空，使用列字母
              if (!header || header.toString().trim() === '') {
                return {
                  name: `column_${index}`,
                  label: `列 ${index + 1}`,
                  field: `col_${index}`,
                  align: 'left',
                  sortable: true
                };
              }

              return {
                name: `col_${index}`,
                label: header.toString(),
                field: `col_${index}`,
                align: 'left',
                sortable: true
              };
            }) : [];

          // 提取数据（从第二行开始）
          const dataRows = [];
          const startRow = jsonData.length > 1 ? 1 : 0;

          for (let rowIndex = startRow; rowIndex < jsonData.length; rowIndex++) {
            // 检查是否超过最大行数限制
            if (props.maxRows > 0 && rowIndex - startRow >= props.maxRows) {
              $q.notify({
                message: `Sheet "${sheetName}" 超过最大行数限制，仅显示前 ${props.maxRows} 行`,
                color: 'warning',
                icon: 'warning',
                timeout: 3000
              });
              break;
            }

            const row = jsonData[rowIndex];
            const rowData = {};

            // 将每列数据映射到对应的字段
            headers.forEach((header, colIndex) => {
              const value = colIndex < row.length ? row[colIndex] : '';
              rowData[header.field] = value;
            });

            // 添加索引字段用于表格行key
            rowData.__index = `row_${rowIndex}`;
            rowData.__originalIndex = rowIndex;

            dataRows.push(rowData);
          }

          // 存储sheet数据
          excelData.value.push({
            name: sheetName,
            headers: headers,
            data: dataRows,
            originalData: jsonData
          });

          // 初始化全屏状态
          isFullScreen.value[i] = false;
        }

        // 默认显示第一个sheet
        currentSheet.value = 0;

        // 通知父组件数据已加载
        emit('excel-loaded', {
          fileName: selectedFile.value.name,
          sheetCount: sheetNames.value.length,
          totalRows: totalRows.value
        });

        $q.notify({
          message: `成功导入 ${sheetNames.value.length} 个Sheet，共 ${totalRows.value} 行数据`,
          color: 'positive',
          icon: 'check_circle',
          timeout: 3000
        });

      } catch (error) {
        console.error('Excel导入错误:', error);
        errorMessage.value = `导入失败: ${error.message}`;

        $q.notify({
          message: `导入失败: ${error.message}`,
          color: 'negative',
          icon: 'error',
          timeout: 5000
        });
      } finally {
        isLoading.value = false;
      }
    };
    // 在已有方法后面添加导出方法
    const exportToExcel = () => {
      if (excelData.value.length === 0) {
        $q.notify({
          message: '没有可导出的数据',
          color: 'warning',
          icon: 'warning',
          timeout: 2000
        });
        return;
      }

      exportLoading.value = true;

      try {
        // 创建工作簿
        const wb = XLSX.utils.book_new();

        // 处理每个sheet
        excelData.value.forEach((sheet, sheetIndex) => {
          // 获取包含修改的数据
          const sheetData = getSheetDataWithModifications(sheet, sheetIndex);

          // 将数据转换为工作表
          const ws = XLSX.utils.json_to_sheet(sheetData, {
            header: sheet.headers.map(h => h.label),
            skipHeader: false
          });

          // 设置列宽（可选，使导出更美观）
          const colWidths = sheet.headers.map(() => ({ wch: 15 }));
          ws['!cols'] = colWidths;

          // 添加工作表到工作簿
          XLSX.utils.book_append_sheet(wb, ws, sheet.name);
        });

        // 根据选择的格式导出
        let fileData, mimeType, fileExtension;

        if (exportFormat.value === 'csv') {
          // 导出为CSV（只导出第一个sheet，因为CSV不支持多sheet）
          const firstSheetName = wb.SheetNames[0];
          const firstWs = wb.Sheets[firstSheetName];
          fileData = XLSX.utils.sheet_to_csv(firstWs);
          mimeType = 'text/csv;charset=utf-8';
          fileExtension = 'csv';
        } else {
          // 导出为XLSX
          fileData = XLSX.write(wb, {
            type: 'array',
            bookType: 'xlsx'
          });
          mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
          fileExtension = 'xlsx';
        }

        // 创建Blob并下载
        const blob = new Blob([fileData], { type: mimeType });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${exportFileName.value}.${fileExtension}`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        // 触发导出成功事件
        emit('export-success', {
          fileName: `${exportFileName.value}.${fileExtension}`,
          format: exportFormat.value,
          sheetCount: excelData.value.length,
          includeModifications: true
        });

        $q.notify({
          message: `成功导出 ${excelData.value.length} 个Sheet的数据`,
          color: 'positive',
          icon: 'file_download',
          timeout: 3000
        });

      } catch (error) {
        console.error('导出Excel失败:', error);

        $q.notify({
          message: `导出失败: ${error.message}`,
          color: 'negative',
          icon: 'error',
          timeout: 5000
        });

        emit('export-error', { error: error.message });
      } finally {
        exportLoading.value = false;
      }
    };

    // 辅助方法：获取包含修改的sheet数据
    const getSheetDataWithModifications = (sheet, sheetIndex) => {
      // 将数据转换为导出格式
      return sheet.data.map(row => {
        const rowData = {};

        // 复制所有字段
        sheet.headers.forEach(header => {
          // 检查是否有未保存的修改
          const cellModification = unsavedChanges.value[sheetIndex]?.find(
            change => change.rowIndex === row.__originalIndex && change.colField === header.field
          );

          // 如果有修改，使用修改后的值，否则使用原始值
          rowData[header.label] = cellModification ? cellModification.newValue : row[header.field];
        });

        return rowData;
      });
    };

    // 导出为特定格式的方法（暴露给父组件）
    const exportToFormat = (format = 'xlsx', options = {}) => {
      const { includeModifications = true, sheetIndex = null } = options;

      if (excelData.value.length === 0) {
        throw new Error('没有可导出的数据');
      }

      // 创建工作簿
      const wb = XLSX.utils.book_new();

      // 确定要导出的sheet
      const sheetsToExport = sheetIndex !== null
        ? [excelData.value[sheetIndex]]
        : excelData.value;

      // 处理每个sheet
      sheetsToExport.forEach((sheet, index) => {
        // 获取数据（根据是否包含修改）
        let sheetData;

        if (includeModifications) {
          const actualIndex = sheetIndex !== null ? sheetIndex : index;
          sheetData = getSheetDataWithModifications(sheet, actualIndex);
        } else {
          // 导出原始数据（排除内部字段）
          sheetData = sheet.data.map(row => {
            const rowData = {};
            sheet.headers.forEach(header => {
              rowData[header.label] = row[header.field];
            });
            return rowData;
          });
        }

        // 将数据转换为工作表
        const ws = XLSX.utils.json_to_sheet(sheetData, {
          header: sheet.headers.map(h => h.label),
          skipHeader: false
        });

        // 添加工作表到工作簿
        const wsName = sheetIndex !== null ? sheet.name : `${sheet.name}_${index}`;
        XLSX.utils.book_append_sheet(wb, ws, wsName);
      });

      // 生成文件数据
      let fileData, mimeType, fileExtension;

      if (format === 'csv') {
        // 导出为CSV
        const firstSheetName = wb.SheetNames[0];
        const firstWs = wb.Sheets[firstSheetName];
        fileData = XLSX.utils.sheet_to_csv(firstWs);
        mimeType = 'text/csv;charset=utf-8';
        fileExtension = 'csv';
      } else if (format === 'xls') {
        // 导出为XLS（旧格式）
        fileData = XLSX.write(wb, {
          type: 'array',
          bookType: 'xls'
        });
        mimeType = 'application/vnd.ms-excel';
        fileExtension = 'xls';
      } else {
        // 默认导出为XLSX
        fileData = XLSX.write(wb, {
          type: 'array',
          bookType: 'xlsx'
        });
        mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        fileExtension = 'xlsx';
      }

      return {
        blob: new Blob([fileData], { type: mimeType }),
        fileName: `${exportFileName.value}.${fileExtension}`,
        mimeType,
        format
      };
    };

    // 导出当前数据到Blob（暴露给父组件）
    const exportDataToBlob = (options = {}) => {
      const {
        format = 'xlsx',
        includeModifications = true,
        sheetIndex = null
      } = options;

      return exportToFormat(format, { includeModifications, sheetIndex });
    };

    // 在导出对话框中提供格式选择
    const showExportDialog = () => {
      $q.dialog({
        title: '导出选项',
        message: '请选择导出格式和选项',
        options: {
          type: 'radio',
          model: exportFormat.value,
          items: [
            { label: 'Excel (.xlsx)', value: 'xlsx' },
            { label: 'Excel 97-2003 (.xls)', value: 'xls' },
            { label: 'CSV (.csv)', value: 'csv',
              caption: '注意：CSV格式只导出第一个Sheet' }
          ]
        },
        prompt: {
          model: exportFileName.value,
          type: 'text',
          label: '文件名',
          isValid: val => val.length > 0
        },
        cancel: true,
        persistent: true
      }).onOk(data => {
        if (data && data.model && data.value) {
          exportFormat.value = data.value;
          // 这里可以设置文件名，但下载时会自动使用
          exportToExcel();
        }
      });
    };


    const readFile = (file) => {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = (e) => {
          resolve(e.target.result);
        };

        reader.onerror = (e) => {
          console.log('文件读取失败',e);
          reject(new Error('文件读取失败'));
        };

        // 根据文件类型选择读取方式
        if (file.name.endsWith('.csv')) {
          reader.readAsText(file);
        } else {
          reader.readAsArrayBuffer(file);
        }
      });
    };

    const resetComponent = () => {
      selectedFile.value = null;
      excelData.value = [];
      sheetNames.value = [];
      currentSheet.value = 0;
      errorMessage.value = '';
      searchText.value = '';
      isFullScreen.value = {};
      fullScreenDialog.value = false;
      unsavedChanges.value = {};
      selectedCell.value = null;
      editingCell.value = {
        sheetIndex: null,
        rowIndex: null,
        colField: null,
        originalValue: null
      };
      editValue.value = '';

      emit('reset');
    };

    const emitDataToParent = (includeModifications = false) => {
      if (excelData.value.length === 0) {
        $q.notify({
          message: '没有可导出的数据',
          color: 'warning',
          icon: 'warning'
        });
        return;
      }

      let exportData;

      if (includeModifications) {
        // 构建包含修改的数据
        exportData = {
          fileName: selectedFile.value?.name || '',
          sheets: excelData.value.map((sheet, index) => {
            // 应用修改到数据
            const modifiedData = sheet.data.map(row => {
              const cleanRow = { ...row };
              delete cleanRow.__index;
              delete cleanRow.__originalIndex;

              // 应用该行的修改
              const rowChanges = unsavedChanges.value[index]?.filter(
                change => change.rowIndex === row.__originalIndex
              ) || [];

              rowChanges.forEach(change => {
                cleanRow[change.colField] = change.newValue;
              });

              return cleanRow;
            });

            return {
              name: sheet.name,
              index: index,
              headers: sheet.headers.map(h => h.label),
              data: modifiedData,
              rowCount: sheet.data.length,
              columnCount: sheet.headers.length,
              hasUnsavedChanges: unsavedChanges.value[index]?.length > 0
            };
          }),
          totalSheets: excelData.value.length,
          totalRows: totalRows.value,
          hasUnsavedChanges: totalUnsavedChanges.value > 0,
          unsavedChanges: unsavedChanges.value
        };
      } else {
        // 构建原始数据格式
        exportData = {
          fileName: selectedFile.value?.name || '',
          sheets: excelData.value.map((sheet, index) => ({
            name: sheet.name,
            index: index,
            headers: sheet.headers.map(h => h.label),
            data: sheet.data.map(row => {
              const cleanRow = { ...row };
              delete cleanRow.__index;
              delete cleanRow.__originalIndex;
              return cleanRow;
            }),
            rowCount: sheet.data.length,
            columnCount: sheet.headers.length
          })),
          totalSheets: excelData.value.length,
          totalRows: totalRows.value
        };
      }

      // 触发事件给父组件
      emit('get-excel-data', exportData);

      $q.notify({
        message: `数据已准备就绪，共 ${exportData.totalSheets} 个Sheet`,
        color: 'info',
        icon: 'info',
        timeout: 3000
      });
    };

    const getTabLabel = (sheetName, index) => {
      let label = sheetName;
      if (unsavedChanges.value[index]?.length > 0) {
        label += ` (${unsavedChanges.value[index].length})`;
      }
      return label;
    };

    const getSheetIcon = (index) => {
      const icons = ['grid_on', 'table_chart', 'table_rows', 'view_list'];
      return icons[index % icons.length];
    };

    const formatCellValue = (value) => {
      if (value === null || value === undefined || value === '') {
        return '-';
      }

      // 如果是日期类型
      if (value instanceof Date) {
        return value.toLocaleDateString();
      }

      // 如果是数字，并且props.showDataTypes为true，显示类型
      if (props.showDataTypes && typeof value === 'number') {
        return `${value} (数字)`;
      }

      // 如果是布尔值
      if (typeof value === 'boolean') {
        return value ? '是' : '否';
      }

      return value.toString();
    };

    const copyHeaders = (headers) => {
      const headerText = headers.map(h => h.label).join(', ');

      // 使用现代 Clipboard API
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(headerText)
          .then(() => {
            $q.notify({
              message: '表头已复制到剪贴板',
              color: 'positive',
              icon: 'content_copy',
              timeout: 2000
            });
          })
          .catch(err => {
            console.error('复制失败:', err);
            fallbackCopyText(headerText);
          });
      } else {
        fallbackCopyText(headerText);
      }
    };

    const fallbackCopyText = (text) => {
      const textArea = document.createElement('textarea');
      textArea.value = text;
      textArea.style.position = 'fixed';
      textArea.style.opacity = '0';
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();

      try {
        document.execCommand('copy');
        $q.notify({
          message: '表头已复制到剪贴板',
          color: 'positive',
          icon: 'content_copy',
          timeout: 2000
        });
      } catch (err) {
        console.error('复制失败:', err);
        $q.notify({
          message: '复制失败，请手动复制',
          color: 'warning',
          icon: 'warning',
          timeout: 3000
        });
      }

      document.body.removeChild(textArea);
    };

    const toggleFullScreen = (sheetIndex) => {
      fullScreenSheet.value = excelData.value[sheetIndex];
      fullScreenDialog.value = true;
    };

    const onFileRejected = (rejectedEntries) => {
      if (rejectedEntries.length > 0) {
        const reason = rejectedEntries[0].failedPropValidation;
        if (reason === 'max-file-size') {
          errorMessage.value = '文件大小超过10MB限制';
        } else if (reason === 'accept') {
          errorMessage.value = '文件格式不支持，请上传Excel文件 (.xlsx, .xls, .csv)';
        } else {
          errorMessage.value = '文件选择失败';
        }
      }
    };

    // 编辑相关方法
    const getFilteredData = (data) => {
      if (!searchText.value) return data;

      const searchLower = searchText.value.toLowerCase();
      return data.filter(row => {
        return Object.keys(row).some(key => {
          if (key === '__index' || key === '__originalIndex') return false;
          const value = row[key];
          if (value === null || value === undefined) return false;
          return value.toString().toLowerCase().includes(searchLower);
        });
      });
    };

    const startEditing = (rowIndex, colField, sheetIndex) => {
      if (!internalEditable.value) return;

      const sheet = excelData.value[sheetIndex];
      const row = sheet.data.find(r => r.__originalIndex === rowIndex);

      if (row) {
        const originalValue = row[colField];
        editingCell.value = {
          sheetIndex,
          rowIndex,
          colField,
          originalValue
        };
        editValue.value = originalValue || '';

        emit('cell-edit-start', {
          sheetIndex,
          sheetName: sheetNames.value[sheetIndex],
          rowIndex,
          colField,
          value: originalValue
        });
      }
    };

    const isEditing = (rowIndex, colField, sheetIndex) => {
      return editingCell.value.sheetIndex === sheetIndex &&
             editingCell.value.rowIndex === rowIndex &&
             editingCell.value.colField === colField;
    };

    const isCellModified = (rowIndex, colField, sheetIndex) => {
      if (!unsavedChanges.value[sheetIndex]) return false;

      return unsavedChanges.value[sheetIndex].some(
        change => change.rowIndex === rowIndex && change.colField === colField
      );
    };

    const isCellSelected = (rowIndex, colField, sheetIndex) => {
      return selectedCell.value &&
             selectedCell.value.sheetIndex === sheetIndex &&
             selectedCell.value.rowIndex === rowIndex &&
             selectedCell.value.colField === colField;
    };

    const validateCellValue = (value) => {
      if (props.cellValidation) {
        return props.cellValidation(value) || true;
      }
      return true;
    };

    const finishEditing = () => {
      if (editingCell.value.sheetIndex === null) return;

      const { sheetIndex, rowIndex, colField, originalValue } = editingCell.value;
      const sheet = excelData.value[sheetIndex];
      const row = sheet.data.find(r => r.__originalIndex === rowIndex);

      if (row && editValue.value !== originalValue) {
        // 更新数据
        row[colField] = editValue.value;

        // 记录未保存的修改
        if (!unsavedChanges.value[sheetIndex]) {
          unsavedChanges.value[sheetIndex] = [];
        }

        // 检查是否已经存在该单元格的修改记录
        const existingChangeIndex = unsavedChanges.value[sheetIndex].findIndex(
          change => change.rowIndex === rowIndex && change.colField === colField
        );

        if (existingChangeIndex >= 0) {
          // 更新现有记录
          unsavedChanges.value[sheetIndex][existingChangeIndex].newValue = editValue.value;
          unsavedChanges.value[sheetIndex][existingChangeIndex].timestamp = new Date().toISOString();
        } else {
          // 添加新记录
          unsavedChanges.value[sheetIndex].push({
            rowIndex,
            colField,
            originalValue,
            newValue: editValue.value,
            timestamp: new Date().toISOString()
          });
        }

        // 如果启用自动保存
        if (internalAutoSave.value) {
          saveSheetChanges(sheetIndex);
        }

        // 触发修改事件
        emit('cell-updated', {
          sheetIndex,
          sheetName: sheetNames.value[sheetIndex],
          rowIndex,
          colField,
          originalValue,
          newValue: editValue.value,
          totalChanges: unsavedChanges.value[sheetIndex].length
        });
      } else if (row) {
        // 值未改变，但也触发事件
        emit('cell-edit-end', {
          sheetIndex,
          sheetName: sheetNames.value[sheetIndex],
          rowIndex,
          colField,
          value: editValue.value
        });
      }

      // 重置编辑状态
      editingCell.value = {
        sheetIndex: null,
        rowIndex: null,
        colField: null,
        originalValue: null
      };
      editValue.value = '';
    };

    const cancelEditing = () => {
      editingCell.value = {
        sheetIndex: null,
        rowIndex: null,
        colField: null,
        originalValue: null
      };
      editValue.value = '';
    };

    const onCellClick = (rowIndex, colField, sheetIndex, event) => {
      // 如果正在编辑，不处理点击
      if (isEditing(rowIndex, colField, sheetIndex)) return;

      // 设置选中单元格
      selectedCell.value = {
        sheetIndex,
        rowIndex,
        colField
      };

      const sheet = excelData.value[sheetIndex];
      const row = sheet.data.find(r => r.__originalIndex === rowIndex);
      const value = row ? row[colField] : null;

      emit('cell-selected', {
        sheetIndex,
        sheetName: sheetNames.value[sheetIndex],
        rowIndex,
        colField,
        value,
        event
      });
    };

    const hasUnsavedChanges = (sheetIndex) => {
      return unsavedChanges.value[sheetIndex] &&
             unsavedChanges.value[sheetIndex].length > 0;
    };

    const saveSheetChanges = (sheetIndex) => {
      if (!unsavedChanges.value[sheetIndex] || unsavedChanges.value[sheetIndex].length === 0) {
        return;
      }

      const changes = [...unsavedChanges.value[sheetIndex]];
      unsavedChanges.value[sheetIndex] = [];

      emit('changes-saved', {
        sheetIndex,
        sheetName: sheetNames.value[sheetIndex],
        changes,
        totalSaved: changes.length
      });

      $q.notify({
        message: `已保存 ${changes.length} 处修改`,
        color: 'positive',
        icon: 'check',
        timeout: 2000
      });
    };

    const discardSheetChanges = (sheetIndex) => {
      if (!unsavedChanges.value[sheetIndex] || unsavedChanges.value[sheetIndex].length === 0) {
        return;
      }

      // 恢复所有未保存的修改
      unsavedChanges.value[sheetIndex].forEach(change => {
        const sheet = excelData.value[sheetIndex];
        const row = sheet.data.find(r => r.__originalIndex === change.rowIndex);
        if (row) {
          row[change.colField] = change.originalValue;
        }
      });

      const discardedCount = unsavedChanges.value[sheetIndex].length;
      unsavedChanges.value[sheetIndex] = [];

      emit('changes-discarded', {
        sheetIndex,
        sheetName: sheetNames.value[sheetIndex],
        discardedCount
      });

      $q.notify({
        message: `已丢弃 ${discardedCount} 处修改`,
        color: 'warning',
        icon: 'undo',
        timeout: 2000
      });
    };

    const saveAllChanges = () => {
      let totalSaved = 0;
      Object.keys(unsavedChanges.value).forEach(sheetIndex => {
        if (unsavedChanges.value[sheetIndex] && unsavedChanges.value[sheetIndex].length > 0) {
          totalSaved += unsavedChanges.value[sheetIndex].length;
          unsavedChanges.value[sheetIndex] = [];
        }
      });

      emit('all-changes-saved', { totalSaved });

      if (totalSaved > 0) {
        $q.notify({
          message: `已保存所有 ${totalSaved} 处修改`,
          color: 'positive',
          icon: 'check_circle',
          timeout: 3000
        });
      }
    };

    const discardAllChanges = () => {
      $q.dialog({
        title: '确认撤销',
        message: '确定要撤销所有未保存的修改吗？此操作不可恢复。',
        cancel: true,
        persistent: true
      }).onOk(() => {
        let totalDiscarded = 0;

        Object.keys(unsavedChanges.value).forEach(sheetIndex => {
          if (unsavedChanges.value[sheetIndex]) {
            // 恢复所有未保存的修改
            unsavedChanges.value[sheetIndex].forEach(change => {
              const sheet = excelData.value[sheetIndex];
              const row = sheet.data.find(r => r.__originalIndex === change.rowIndex);
              if (row) {
                row[change.colField] = change.originalValue;
              }
            });

            totalDiscarded += unsavedChanges.value[sheetIndex].length;
            unsavedChanges.value[sheetIndex] = [];
          }
        });

        emit('all-changes-discarded', { totalDiscarded });

        $q.notify({
          message: `已撤销所有 ${totalDiscarded} 处修改`,
          color: 'warning',
          icon: 'undo',
          timeout: 3000
        });
      });
    };

    // 暴露给父组件的方法
    const getExcelData = (includeModifications = false) => {
      if (excelData.value.length === 0) {
        return null;
      }

      let data;

      if (includeModifications) {
        data = {
          fileName: selectedFile.value?.name || '',
          sheets: excelData.value.map((sheet, index) => {
            const modifiedData = sheet.data.map(row => {
              const cleanRow = { ...row };
              delete cleanRow.__index;
              delete cleanRow.__originalIndex;

              const rowChanges = unsavedChanges.value[index]?.filter(
                change => change.rowIndex === row.__originalIndex
              ) || [];

              rowChanges.forEach(change => {
                cleanRow[change.colField] = change.newValue;
              });

              return cleanRow;
            });

            return {
              name: sheet.name,
              index: index,
              headers: sheet.headers.map(h => h.label),
              data: modifiedData,
              rawData: sheet.originalData,
              hasUnsavedChanges: unsavedChanges.value[index]?.length > 0
            };
          }),
          hasUnsavedChanges: totalUnsavedChanges.value > 0,
          unsavedChanges: unsavedChanges.value
        };
      } else {
        data = {
          fileName: selectedFile.value?.name || '',
          sheets: excelData.value.map((sheet, index) => ({
            name: sheet.name,
            index: index,
            headers: sheet.headers.map(h => h.label),
            data: sheet.data.map(row => {
              const cleanRow = { ...row };
              delete cleanRow.__index;
              delete cleanRow.__originalIndex;
              return cleanRow;
            }),
            rawData: sheet.originalData
          }))
        };
      }

      return data;
    };

    const getModifiedData = () => {
      const data = getExcelData(true);
      return {
        ...data,
        modifiedSheets: Object.keys(unsavedChanges.value)
          .filter(sheetIndex => unsavedChanges.value[sheetIndex]?.length > 0)
          .map(sheetIndex => ({
            sheetIndex: parseInt(sheetIndex),
            sheetName: sheetNames.value[sheetIndex],
            changes: unsavedChanges.value[sheetIndex],
            changeCount: unsavedChanges.value[sheetIndex].length
          })),
        totalUnsavedChanges: totalUnsavedChanges.value
      };
    };

    const updateCells = (updates) => {
      updates.forEach(update => {
        const { sheetIndex, rowIndex, colField, value } = update;
        const sheet = excelData.value[sheetIndex];
        const row = sheet.data.find(r => r.__originalIndex === rowIndex);

        if (row) {
          const originalValue = row[colField];
          row[colField] = value;

          // 记录修改
          if (!unsavedChanges.value[sheetIndex]) {
            unsavedChanges.value[sheetIndex] = [];
          }

          const existingChangeIndex = unsavedChanges.value[sheetIndex].findIndex(
            change => change.rowIndex === rowIndex && change.colField === colField
          );

          if (existingChangeIndex >= 0) {
            unsavedChanges.value[sheetIndex][existingChangeIndex].newValue = value;
            unsavedChanges.value[sheetIndex][existingChangeIndex].timestamp = new Date().toISOString();
          } else {
            unsavedChanges.value[sheetIndex].push({
              rowIndex,
              colField,
              originalValue,
              newValue: value,
              timestamp: new Date().toISOString()
            });
          }
        }
      });

      emit('cells-updated', { updates, totalUpdates: updates.length });
    };

    const clearUnsavedChanges = () => {
      unsavedChanges.value = {};
      emit('all-changes-cleared');
    };

    const setEditable = (editable) => {
      internalEditable.value = editable;
    };

    const setAutoSave = (autoSaveEnabled) => {
      internalAutoSave.value = autoSaveEnabled;
    };

    return {
      // 响应式数据
      selectedFile,
      excelData,
      sheetNames,
      currentSheet,
      isLoading,
      errorMessage,
      searchText,
      isFullScreen,
      fullScreenDialog,
      fullScreenSheet,

      // 编辑相关数据 - 使用重命名后的变量
      internalEditable,
      internalAutoSave,
      editingCell,
      editValue,
      unsavedChanges,
      selectedCell,
      cellValidationRules,

      // 计算属性
      totalRows,
      currentSheetName,
      totalUnsavedChanges,
      hasAnyData,

      // 方法
      importExcel,
      resetComponent,
      emitDataToParent,
      getTabLabel,
      getSheetIcon,
      formatCellValue,
      copyHeaders,
      toggleFullScreen,
      onFileRejected,

      // 编辑相关方法
      getFilteredData,
      startEditing,
      isEditing,
      isCellModified,
      isCellSelected,
      finishEditing,
      cancelEditing,
      onCellClick,
      hasUnsavedChanges,
      saveSheetChanges,
      discardSheetChanges,
      saveAllChanges,
      discardAllChanges,
      validateCellValue,

      // 暴露给父组件的方法
      getExcelData,
      getModifiedData,
      updateCells,
      clearUnsavedChanges,
      setEditable,
      setAutoSave,

      //导出
      exportLoading,
      exportFormat,
      exportFileName,
      exportToExcel,
      exportDataToBlob,
      showExportDialog,
    };
  }
};
</script>

<style scoped>
/* 样式保持不变，与之前版本相同 */
.excel-import-component {
  font-family: 'Segoe UI', 'Microsoft YaHei', sans-serif;
}

.file-input-section .q-card {
  border-radius: 8px;
  border-left: 4px solid var(--q-primary);
}

.error-message {
  padding: 8px 12px;
  background-color: #ffebee;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
}

.empty-state {
  border: 2px dashed #e0e0e0;
  border-radius: 12px;
  background-color: #fafafa;
}

.sheet-info {
  border-bottom: 1px solid #e0e0e0;
}

.table-container {
  overflow: auto;
  max-height: 500px;
}

.excel-table {
  font-size: 0.875rem;
}

.excel-table thead th {
  background-color: #f5f5f5;
  font-weight: 600;
  position: sticky;
  top: 0;
  z-index: 1;
}

.excel-cell {
  border-right: 1px solid rgba(0, 0, 0, 0.12);
  border-bottom: 1px solid rgba(0, 0, 0, 0.12);
  padding: 0 !important;
  position: relative;
  min-height: 32px;
}

.excel-cell:last-child {
  border-right: none;
}

.editing-cell {
  background-color: #e3f2fd !important;
  border: 1px solid #2196f3 !important;
  z-index: 10;
}

.editing-cell-content {
  padding: 0;
  margin: -1px;
  height: 100%;
}

.edit-input {
  width: 100%;
  height: 100%;
  font-size: inherit;
}

.edit-input :deep(.q-field__control) {
  height: 100%;
  min-height: 0;
  background-color: #e3f2fd;
}

.cell-content {
  width: 100%;
  height: 100%;
  padding: 4px 8px;
  cursor: pointer;
  user-select: none;
  display: flex;
  align-items: center;
}

.cell-content:hover {
  background-color: #f5f5f5;
}

.modified-cell {
  background-color: #fff3e0 !important;
}

.modified-cell .cell-content {
  position: relative;
}

.modified-cell .cell-content::after {
  content: '';
  position: absolute;
  top: 2px;
  right: 2px;
  width: 6px;
  height: 6px;
  background-color: #ff9800;
  border-radius: 50%;
}

.selected-cell {
  background-color: #e8f5e9 !important;
  border: 1px solid #4caf50 !important;
}

.fullscreen-table {
  font-size: 0.9rem;
  height: calc(100vh - 150px);
}

.fullscreen-table thead th {
  background-color: #e3f2fd;
  font-weight: 600;
}

.q-tab--active {
  background-color: rgba(255, 255, 255, 0.2);
}

.sheet-panels {
  border: 1px solid #e0e0e0;
  border-top: none;
  border-radius: 0 0 8px 8px;
}

/* 过渡效果 */
.cell-content {
  transition: background-color 0.2s ease;
}

.edit-input {
  transition: background-color 0.3s ease;
}

/* 保存按钮样式 */
.q-btn--warning {
  background-color: #ff9800;
  color: white;
}

.q-btn--warning:hover {
  background-color: #f57c00;
}
</style>
