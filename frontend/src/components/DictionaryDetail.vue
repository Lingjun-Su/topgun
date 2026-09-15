<template>
  <q-form @submit="onSubmit" class="q-gutter-md">
    <div class="row q-col-gutter-sm">
      <q-input
        v-model="form.type"
        label="字典类型编码 (type)"
        class="col-12"
        outlined
        :readonly="!isEditing"
        :rules="[val => !!val || '必填']"
      />
      <q-input
        v-model="form.code"
        label="字典项编码 (code)"
        class="col-12"
        outlined
        :readonly="!isEditing"
        :rules="[val => !!val || '必填']"
      />
      <q-input
        v-model="form.label"
        label="字典项名称 (label)"
        class="col-12"
        outlined
        :readonly="!isEditing"
        :rules="[val => !!val || '必填']"
      />
      <q-input
        v-model.number="form.sort"
        type="number"
        label="排序权重"
        class="col-6"
        outlined
        :readonly="!isEditing"
      />
      <q-select
        v-model="form.status"
        :options="statusOptions"
        label="状态"
        class="col-6"
        outlined
        emit-value
        map-options
        :readonly="!isEditing"
      />
    </div>

    <div class="row justify-end q-mt-md q-gutter-sm">
      <template v-if="!isEditing">
        <q-btn label="修改" color="primary" icon="edit" @click="isEditing = true" />
      </template>
      <template v-else>
        <q-btn label="取消" color="grey" flat @click="onCancel" />
        <q-btn label="保存" color="positive" icon="save" type="submit" :loading="submitting" />
      </template>
    </div>
  </q-form>
</template>

<script setup>
import { ref, watch } from 'vue';
import { dictionaryApi } from 'src/api/dictionary'; // 导入刚才写的 API 模块

const props = defineProps({
  initialData: Object,
  isNew: Boolean
});
const emit = defineEmits(['saved', 'cancel']);

const isEditing = ref(props.isNew);
const submitting = ref(false);
const form = ref({ ...props.initialData });

const statusOptions = [
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 }
];

// 当外部传入的数据改变时（切换了列表行），重置表单
watch(() => props.initialData, (newVal) => {
  form.value = { ...newVal };
  isEditing.value = props.isNew;
}, { deep: true });

const onCancel = () => {
  if (props.isNew) emit('cancel');
  else {
    form.value = { ...props.initialData };
    isEditing.value = false;
  }
};

const onSubmit = async () => {
  submitting.value = true;
  try {
    // 这里调用 Laravel API (axios.post 或 axios.patch)
    if(form.value.id==undefined||form.value.id==''){//新增
      console.log("new");
      const response = await dictionaryApi.store(form.value);
      console.log(response);
    }else{
      console.log("edit",form.value.id)
      const payload = {
        type: form.value.type,
        code: form.value.code,
        label: form.value.label,
        sort: form.value.sort,
        status: form.value.status
      };
      const response =await dictionaryApi.update(form.value.id,payload);

      console.log(response);
    }
    console.log(form.value);
    emit('saved', form.value);
    isEditing.value = false;
  } finally {
    submitting.value = false;
  }
};
</script>
