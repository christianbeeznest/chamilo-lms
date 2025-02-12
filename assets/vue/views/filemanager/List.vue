<template>
  <div v-if="!isLoading">
    <div class="flex border-b border-gray-200">
      <button
        :class="{
          'border-blue-500 text-blue-600': activeTab === 'personalFiles',
          'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300': activeTab !== 'personalFiles',
        }"
        class="px-4 py-2 -mb-px font-semibold border-b-2"
        @click="changeTab('personalFiles')"
      >
        {{ t("Personal Files") }}
      </button>
      <button
        v-if="isAllowedToEdit && courseIsSet"
        :class="{
          'border-blue-500 text-blue-600': activeTab === 'documents',
          'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300': activeTab !== 'documents',
        }"
        class="px-4 py-2 -mb-px font-semibold border-b-2"
        @click="changeTab('documents')"
      >
        {{ t("Documents") }}
      </button>
    </div>

    <div
      v-if="activeTab === 'personalFiles'"
      class="mt-4"
    >
      <PersonalFiles />
    </div>

    <div
      v-if="activeTab === 'documents' && isAllowedToEdit && courseIsSet"
      class="mt-4"
    >
      <CourseDocuments />
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue"
import { useRoute, useRouter } from "vue-router"
import PersonalFiles from "../../components/filemanager/PersonalFiles.vue"
import CourseDocuments from "../../components/filemanager/CourseDocuments.vue"
import { checkIsAllowedToEdit } from "../../composables/userPermissions"
import { useI18n } from "vue-i18n"
import { useCidReqStore } from "../../store/cidReq"
import { storeToRefs } from "pinia"

const route = useRoute()
const router = useRouter()

const activeTab = ref(route.query.tab || "personalFiles")
const isAllowedToEdit = ref(false)
const isLoading = ref(true)
const { t } = useI18n()

const cidReqStore = useCidReqStore()
const { course } = storeToRefs(cidReqStore)
const courseIsSet = ref(false)

const changeTab = (tab) => {
  activeTab.value = tab
  router.replace({ query: { ...route.query, tab } })
}

watch(route, (newRoute) => {
  if (newRoute.query.tab !== activeTab.value) {
    activeTab.value = newRoute.query.tab || "personalFiles"
  }
})

onMounted(async () => {
  isAllowedToEdit.value = await checkIsAllowedToEdit()
  courseIsSet.value = !!course.value
  isLoading.value = false
})
</script>
