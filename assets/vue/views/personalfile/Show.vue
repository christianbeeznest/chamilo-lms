<template>
  <div>
    <Button
      :label="$t('Back')"
      icon="pi pi-chevron-left"
      @click="goBack"
    />
    <Toolbar
      v-if="item && isCurrentTeacher"
      :handle-delete="del"
      :handle-edit="editHandler"
    >
    </Toolbar>

    <p
      v-if="item"
      class="text-lg"
    >
      {{ item["title"] }}
    </p>

    <div
      v-if="item"
      class="flex flex-row"
    >
      <div class="w-1/2">
        <div
          v-if="item.resourceNode.firstResourceFile"
          class="flex justify-center"
        >
          <div class="w-64">
            <q-img
              v-if="item.resourceNode.firstResourceFile.image"
              :src="item['contentUrl'] + '&w=300'"
              spinner-color="primary"
            />
            <span v-else-if="item.resourceNode.firstResourceFile.video">
              <video controls>
                <source :src="item['contentUrl']" />
              </video>
            </span>
            <span v-else>
              <q-btn
                :to="item['downloadUrl']"
                class="btn btn--primary"
              >
                <v-icon icon="mdi-file-download" />
                {{ $t("Download file") }}
              </q-btn>
            </span>
          </div>
        </div>
        <div
          v-else
          class="flex justify-center"
        >
          <v-icon icon="mdi-folder" />
        </div>
      </div>

      <span class="w-1/2">
        <q-markup-table>
          <tbody>
            <tr>
              <td>
                <strong>{{ $t("Author") }}</strong>
              </td>
              <td>
                {{ item["resourceNode"].creator.username }}
              </td>
              <td></td>
              <td />
            </tr>
            <tr>
              <td>
                <strong>{{ $t("Comment") }}</strong>
              </td>
              <td>
                {{ item["comment"] }}
              </td>
            </tr>
            <tr>
              <td>
                <strong>{{ $t("Created at") }}</strong>
              </td>
              <td>
                {{ item["resourceNode"] ? relativeDatetime(item["resourceNode"].createdAt) : "" }}
              </td>
              <td />
            </tr>
            <tr>
              <td>
                <strong>{{ $t("Updated at") }}</strong>
              </td>
              <td>
                {{ item["resourceNode"] ? relativeDatetime(item["resourceNode"].updatedAt) : "" }}
              </td>
              <td />
            </tr>
            <tr v-if="item.resourceNode.firstResourceFile">
              <td>
                <strong>{{ $t("File") }}</strong>
              </td>
              <td>
                <div>
                  <a
                    :href="item['downloadUrl']"
                    class="btn btn--primary"
                  >
                    <v-icon icon="mdi-file-download" />
                    {{ $t("Download file") }}
                  </a>
                </div>
              </td>
              <td />
            </tr>
          </tbody>
        </q-markup-table>

        <hr />
        <ShowLinks :item="item" />
      </span>
    </div>

    <Loading :visible="isLoading" />
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex"
import { mapFields } from "vuex-map-fields"
import Loading from "../../components/Loading.vue"
import ShowMixin from "../../mixins/ShowMixin"
import Toolbar from "../../components/Toolbar.vue"

import ShowLinks from "../../components/resource_links/ShowLinks.vue"
import { useFormatDate } from "../../composables/formatDate"
import { useSecurityStore } from "../../store/securityStore"
import { storeToRefs } from "pinia"

const servicePrefix = "PersonalFile"

export default {
  name: "PersonalFileShow",
  components: {
    Loading,
    Toolbar,
    ShowLinks,
  },
  mixins: [ShowMixin],
  data() {
    const { relativeDatetime } = useFormatDate()
    const securityStore = useSecurityStore()

    const { isAuthenticated, isAdmin, isCurrentTeacher } = storeToRefs(securityStore)

    return {
      relativeDatetime,
      isAuthenticated,
      isAdmin,
      isCurrentTeacher,
    }
  },
  computed: {
    ...mapFields("personalfile", {
      isLoading: "isLoading",
    }),
    ...mapGetters("personalfile", ["find"]),
  },
  methods: {
    goBack() {
      this.$router.go(-1)
    },
    ...mapActions("personalfile", {
      deleteItem: "del",
      reset: "resetShow",
      retrieve: "loadWithQuery",
    }),
  },
  servicePrefix,
}
</script>
