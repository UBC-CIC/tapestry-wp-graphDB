<template>
  <b-modal
    v-if="node"
    id="node-modal"
    :visible="show"
    size="lg"
    class="text-muted"
    scrollable
    :header-class="isMultiContentNodeChild ? 'modal-header-small' : ''"
    body-class="p-0"
    @hide="handleClose"
  >
    <template #modal-title>
      <span v-if="isMultiContentNodeChild">
        <b-link
          v-for="(parentNode, index) in multiContentChildParents"
          :key="parentNode.id"
          class="nav-item modal-header-link"
          :data-qa="
            index == multiContentChildParents.length - 1
              ? 'node-modal-header-back'
              : ''
          "
          @click="gotoEdit(parentNode.id)"
        >
          {{ parentNode.title }}
          <i class="fas fa-chevron-right fa-xs mx-2" />
        </b-link>
      </span>
      <span data-qa="node-modal-header">
        {{ title }}
      </span>
    </template>
    <b-container fluid class="px-0" data-qa="node-modal">
      <b-overlay :show="loading" variant="white">
        <div v-if="hasSubmissionError" class="error-wrapper">
          <h5>Operation failed due to the following error(s):</h5>
          <ul>
            <li v-for="error in errors" :key="error">{{ error }}</li>
          </ul>
        </div>
        <b-tabs card :class="{ 'has-errors': hasSubmissionError }">
          <b-tab
            title="Content"
            :active="tab === 'content'"
            style="overflow: hidden;"
            @click="changeTab('content')"
          >
            <content-form
              :node="node"
              :parent="parent"
              :actionType="type"
              :maxDescriptionLength="maxDescriptionLength"
              @load="videoLoaded = true"
              @unload="videoLoaded = false"
              @type-changed="handleTypeChange"
            />
          </b-tab>
          <b-tab
            title="References"
            :active="tab === 'references'"
            @click="changeTab('references')"
          >
            <references-form :node="node" />
          </b-tab>
          <b-tab
            title="Appearance"
            :active="tab === 'appearance'"
            @click="changeTab('appearance')"
          >
            <appearance-form
              :node="node"
              :is-page-child="isPageMultiConentNodeChild"
            />
          </b-tab>
          <b-tab
            v-if="node.mediaType === 'h5p' || node.mediaType === 'video'"
            :active="tab === 'behaviour'"
            title="Behaviour"
            @click="changeTab('behaviour')"
          >
            <behaviour-form :node="node" />
          </b-tab>
          <b-tab
            v-if="viewAccess"
            title="Access"
            :active="tab === 'access'"
            @click="changeTab('access')"
          >
            <h6 class="mb-3">Node Permissions</h6>
            <b-card no-body>
              <permissions-table v-model="node.permissions" />
            </b-card>
            <h6 class="mt-4 mb-3">Lock Node</h6>
            <conditions-form :node="node" />
          </b-tab>
          <b-tab
            v-if="node.mediaType === 'h5p' || node.mediaType === 'video'"
            title="Activity"
            :active="tab === 'activity'"
            @click="changeTab('activity')"
          >
            <activity-form :node="node" />
          </b-tab>
          <b-tab
            v-if="node.hasMultiContentChild"
            title="Ordering"
            :active="tab === 'ordering'"
            @click="changeTab('ordering')"
          >
            <div>
              <slick-list
                :value="node.childOrdering"
                lock-axis="y"
                @input="updateOrderingArray"
              >
                <slick-item
                  v-for="(childId, index) in node.childOrdering"
                  :key="index"
                  class="slick-list-item"
                  :index="index"
                  style="z-index: 9999 !important;"
                >
                  <span class="fas fa-bars fa-xs"></span>
                  <span>{{ getNode(childId).title }}</span>
                  <span style="color: grey;">id: {{ childId }}</span>
                </slick-item>
              </slick-list>
            </div>
          </b-tab>
          <b-tab
            v-if="settings.renderMap"
            title="Geography"
            :active="tab === 'coordinates'"
            @click="changeTab('coordinates')"
          >
            <coordinates-form :node="node" />
          </b-tab>
          <b-tab
            title="Copyright"
            :active="tab === 'copyright'"
            @click="changeTab('copyright')"
          >
            <copyright-form :node="node" />
          </b-tab>
        </b-tabs>
      </b-overlay>
    </b-container>
    <template slot="modal-footer">
      <b-overlay :show="loading || fileUploading" variant="white" class="w-100">
        <template>
          <div class="buttons-container d-flex w-100">
            <delete-node-button
              v-if="type === 'edit'"
              :node-id="Number(nodeId)"
              :disabled="loading || fileUploading"
              :isMultiContentNodeChild="isMultiContentNodeChild"
              @submit="loading = true"
              @setLoading="setLoading"
              @message="setDisabledMessage"
              @complete="handleDeleteComplete"
            ></delete-node-button>
            <span style="flex-grow:1;"></span>
            <b-button
              size="sm"
              variant="light"
              :disabled="loading || fileUploading"
              @click="handleClose"
            >
              Cancel
            </b-button>
            <b-button
              v-if="rootId !== 0 && canMakeDraft"
              id="draft-button"
              size="sm"
              variant="secondary"
              :disabled="loading || fileUploading || fieldsInvalid"
              @click="handleDraftSubmit"
            >
              <span>Save as Private Draft</span>
            </b-button>
            <b-button
              v-if="canPublish"
              id="submit-button"
              data-qa="submit-node-modal"
              size="sm"
              variant="primary"
              :disabled="loading || fileUploading || fieldsInvalid"
              @click="handlePublish"
            >
              <span>Publish</span>
            </b-button>
            <b-button
              v-else-if="settings.submitNodesEnabled"
              data-qa="submit-node-modal"
              size="sm"
              variant="primary"
              :disabled="!canMakeDraft || loading || fileUploading"
              @click="handleSubmitForReview"
            >
              <span>
                {{ wasRejected ? "Re-submit" : "Submit" }}
                to Administrators for Review
              </span>
            </b-button>
          </div>
          <b-form-invalid-feedback :state="canMakeDraft">
            {{ warningText }}
            <br v-if="warningText" />
            {{ deleteWarningText }}
          </b-form-invalid-feedback>
          <b-form-invalid-feedback
            :state="!hasUnsavedChanges"
            class="text-right font-weight-bold"
          >
            You have unsaved changes
          </b-form-invalid-feedback>
        </template>
        <template #overlay>
          <span
            :title="
              loading
                ? 'Loading... Please wait.'
                : 'Please wait for the file to upload.'
            "
          >
            ...
          </span>
        </template>
      </b-overlay>
    </template>
    <div v-if="loadDuration">
      <iframe
        v-if="node.mediaType === 'h5p'"
        ref="frame"
        class="duration-calculator"
        :src="node.typeData.mediaURL"
        @load="setH5pDuration"
      ></iframe>
      <video
        v-if="node.mediaFormat === 'mp4'"
        ref="video"
        :src="node.typeData.mediaURL"
        style="display: none;"
        @loadeddata="setVideoDuration"
        @error="handleVideoFrameError"
      ></video>
      <youtube
        v-if="node.mediaFormat === 'youtube'"
        :video-id="node.typeData.youtubeID"
        :player-vars="{ autoplay: 0 }"
        style="display: none;"
        @ready="setYouTubeDuration"
      ></youtube>
    </div>
  </b-modal>
</template>

<script>
import { mapActions, mapGetters, mapMutations, mapState } from "vuex"
import { SlickList, SlickItem } from "vue-slicksort"
import ActivityForm from "./forms/ContentForm/ActivityForm"
import AppearanceForm from "./forms/AppearanceForm"
import BehaviourForm from "./forms/BehaviourForm"
import ConditionsForm from "./forms/ConditionsForm"
import CoordinatesForm from "./forms/CoordinatesForm"
import ContentForm from "./forms/ContentForm"
import CopyrightForm from "./forms/CopyrightForm"
import ReferencesForm from "./forms/ReferencesForm"
import PermissionsTable from "../common/PermissionsTable"
import DeleteNodeButton from "./DeleteNodeButton"
import { names } from "@/config/routes"
import Helpers from "@/utils/Helpers"
import * as Comment from "@/utils/comments"
import { sizes, nodeStatus } from "@/utils/constants"
import { getLinkMetadata } from "@/services/LinkPreviewApi"
import DragSelectModular from "@/utils/dragSelectModular"
import * as wp from "@/services/wp"

const shouldFetch = (url, selectedNode) => {
  if (!selectedNode.typeData.linkMetadata) {
    return true
  }
  const oldUrl = selectedNode.typeData.linkMetadata.url
  return oldUrl != Helpers.normalizeUrl(url)
}

export default {
  name: "node-modal",
  components: {
    AppearanceForm,
    BehaviourForm,
    ContentForm,
    ActivityForm,
    ConditionsForm,
    CoordinatesForm,
    CopyrightForm,
    ReferencesForm,
    SlickItem,
    SlickList,
    PermissionsTable,
    DeleteNodeButton,
  },
  data() {
    return {
      loading: false,
      userId: null,
      errors: [],
      maxDescriptionLength: 2000,
      node: null,
      videoLoaded: false,
      fileUploading: false,
      loadDuration: false,
      warningText: "",
      deleteWarningText: "",
      keepOpen: false,
    }
  },
  computed: {
    ...mapGetters([
      "createDefaultNode",
      "getDirectChildren",
      "getParent",
      "getNode",
      "getNeighbours",
    ]),
    ...mapState([
      "nodes",
      "rootId",
      "settings",
      "visibleNodes",
      "apiError",
      "returnRoute",
    ]),
    parent() {
      const parent = this.getNode(
        this.type === "add" ? this.nodeId : this.getParent(this.nodeId)
      )
      return parent ? parent : null
    },
    title() {
      if (this.type === "add") {
        return this.parent
          ? `Add new sub-topic to ${this.parent.title}`
          : "Add root node"
      } else if (this.type === "edit") {
        return `Edit node: ${this.node.title}`
      }
      return ""
    },
    wasRejected() {
      return this.node.reviewStatus === nodeStatus.REJECT
    },
    isAuthenticated() {
      return wp.isLoggedIn()
    },
    viewAccess() {
      return this.settings.showAccess === undefined
        ? true
        : this.settings.showAccess
        ? true
        : wp.canEditTapestry()
    },
    linkHasThumbnailData() {
      return (
        (this.node.mediaType === "url-embed" && this.node.behaviour !== "embed") ||
        this.node.mediaFormat === "youtube"
      )
    },
    canPublish() {
      if (this.type === "add") {
        return (
          Helpers.hasPermission(this.parent, this.type) &&
          (!this.parent || this.parent.status !== "draft")
        )
      } else if (this.node.status === "draft" && this.type === "edit") {
        return this.getNeighbours(this.nodeId).some(neighbourId => {
          let neighbour = this.getNode(neighbourId)
          return (
            neighbour.status !== "draft" && Helpers.hasPermission(neighbour, "add")
          )
        })
      } else {
        return Helpers.hasPermission(this.node, this.type)
      }
    },
    authoredNode() {
      if (this.node.author) {
        return wp.isCurrentUser(this.node.author.id)
      }
      return true
    },
    canMakeDraft() {
      const { id } = wp.getCurrentUser()
      if (this.node.status === "publish" && this.type === "edit") {
        return false
      }
      return this.hasDraftPermission(id)
    },
    canEditTapestry() {
      return wp.canEditTapestry()
    },
    fieldsInvalid() {
      if (
        this.node.mapCoordinates &&
        (this.node.mapCoordinates.lng !== "" || this.node.mapCoordinates.lat !== "")
      ) {
        return (
          this.node.mapCoordinates.lat > 90 ||
          this.node.mapCoordinates.lat < -90 ||
          this.node.mapCoordinates.lng > 180 ||
          this.node.mapCoordinates.lng < -180 ||
          this.node.mapCoordinates.lng === "" ||
          this.node.mapCoordinates.lat === ""
        )
      }
      return false
    },
    nodeId() {
      const rowId = this.$route.params.rowId
      const nodeId = this.$route.params.nodeId
      return rowId || nodeId || Number(nodeId)
    },
    show() {
      return this.$route.name === names.MODAL
    },
    tab() {
      return this.$route.params.tab || ""
    },
    type() {
      return this.$route.params.type || ""
    },
    hasSubmissionApiError() {
      return this.apiError
    },
    hasSubmissionError() {
      return this.errors.length
    },
    hasUnsavedChanges() {
      const oldNode = this.getNode(this.nodeId)
      return this.type === "add" || !Helpers.nodeEqual(oldNode, this.node)
    },
    isMultiContentNodeChild() {
      return this.parent && this.parent.mediaType == "multi-content"
    },
    isPageMultiConentNodeChild() {
      return (
        !!this.isMultiContentNodeChild && this.parent?.presentationStyle === "page"
      )
    },
    multiContentChildParents() {
      let parents = []
      let parentId
      let parent = this.parent
      while (parent != null) {
        parents.unshift(parent)
        parentId = this.getParent(parent.id)
        parent = parentId ? this.getNode(parentId) : null
      }
      return parents
    },
    isMultipleChoiceValueValid() {
      const questionsWithMultipleChoiceEnabled = this.node.typeData.activity.questions.filter(
        question => {
          return question.answerTypes.multipleChoice.enabled
        }
      )
      const validMultipleChoiceValues = questionsWithMultipleChoiceEnabled.every(
        question => {
          return question.answerTypes.multipleChoice.choices.every(option => {
            return option.value != ""
          })
        }
      )
      return validMultipleChoiceValues
    },
    isMultipleChoiceImageValid() {
      const questionsWithMultipleChoiceEnabled = this.node.typeData.activity.questions.filter(
        question => {
          return question.answerTypes.multipleChoice.enabled
        }
      )
      const validMultipleChoiceImages = questionsWithMultipleChoiceEnabled.every(
        question => {
          const useImages = question.answerTypes.multipleChoice.useImages
          if (useImages) {
            return question.answerTypes.multipleChoice.choices.every(option => {
              return option.imageUrl != "" && option.imageUrl != null
            })
          } else if (!useImages) {
            return true
          }
        }
      )
      return validMultipleChoiceImages
    },
  },
  watch: {
    nodeId: {
      immediate: true,
      handler() {
        if (this.show) {
          if (this.isValid()) {
            this.initialize()
          }
        }
      },
    },
    show: {
      immediate: true,
      handler(show) {
        if (show) {
          if (this.isValid()) {
            DragSelectModular.removeDragSelectListener()
            this.loading = false
            this.initialize()
          }
        } else {
          DragSelectModular.addDragSelectListener()
        }
      },
    },
    type() {
      this.initialize()
    },
    tab: {
      immediate: true,
      handler() {
        if (this.show) {
          this.isValid()
        }
      },
    },
    hasSubmissionApiError() {
      if (this.apiError) {
        this.errors.push(this.apiError.error)
      }
    },
    hasSubmissionError() {
      if (this.hasSubmissionError) {
        this.loading = false
      }
    },
  },
  mounted() {
    this.$root.$on("node-modal::uploading", isUploading => {
      this.fileUploading = isUploading
    })
    this.$root.$on("fileID", fileId => {
      if (fileId.thumbnailType == "locked") {
        this.node.lockedThumbnailFileId = fileId.data
      } else if (fileId.thumbnailType == "thumbnail") {
        this.node.thumbnailFileId = fileId.data
      }
    })
    this.$root.$on("add-node", () => {
      this.keepOpen = true
      this.handlePublish()
    })
    this.$root.$on("remove-thumbnail", thumbnailType => {
      if (thumbnailType == "thumbnail") {
        this.node.imageURL = ""
        this.node.thumbnailFileId = ""
      } else {
        this.node.lockedImageURL = ""
        this.node.lockedThumbnailFileId = ""
      }
    })
    this.initialize()
  },
  methods: {
    ...mapMutations(["updateRootNode", "setReturnRoute"]),
    ...mapActions([
      "addNode",
      "addLink",
      "updateNode",
      "updateLockedStatus",
      "setTapestryErrorReporting",
    ]),
    setLoading(status) {
      this.loading = status
    },
    isValid() {
      const isNodeValid = this.validateNodeRoute(this.nodeId)
      if (!isNodeValid) {
        this.$router.replace({
          name: names.APP,
          params: { nodeId: this.nodeId },
        })
        return false
      }
      const isTabValid = this.validateTab(this.tab)
      if (!isTabValid) {
        this.$router.replace({
          name: names.MODAL,
          params: { nodeId: this.nodeId, type: this.type, tab: "content" },
          query: this.$route.query,
        })
      }
      return true
    },
    validateNodeRoute(nodeId) {
      if (this.type === "add") {
        if (Object.keys(this.nodes).length === 0 || this.isAuthenticated) {
          return true
        }
      }
      if (!this.nodes.hasOwnProperty(nodeId)) {
        return false
      }
      const isAllowed = Helpers.hasPermission(this.getNode(nodeId), this.type)
      const messages = {
        edit: `You don't have permission to edit this node`,
        add: `You don't have permission to add to this node`,
      }
      if (!isAllowed && this.type in messages) {
        alert(messages[this.type])
      }
      return isAllowed
    },
    initialize() {
      this.errors = []
      let copy = this.createDefaultNode()
      if (this.type === "edit") {
        const node = this.getNode(this.nodeId)
        copy = Helpers.deepCopy(node)
      }
      copy.hasMultiContentChild = this.hasMultiContentChild(copy)
      if (!copy.mapCoordinates) {
        copy.mapCoordinates = {
          lat: "",
          lng: "",
        }
      }
      this.node = copy
      this.setTapestryErrorReporting(false)
    },
    validateTab(requestedTab) {
      // Tabs that are valid for ALL node types and modal types
      const okTabs = [
        "content",
        "references",
        "appearance",
        "copyright",
        "coordinates",
      ]
      if (okTabs.includes(requestedTab)) {
        return true
      }

      // If requested tab is access, check if the user can access it
      if (requestedTab === "access") {
        return this.viewAccess
      }

      switch (requestedTab) {
        case "activity": {
          return this.node.mediaType === "h5p" || this.node.mediaType === "video"
        }
        case "behaviour": {
          return this.node.mediaType === "h5p" || this.node.mediaType === "video"
        }
        case "ordering": {
          return this.node.hasMultiContentChild
        }
      }

      return false
    },
    hasMultiContentChild(node) {
      if (this.parent) {
        const children = this.getDirectChildren(node.id)
        return children.length > 0
      }
      return node.mediaType === "multi-content"
    },
    setDisabledMessage(msg) {
      this.deleteWarningText = msg
    },
    changeTab(tab) {
      // Prevent multiple clicks
      if (tab !== this.tab) {
        this.$router.push({
          name: names.MODAL,
          params: { nodeId: this.nodeId, type: this.type, tab },
          query: this.$route.query,
        })
      }
    },
    handleDeleteComplete() {
      this.node = this.parent
      this.loading = false
      this.keepOpen = true
      this.close("delete")
    },
    handleClose(event) {
      if (
        this.hasUnsavedChanges &&
        (event.trigger == "backdrop" ||
          event.trigger == "headerclose" ||
          event.trigger == "esc" ||
          event instanceof MouseEvent) // cancel triggered
      ) {
        event.preventDefault()
        this.$bvModal
          .msgBoxConfirm("All unsaved changes will be lost.", {
            modalClass: "node-modal-confirmation",
            title: "Are you sure you want to continue?",
            okTitle: "Close",
          })
          .then(close => {
            if (close) {
              this.close()
            }
          })
          .catch(err => console.log(err))
      } else {
        this.close(event)
      }
    },
    close(event = null) {
      if (this.show) {
        if (Object.keys(this.nodes).length === 0) {
          this.$router.push({ path: "/", query: this.$route.query })
        } else if (this.returnRoute) {
          this.$router.push(this.returnRoute)
        } else if (this.keepOpen) {
          // Switch to edit mode if multi-content just added
          this.$router.push({
            name: names.MODAL,
            params: { nodeId: this.node.id, type: "edit", tab: "content" },
            query: this.$route.query,
          })
        } else if (this.rootId && !this.nodeId) {
          // We just added a root node
          this.$router.push({
            name: names.APP,
            params: { nodeId: this.rootId },
            query: this.$route.query,
          })
        } else if (
          this.isMultiContentNodeChild &&
          this.$route.query.nav === "modal"
        ) {
          // Prevent NodeModal from closing

          if (event) event.preventDefault()

          // Return to modal of parent node
          this.$router.push({
            name: names.MODAL,
            params: { nodeId: this.parent.id, type: "edit", tab: "content" },
            query: this.$route.query,
          })
        } else {
          this.$router.push({
            name: names.APP,
            params: { nodeId: this.nodeId },
            query: { ...this.$route.query, nav: undefined },
          })
        }
      }
      this.keepOpen = false
      this.setTapestryErrorReporting(true)
      this.setReturnRoute(null)
    },
    gotoEdit(nodeId) {
      if (this.hasUnsavedChanges) {
        this.$bvModal
          .msgBoxConfirm("All unsaved changes will be lost.", {
            modalClass: "node-modal-confirmation",
            title: "Are you sure you want to continue?",
            okTitle: "Close",
          })
          .then(close => {
            if (close) {
              this.$router.push({
                name: names.MODAL,
                params: { nodeId, type: "edit", tab: "content" },
              })
            }
          })
          .catch(err => console.log(err))
      } else {
        this.$router.push({
          name: names.MODAL,
          params: { nodeId, type: "edit", tab: "content" },
        })
      }
    },
    async handleSubmit() {
      this.errors = this.validateNode()
      if (!this.hasSubmissionError) {
        this.loading = true
        this.updateNodeCoordinates()

        if (this.linkHasThumbnailData) {
          await this.setLinkData()
        }

        if (this.shouldReloadDuration()) {
          this.loadDuration = true
        } else {
          return this.submitNode()
        }
      }
    },
    handlePublish() {
      this.node.status = nodeStatus.PUBLISH
      this.handleSubmit()
    },
    handleDraftSubmit() {
      this.node.status = nodeStatus.DRAFT
      this.handleSubmit()
    },
    handleSubmitForReview() {
      if (!this.settings.draftNodesEnabled || !this.settings.submitNodesEnabled) {
        return
      }
      this.node.reviewStatus = nodeStatus.SUBMIT
      this.node.status = nodeStatus.DRAFT

      this.node.reviewComments.push(
        Comment.createComment(Comment.types.STATUS_CHANGE, {
          from: null,
          to: nodeStatus.SUBMIT,
        })
      )

      this.handleSubmit()
    },
    async submitNode() {
      if (this.type === "add") {
        const id = await this.addNode(this.node)
        this.node.id = id
        if (this.parent) {
          // Add link from parent node to this node
          const newLink = {
            source: this.parent.id,
            target: id,
            value: 1,
            type: "",
            addedOnNodeCreation: true,
          }
          await this.addLink(newLink)
          // do not update parent's child ordering if the current node is a draft node since draft shouldn't appear in multi-content nodes
          if (this.node.status !== "draft") {
            this.$store.commit("updateNode", {
              id: this.parent.id,
              newNode: {
                childOrdering: [...this.parent.childOrdering],
              },
            })
          }
        } else {
          this.updateRootNode(id)
        }
      } else {
        await this.updateNode({
          id: this.node.id,
          newNode: this.node,
        })
      }
      await this.updateLockedStatus()
      this.loading = false

      /**
       * Sometimes changes in the parent node causes changes in child nodes. For
       * example, when a node goes from a video to a non-video, all child popups
       * should be invalidated.
       */
      this.updateChildren()
      if (!this.hasSubmissionError) {
        this.close()
      }
    },
    updateChildren() {
      if (!["h5p", "video"].includes(this.node.mediaType)) {
        const children = this.getDirectChildren(this.node.id)
        children.forEach(childId => {
          const childNode = this.getNode(childId)
          if (childNode && childNode.popup) {
            this.updateNode({ id: childId, newNode: { popup: null } })
          }
        })
      }
    },
    getRandomNumber(min, max) {
      return Math.random() * (max - min) + min
    },
    coinToss() {
      return Math.floor(Math.random() * 2) == 0
    },
    calculateX(yIsCalculated) {
      if (!yIsCalculated) {
        if (this.coinToss()) {
          this.node.coordinates.x = this.getRandomNumber(
            this.parent.coordinates.x +
              sizes.NODE_RADIUS_SELECTED +
              sizes.NODE_RADIUS,
            this.parent.coordinates.x + sizes.NODE_RADIUS_SELECTED * 2
          )
        } else {
          this.node.coordinates.x = this.getRandomNumber(
            this.parent.coordinates.x -
              sizes.NODE_RADIUS_SELECTED -
              sizes.NODE_RADIUS,
            this.parent.coordinates.x - sizes.NODE_RADIUS_SELECTED * 2
          )
        }
        this.calculateY(true)
      } else {
        this.node.coordinates.x = this.getRandomNumber(
          this.parent.coordinates.x - sizes.NODE_RADIUS_SELECTED * 2,
          this.parent.coordinates.x + sizes.NODE_RADIUS_SELECTED * 2
        )
      }
    },
    calculateY(xIsCalculated) {
      if (!xIsCalculated) {
        if (this.coinToss()) {
          this.node.coordinates.y = this.getRandomNumber(
            this.parent.coordinates.y +
              sizes.NODE_RADIUS_SELECTED +
              sizes.NODE_RADIUS,
            this.parent.coordinates.y + sizes.NODE_RADIUS_SELECTED * 2
          )
        } else {
          this.node.coordinates.y = this.getRandomNumber(
            this.parent.coordinates.y -
              sizes.NODE_RADIUS_SELECTED -
              sizes.NODE_RADIUS,
            this.parent.coordinates.y - sizes.NODE_RADIUS_SELECTED * 2
          )
        }
        this.calculateX(true)
      } else {
        this.node.coordinates.y = this.getRandomNumber(
          this.parent.coordinates.y - sizes.NODE_RADIUS_SELECTED * 2,
          this.parent.coordinates.y + sizes.NODE_RADIUS_SELECTED * 2
        )
      }
    },
    updateNodeCoordinates() {
      if (this.type === "add" && this.parent) {
        this.coinToss() ? this.calculateX(false) : this.calculateY(false)
      }
    },
    validateNode() {
      const errMsgs = []

      if (this.node.title.length == 0) {
        errMsgs.push("Please enter a title")
      }
      if (
        this.node.description.replace(/<[^>]*>?/gm, "").length >
        this.maxDescriptionLength
      ) {
        errMsgs.push(
          "Please limit your description to under " +
            this.maxDescriptionLength +
            " characters"
        )
      }

      if (this.node.popup) {
        const { time } = this.node.popup
        if (time === "") {
          errMsgs.push(`Please enter a time for your popup.`)
        } else if (time <= 0) {
          errMsgs.push(`Please enter a time greater than 0.`)
        }
      }

      if (!this.node.mediaType) {
        errMsgs.push("Please select a Content Type")
      } else if (this.node.mediaType === "video") {
        if (!this.isValidVideo(this.node.typeData)) {
          errMsgs.push("Please enter a valid Video URL")
        }
        if (!Helpers.onlyContainsDigits(this.node.mediaDuration)) {
          this.node.mediaDuration = 0
        }
      } else if (this.node.mediaType === "h5p") {
        if (this.node.typeData.mediaURL === "") {
          errMsgs.push("Please select an H5P content for this node")
        }
        if (!Helpers.onlyContainsDigits(this.node.mediaDuration)) {
          this.node.mediaDuration = 0
        }
      } else if (this.node.mediaType === "url-embed") {
        if (this.node.typeData.mediaURL === "") {
          errMsgs.push("Please enter an Embed URL")
        }
      } else if (this.node.mediaType === "activity") {
        const validActivityTitles = this.node.typeData.activity.questions.every(
          question => {
            return question.text
          }
        )
        if (!validActivityTitles) {
          errMsgs.push("Please enter a question text for all questions")
        }

        const validActivityOptions = this.node.typeData.activity.questions.every(
          question => {
            const answerTypes = Object.values(question.answerTypes)
            return answerTypes.some(answerType => answerType.enabled)
          }
        )
        if (!validActivityOptions) {
          errMsgs.push("Please enable at least one answer type for each question")
        }

        const questionsWithPreviousActivity = this.node.typeData.activity.questions.filter(
          question => {
            return question.isFollowUp
          }
        )
        const validPreviousAnswers = questionsWithPreviousActivity.every(
          question => {
            const previousAnswer = question.followUp.questionId
            return previousAnswer
          }
        )
        if (!validPreviousAnswers) {
          errMsgs.push("Please select a previous activity to display")
        }
        if (
          this.node.typeData.activity.questions[0].answerTypes.text.allowMultiple
        ) {
          let listQuestion = this.node.typeData.activity.questions[0].answerTypes
            .text
          let minValue = parseInt(listQuestion.minFields, 10)
          let maxValue = parseInt(listQuestion.maxFields, 10)
          if (!Number.isInteger(minValue)) {
            errMsgs.push(
              "Please enter a valid number as the minimum number of fields"
            )
          } else if (minValue < 1) {
            errMsgs.push("Minimum number of fields cannot be less than 1")
          }
          if (!Number.isInteger(maxValue)) {
            errMsgs.push(
              "Please enter a valid number as the maximum number of fields"
            )
          } else if (maxValue < minValue) {
            errMsgs.push(
              "Please ensure minimum number of fields is less than or equal to the maximum number of fields"
            )
          }
        }
        const validMultipleChoiceValues = this.isMultipleChoiceValueValid
        if (!validMultipleChoiceValues) {
          errMsgs.push("Please enter a text for all multiple choice options")
        }
        const validMultipleChoiceImages = this.isMultipleChoiceImageValid
        if (!validMultipleChoiceImages) {
          errMsgs.push("Please upload an image for all multiple choice options")
        }

        // Drag and Drop form validation
        const dragDropQuestions = this.node.typeData.activity.questions.filter(
          question => question.answerTypes.dragDrop.enabled
        )

        const validBucketsText = dragDropQuestions.every(question => {
          return question.answerTypes.dragDrop.buckets.every(bucket => bucket.text)
        })
        if (!validBucketsText) {
          errMsgs.push("Please enter a name for all buckets")
        }

        const validItemsText = dragDropQuestions.every(question => {
          return question.answerTypes.dragDrop.items.every(item => item.text)
        })
        if (!validItemsText) {
          errMsgs.push("Please enter a name for all items")
        }

        const validItemsImages = dragDropQuestions
          .filter(question => question.answerTypes.dragDrop.useImages)
          .every(question => {
            return question.answerTypes.dragDrop.items.every(item => item.imageUrl)
          })
        if (!validItemsImages) {
          errMsgs.push(
            "Images must be uploaded for all drag and drop questions that have 'Use Images' enabled"
          )
        }
      } else if (this.node.mediaType === "answer") {
        const hasActivityId = this.node.typeData.activityId
        if (!hasActivityId) {
          errMsgs.push("Please select an activity")
        }

        const hasQuestionId = this.node.typeData.questionId
        if (!hasQuestionId) {
          errMsgs.push("Please select a question")
        }
      }
      return errMsgs
    },
    isValidVideo(typeData) {
      return (
        typeData.mediaURL !== "" &&
        (typeData.hasOwnProperty("youtubeID") || typeData.mediaURL.endsWith(".mp4"))
      )
    },
    updateOrderingArray(arr) {
      this.node.childOrdering = arr
    },
    handleTypeChange(evt) {
      if (evt === "multi-content") this.node.presentationStyle = "accordion"
    },
    async setLinkData() {
      if (shouldFetch(this.node.typeData.mediaURL, this.node)) {
        const url = this.node.typeData.mediaURL
        const { data } = await getLinkMetadata(url)

        if (data) {
          this.node.typeData.linkMetadata = data
          if (
            confirm(
              "Would you like to use the link preview image as the thumbnail image?"
            )
          ) {
            this.node.imageURL = data.image
          }
          if (
            confirm(
              "Would you like to use the link preview image as the locked thumbnail image?"
            )
          ) {
            this.node.lockedImageURL = data.image
          }
        }
      }
    },
    setVideoDuration() {
      this.node.mediaDuration = parseInt(this.$refs.video.duration)
      this.loadDuration = false
      return this.submitNode()
    },
    setYouTubeDuration(evt) {
      this.node.mediaDuration = evt.target.getDuration()
      this.loadDuration = false
      return this.submitNode()
    },
    setH5pDuration() {
      const frame = this.$refs.frame
      const h5p = frame.contentWindow.H5P
      if (h5p) {
        const instance = h5p.instances[0]
        const libraryName = instance.libraryInfo.machineName
        if (libraryName === "H5P.InteractiveVideo") {
          const h5pVideo = instance.video
          const handleH5PLoad = () => {
            this.node.mediaDuration = parseInt(h5pVideo.getDuration())
            this.loadDuration = false
            return this.submitNode()
          }
          if (h5pVideo.getDuration() !== undefined) {
            handleH5PLoad()
          } else {
            h5pVideo.on("loaded", handleH5PLoad)
          }
          return
        } else {
          this.node.mediaDuration = 0
        }
      }
      this.loadDuration = false
      return this.submitNode()
    },
    shouldReloadDuration() {
      if (this.node.mediaType !== "video" && this.node.mediaType !== "h5p") {
        return false
      }
      if (this.type === "add") {
        return true
      }
      const oldNode = this.getNode(this.nodeId)
      const { youtubeID, mediaURL } = oldNode.typeData
      return this.node.mediaFormat === "youtube"
        ? this.node.typeData.youtubeID !== youtubeID
        : this.node.mediaURL !== mediaURL
    },
    hasDraftPermission(ID) {
      if (!this.settings.draftNodesEnabled) {
        return false
      }
      if (ID === 0) {
        this.warningText = "You must be authenticated to create a draft node"
        return false
      }
      return true
    },
    handleVideoFrameError() {
      this.errors.push(
        "The video could not be found! Please re-upload or check the URL"
      )
      this.loadDuration = false
    },
  },
}
</script>

<style lang="scss" scoped>
.duration-calculator {
  position: fixed;
  left: 101vw;
  width: 1px;
}

h6 {
  font-weight: 400;
}
</style>

<style lang="scss">
/* Use non-scoped styles to overwrite WP theme styles */
table {
  border: 1px solid #dee2e6;

  th,
  td {
    word-break: unset;
    border: none;
  }
}

/* overwrite bootstrap styles */
.modal-header {
  background: #f7f7f7;
  border: none;
  padding-bottom: 0;
  margin-left: 5px;
  flex-direction: column;

  button.close {
    position: absolute;
    top: 15px;
    right: 12px;

    &:focus {
      outline: none;
    }
  }
}

.has-errors > .card-header {
  background: #f8d7da;
}

.modal-title {
  font-size: 1.5rem;
  font-weight: 600;
}

.nav-link:focus {
  outline: none;
}
</style>

<style lang="scss" scoped>
.spinner {
  padding: 3rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

#node-modal-container {
  * {
    outline: none;
  }

  .form-control {
    padding: 15px;
    border: none;
    background: #f1f1f1;
  }

  .modal-header-row {
    display: flex;
    justify-content: space-between;
    width: 100%;
    margin-bottom: 0;

    &:last-child {
      margin-bottom: 0;
    }
  }
}

.modal-header-row {
  display: flex;
  justify-content: space-between;
  width: 100%;
  margin-bottom: 8px;

  &:last-child {
    margin-bottom: 0;
  }
}

.modal-header-small {
  padding-top: 8px;
  padding-bottom: 8px;
}

.modal-header-link {
  font-size: 16px;
}

.error-wrapper {
  position: sticky;
  z-index: 2;
  top: 0;
  background: #f8d7da;
  color: #721c24;
  padding: 1em 1em 1px 2em;
}

.slick-list-item {
  display: flex;
  height: 25px;
  border: lightgray solid 1.5px;
  margin: 10px 25px;
  border-radius: 5px;
  padding: 15px;
  align-items: center;
  > span {
    margin-right: 25px;
  }
  > span:last-of-type {
    margin-left: auto;
  }
}

.indented-options {
  border-left: solid 2px #ccc;
  padding-left: 1em;
}

button:disabled {
  cursor: not-allowed;
}

.buttons-container > * {
  margin: 0.25rem !important;
}
</style>
