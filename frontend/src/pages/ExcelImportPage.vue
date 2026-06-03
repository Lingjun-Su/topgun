<template>
  <q-page class="q-pa-lg">
    <div class="row justify-center">
      <div class="col-12 col-md-10 col-lg-8">
        <div class="text-h4 text-primary q-mb-md">Excel数据导入演示</div>
        <div class="text-subtitle1 text-grey-7 q-mb-lg">
          使用此组件导入Excel文件，预览数据并导出到您的应用中
        </div>

        <!-- Excel导入组件 -->
        <excel-import-component
          ref="excelImporter"
          :auto-parse="false"
          :show-data-types="false"
          :max-rows="5000"
          :editable="true"
          :auto-save="false"
          @excel-loaded="onExcelLoaded"
          @get-excel-data="onGetExcelData"
          @reset="onReset"
          @cell-updated="onCellUpdated"

          @changes-saved="onChangesSaved"
          @all-changes-saved="onAllChangesSaved"
          class="q-mb-xl"
        />

        <!-- 操作区域 -->
        <q-card v-if="importedData" class="q-mt-lg">
          <q-card-section>
            <div class="text-h6">导入的数据</div>
            <div class="text-caption text-grey-7">
              文件名: {{ importedData.fileName }} |
              Sheet数量: {{ importedData.sheets.length }} |
              总行数: {{ getTotalRows(importedData) }}
              <span v-if="importedData.hasUnsavedChanges" class="text-warning q-ml-sm">
                (有{{ importedData.totalUnsavedChanges }}处未保存修改)
              </span>
            </div>
          </q-card-section>

          <q-card-section>
            <div class="row q-gutter-sm">

              <q-btn
                color="secondary"
                icon="analytics"
                label="显示数据结构"
                @click="showDataStructure"
              />

              <q-btn
                color="positive"
                icon="cloud_upload"
                label="提交到服务器"
                @click="submitToServer"
                :loading="submitting"
              />

              <q-btn
                color="grey-7"
                icon="code"
                label="在控制台查看"
                @click="logToConsole"
                outline
              />

            </div>
          </q-card-section>


        </q-card>



        <!-- 数据预览对话框 -->
        <q-dialog v-model="previewDialog" full-width>
          <q-card>
            <q-card-section class="row items-center">
              <div class="text-h6">数据预览 - {{ previewSheetData?.name }}</div>
              <q-space />
              <q-btn icon="close" flat round dense v-close-popup />
            </q-card-section>

            <q-card-section>
              <div class="q-mb-md">
                <span class="text-weight-medium">表头: </span>
                <span class="text-primary">{{ previewSheetData?.headers.join(', ') }}</span>
              </div>

              <q-table
                :rows="previewSheetData?.data.slice(0, 10) || []"
                :columns="previewSheetData?.headers.map((h, i) => ({
                  name: `col_${i}`,
                  label: h,
                  field: `col_${i}`,
                  align: 'left'
                }))"
                dense
                flat
                bordered
                class="preview-table"
              >
                <template v-slot:body="props">
                  <q-tr :props="props">
                    <q-td v-for="col in props.cols" :key="col.name" :props="props">
                      {{ props.row[col.field] || '-' }}
                    </q-td>
                  </q-tr>
                </template>

                <template v-slot:no-data>
                  <div class="full-width row flex-center text-grey">
                    无数据
                  </div>
                </template>
              </q-table>

              <div class="text-caption text-grey-7 q-mt-sm">
                显示前10行数据，共{{ previewSheetData?.rowCount }}行
              </div>
            </q-card-section>
          </q-card>
        </q-dialog>
      </div>
    </div>
  </q-page>
</template>

<script>
import { ref } from 'vue';
import { useQuasar } from 'quasar';
// import ExcelImportComponent from '/src/compenents/ExcelImportComponent.vue';
import ExcelImportComponent from 'src/components/ExcelImportComponent.vue';

export default {
  name: 'ParentComponent',

  components: {
    ExcelImportComponent
  },

  setup() {
    const $q = useQuasar();
    const excelImporter = ref(null);
    const importedData = ref(null);
    const submitting = ref(false);
    const previewDialog = ref(false);
    const previewSheetData = ref(null);
    const selectedCellInfo = ref(null);

    const onExcelLoaded = (event) => {
      console.log('Excel文件已加载:', event);
      $q.notify({
        message: `文件"${event.fileName}"加载成功`,
        color: 'positive',
        icon: 'check_circle',
        timeout: 3000
      });
    };

    const onGetExcelData = (data) => {
      console.log('获取到Excel数据:', data);
      importedData.value = data;
    };

    const onReset = () => {
      importedData.value = null;
      selectedCellInfo.value = null;
      console.log('组件已重置');
    };

    const onCellUpdated = (event) => {
      console.log('单元格已更新:', event);
      $q.notify({
        message: `单元格已修改: ${event.originalValue} → ${event.newValue}`,
        color: 'info',
        icon: 'edit',
        timeout: 2000
      });
    };

    // const onCellSelected = (event) => {
    //   console.log('单元格被选中:', event);
    //   selectedCellInfo.value = {
    //     sheetIndex: event.sheetIndex,
    //     sheetName: event.sheetName,
    //     rowIndex: event.rowIndex,
    //     colField: event.colField,
    //     value: event.value
    //   };
    // };

    const onChangesSaved = (event) => {
      console.log('修改已保存:', event);
      $q.notify({
        message: `已保存 ${event.totalSaved} 处修改`,
        color: 'positive',
        icon: 'check',
        timeout: 2000
      });
    };

    const onAllChangesSaved = (event) => {
      console.log('所有修改已保存:', event);
    };

    const getTotalRows = (data) => {
      if (!data || !data.sheets) return 0;
      return data.sheets.reduce((total, sheet) => total + sheet.rowCount, 0);
    };

    const saveDataToState = () => {
      if (!importedData.value) {
        $q.notify({
          message: '没有可保存的数据',
          color: 'warning',
          icon: 'warning'
        });
        return;
      }

      // 这里可以将数据保存到Vuex/Pinia状态管理
      // 例如: store.commit('SET_IMPORTED_DATA', importedData.value);

      $q.notify({
        message: `已保存 ${importedData.value.sheets.length} 个Sheet的数据`,
        color: 'positive',
        icon: 'save',
        timeout: 3000
      });
    };

    const showDataStructure = () => {
      if (!importedData.value) {
        $q.notify({
          message: '没有可显示的数据',
          color: 'warning',
          icon: 'warning'
        });
        return;
      }

      $q.dialog({
        title: '数据结构',
        message: `
          <div>
            <p><strong>文件名:</strong> ${importedData.value.fileName}</p>
            <p><strong>Sheet数量:</strong> ${importedData.value.sheets.length}</p>
            <p><strong>总行数:</strong> ${getTotalRows(importedData.value)}</p>
            <p><strong>未保存修改:</strong> ${importedData.value.totalUnsavedChanges || 0} 处</p>
            <hr>
            <p><strong>Sheet详情:</strong></p>
            <ul>
              ${importedData.value.sheets.map(sheet =>
                `<li>${sheet.name}: ${sheet.rowCount}行 × ${sheet.columnCount}列 ${sheet.hasUnsavedChanges ? '(有修改)' : ''}</li>`
              ).join('')}
            </ul>
          </div>
        `,
        html: true,
        ok: {
          label: '关闭',
          color: 'primary'
        }
      });
    };

    const submitToServer = async () => {
      if (!importedData.value) {
        $q.notify({
          message: '没有可提交的数据',
          color: 'warning',
          icon: 'warning'
        });
        return;
      }

      submitting.value = true;

      try {
        // 首先获取包含修改的数据
        const modifiedData = excelImporter.value.getModifiedData();

        // 模拟API调用
        await new Promise(resolve => setTimeout(resolve, 1500));

        $q.notify({
          message: `成功提交 ${getTotalRows(modifiedData)} 行数据到服务器`,
          color: 'positive',
          icon: 'cloud_done',
          timeout: 3000
        });
      } catch (error) {
        $q.notify({
          message: '提交失败: ' + error.message,
          color: 'negative',
          icon: 'error',
          timeout: 5000
        });
      } finally {
        submitting.value = false;
      }
    };

    const logToConsole = () => {
      if (!importedData.value) {
        console.warn('没有导入的数据');
        return;
      }

      console.log('导入的Excel数据:', importedData.value);
      $q.notify({
        message: '数据已在控制台输出',
        color: 'info',
        icon: 'code',
        timeout: 2000
      });
    };

    const showSheetPreview = (sheet) => {
      previewSheetData.value = sheet;
      previewDialog.value = true;
    };

    const editSelectedCell = () => {
      if (selectedCellInfo.value && excelImporter.value) {
        excelImporter.value.startEditing(
          selectedCellInfo.value.rowIndex,
          selectedCellInfo.value.colField,
          selectedCellInfo.value.sheetIndex
        );
      }
    };

    // 示例：通过ref调用组件方法
    const getDataFromComponent = () => {
      if (excelImporter.value) {
        const data = excelImporter.value.getExcelData(true);
        if (data) {
          console.log('通过ref获取的数据:', data);
          importedData.value = data;
        } else {
          console.warn('组件中没有数据');
        }
      }
    };

    // 示例：批量更新单元格
    const batchUpdateCells = () => {
      if (excelImporter.value) {
        const updates = [
          { sheetIndex: 0, rowIndex: 1, colField: 'col_0', value: '新值1' },
          { sheetIndex: 0, rowIndex: 2, colField: 'col_1', value: '新值2' },
          { sheetIndex: 0, rowIndex: 3, colField: 'col_2', value: '新值3' }
        ];

        excelImporter.value.updateCells(updates);

        $q.notify({
          message: `已批量更新 ${updates.length} 个单元格`,
          color: 'info',
          icon: 'edit',
          timeout: 2000
        });
      }
    };

    return {
      excelImporter,
      importedData,
      submitting,
      previewDialog,
      previewSheetData,
      selectedCellInfo,
      onExcelLoaded,
      onGetExcelData,
      onReset,
      onCellUpdated,
      onChangesSaved,
      onAllChangesSaved,
      getTotalRows,
      saveDataToState,
      showDataStructure,
      submitToServer,
      logToConsole,
      showSheetPreview,
      editSelectedCell,
      getDataFromComponent,
      batchUpdateCells
    };
  }
};
</script>

<style scoped>
.preview-table {
  max-height: 400px;
  overflow: auto;
}
</style>
