<template>
  <q-page padding>
    <div class="q-pr-md">
      <master-detail-layout
        :has-selection="!!selectedItem"
        v-model:showDialog="showMobileDialog"
        @close-detail="selectedItem = null"
      >

        <template #list>
          <OrganizationTree :show-delete-action="true" @select="getId" />
        </template>
        <template #detail>
          <organization-detail
            :initial-data="selectedItem"
            :is-new="isCreateMode"
            @saved="handleSaved"
            @cancel="onDetailCancel"
          ></organization-detail>
        </template>
      </master-detail-layout>

    </div>
  </q-page>
<div >here2</div>
</template>
<script setup>
import { ref } from 'vue';

import MasterDetailLayout from 'src/components/MasterDetailLayout.vue';
import OrganizationDetail from 'src/components/OrganizationDetail.vue';
import OrganizationTree from 'src/components/OrganizationTree.vue';

const selectedItem = ref(null); // 当前正在编辑的条目对象
const isCreateMode = ref(null); // 当前正在编辑的条目对象
// selectedItem.value =0;
const getId=(id)=>{
  console.log("select",selectedItem.value,id)
  selectedItem.value =id;
  isCreateMode.value =true;
}
</script>
