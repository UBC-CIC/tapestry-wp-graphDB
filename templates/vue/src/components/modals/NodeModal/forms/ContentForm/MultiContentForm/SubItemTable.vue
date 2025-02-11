<template>
  <div id="modal-permissions">
    <b-form-group :label="isPopups ? 'Pop-up nodes' : 'Sub-items'">
      <b-table-simple
        class="text-center mb-0"
        striped
        responsive
        data-qa="sub-item-table"
      >
        <b-tbody>
          <b-tr v-for="subItemNode in subItemNodes" :key="subItemNode.id">
            <b-td class="text-left text-capitalize">
              {{ subItemNode.title }}
            </b-td>
            <b-td class="text-right">
              <b-link @click="handleEdit(subItemNode.id)">Edit</b-link>
            </b-td>
          </b-tr>
        </b-tbody>
      </b-table-simple>
      <p v-if="requiresSaving" class="my-2 p-0 text-muted small">
        To add new {{ isPopups ? "pop-up nodes" : "sub-items" }}, please save this
        node.
      </p>
      <div class="buttons-container" :style="buttonContainerStyle">
        <b-button
          class="add-subitem-button"
          data-qa="add-subitem"
          variant="primary"
          :disabled="requiresSaving"
          @click="addSubitem"
        >
          <i class="fas fa-plus icon"></i>
          Add New {{ isPopups ? "Pop-up node" : "Sub-item" }}
        </b-button>
        <b-button
          v-if="requiresSaving"
          class="save-node-button"
          data-qa="save-node-button"
          variant="primary"
          @click="handleSave"
        >
          Save
        </b-button>
      </div>
    </b-form-group>
  </div>
</template>

<script>
import { mapGetters } from "vuex"
import { names } from "@/config/routes"

export default {
  name: "sub-item-table",
  props: {
    node: {
      type: Object,
      required: true,
    },
    actionType: {
      type: String,
      required: true,
    },
    isPopups: {
      type: Boolean,
      required: false,
      default: false,
    },
  },
  computed: {
    ...mapGetters(["getNode", "getDirectChildren"]),
    requiresSaving() {
      // Require saving if node is changing from non-multi-content to multi-content
      const node = this.getNode(this.node.id)
      return this.actionType === "add" || node.mediaType !== "multi-content"
    },
    buttonContainerStyle() {
      return this.subItemNodes.length > 0 ? "margin-top: 20px" : ""
    },
    subItemNodes() {
      return this.node.childOrdering
        .map(this.getNode)
        .filter(node => !this.isPopups || node.popup)
    },
  },
  methods: {
    addSubitem() {
      this.$router.push({
        name: names.MODAL,
        params: { nodeId: this.node.id, type: "add", tab: "content" },
        query: { ...this.$route.query, nav: "modal" },
      })
    },
    handleEdit(nodeId) {
      this.$router.push({
        name: names.MODAL,
        params: { nodeId: nodeId, type: "edit", tab: "content" },
        query: { ...this.$route.query, nav: "modal" },
      })
    },
    handleSave() {
      this.$root.$emit("add-node")
    },
  },
}
</script>
