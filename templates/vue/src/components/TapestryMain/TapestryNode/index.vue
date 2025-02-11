<template>
  <transition name="fade">
    <g
      v-show="show"
      ref="node"
      :data-qa="`node-${node.id}`"
      :data-locked="!node.accessible"
      :transform="`translate(${node.coordinates.x}, ${node.coordinates.y})`"
      :class="{
        opaque: !visibleNodes.includes(node.id),
        'has-thumbnail': node.thumbnailURL,
        'has-title': !node.hideTitle,
      }"
      :style="{
        cursor:
          node.accessible || hasPermission('edit') || hasPermission('move')
            ? 'pointer'
            : 'not-allowed',
      }"
      @click="handleClick"
      @mouseover="handleMouseover"
      @mouseleave="handleMouseleave"
    >
      <circle
        ref="circle"
        :data-qa="`node-circle-${node.id}`"
        :fill="fill"
        :stroke="progressBackgroundColor"
      ></circle>
      <transition name="fade">
        <circle
          v-show="(!node.hideTitle && !isHovered) || !node.accessible || selected"
          :r="radius"
          :fill="overlayFill"
          class="node-overlay"
          :class="selected ? 'selected' : !node.accessible ? 'locked' : 'normal'"
        ></circle>
      </transition>
      <progress-bar
        v-if="
          node.nodeType !== 'grandchild' &&
            node.nodeType !== '' &&
            !node.hideProgress
        "
        :x="node.coordinates.x"
        :y="node.coordinates.y"
        :radius="radius"
        :background-color="progressBackgroundColor"
        :data-qa="`node-progress-${node.id}`"
        :progress="progress"
        :locked="!node.accessible"
      ></progress-bar>
      <status-bar
        v-if="
          node.nodeType !== 'grandchild' &&
            node.nodeType !== '' &&
            !node.hideProgress
        "
        :x="node.coordinates.x"
        :y="node.coordinates.y"
        :radius="radius"
        :locked="!node.accessible"
        :status="node.status"
        :reviewStatus="node.reviewStatus"
        :enableHighlight="highlightNode"
        :data-qa="`node-status-${node.id}`"
      ></status-bar>
      <g v-show="node.nodeType !== 'grandchild' && node.nodeType !== ''">
        <transition name="fade">
          <foreignObject
            v-if="!node.hideTitle"
            v-show="!isHovered || !thumbnailURL || selected || !node.accessible"
            :data-qa="`node-title-${node.id}`"
            class="metaWrapper"
            :width="(140 * 2 * 5) / 6"
            :height="(140 * 2 * 5) / 6"
            :x="-(140 * 5) / 6"
            :y="-(140 * 5) / 6"
          >
            <div class="meta" :style="{ color: node.textColor }">
              <p class="title">{{ node.title }}</p>
              <p v-if="node.mediaDuration" class="timecode">
                {{ formatDuration() }}
              </p>
            </div>
          </foreignObject>
        </transition>
        <g v-show="!transitioning">
          <node-button
            v-if="!node.hideMedia"
            :x="0"
            :y="-radius"
            :fill="buttonBackgroundColor"
            :data-qa="`open-node-${node.id}`"
            :disabled="!node.accessible && !hasPermission('edit')"
            @click="handleRequestOpen"
          >
            <tapestry-icon :icon="icon" svg></tapestry-icon>
          </node-button>
          <template v-if="isLoggedIn">
            <add-child-button
              v-if="
                (node.mediaType === 'multi-content' || !hasTooManyLevels) &&
                  (hasPermission('add') || settings.draftNodesEnabled)
              "
              :node="node"
              :fill="buttonBackgroundColor"
              :x="canReview || hasPermission('edit') ? -35 : 0"
              :y="radius"
            ></add-child-button>
            <node-button
              v-if="hasPermission('edit')"
              :x="hasTooManyLevels && node.mediaType !== 'multi-content' ? 0 : 35"
              :y="radius"
              :fill="buttonBackgroundColor"
              :data-qa="`edit-node-${node.id}`"
              @click="editNode(node.id)"
            >
              <tapestry-icon icon="pen" svg></tapestry-icon>
            </node-button>
            <node-button
              v-else-if="canReview"
              :x="hasTooManyLevels ? 0 : 35"
              :y="radius"
              :fill="buttonBackgroundColor"
              :data-qa="`review-node-${node.id}`"
              @click="reviewNode"
            >
              <tapestry-icon icon="comment-dots" svg></tapestry-icon>
            </node-button>
          </template>
        </g>
      </g>
      <defs>
        <pattern :id="`node-image-${node.id}`" width="1" height="1">
          <image
            data-qa="nodeImage"
            preserveAspectRatio="xMidYMid slice"
            :href="thumbnailURL"
            x="0"
            y="0"
            :width="radius * 2"
            :height="radius * 2"
          />
        </pattern>
      </defs>
    </g>
  </transition>
</template>

<script>
import * as d3 from "d3"
import { mapActions, mapGetters, mapState, mapMutations } from "vuex"
import TapestryIcon from "@/components/common/TapestryIcon"
import { names } from "@/config/routes"
import { bus } from "@/utils/event-bus"
import Helpers from "@/utils/Helpers"
import * as wp from "@/services/wp"
import client from "@/services/TapestryAPI"
import { nodeStatus } from "@/utils/constants"
import AddChildButton from "./AddChildButton"
import ProgressBar from "./ProgressBar"
import StatusBar from "./StatusBar"
import NodeButton from "./NodeButton"
import TinyColor from "tinycolor2"

export default {
  name: "tapestry-node",
  components: {
    AddChildButton,
    ProgressBar,
    TapestryIcon,
    StatusBar,
    NodeButton,
  },
  props: {
    node: {
      type: Object,
      required: true,
    },
    root: {
      type: Boolean,
      required: true,
    },
  },
  data() {
    return {
      transitioning: false,
      isHovered: false,
    }
  },
  computed: {
    ...mapState(["selection", "settings", "visibleNodes"]),
    ...mapGetters(["getNode", "getDirectChildren", "isVisible", "getParent"]),
    canReview() {
      if (!this.isLoggedIn) {
        return false
      }
      if (this.node.author.id === wp.getCurrentUser().id) {
        return this.node.status === nodeStatus.DRAFT
      }
      if (wp.canEditTapestry()) {
        return this.node.reviewStatus === nodeStatus.SUBMIT
      }
      return false
    },
    isLoggedIn() {
      return wp.isLoggedIn()
    },
    hasTooManyLevels() {
      const parent = this.getParent(this.node.id)
      const grandparent = this.getParent(parent)
      if (parent && grandparent) {
        const parentNode = this.getNode(parent)
        const gpNode = this.getNode(grandparent)
        return (
          parentNode.mediaType !== "multi-content" &&
          gpNode.mediaType === "multi-content"
        )
      }
      return false
    },
    icon() {
      if (!this.node.accessible) {
        return "lock"
      }
      switch (this.node.mediaType) {
        case "h5p":
        case "video":
          return "play"
        case "text":
          return "text"
        case "activity":
          return "tasks"
        case "url-embed":
          return "window-maximize"
        case "multi-content":
          return "bars"
        case "wp-post":
          return "post"
        case "answer":
          return "answer"
        default:
          return "exclamation"
      }
    },
    show() {
      return this.isVisible(this.node.id)
    },
    radius() {
      if (!this.show) {
        return 0
      }
      if (this.root) {
        return 210
      }
      if (this.node.nodeType === "grandchild") {
        return 40
      }
      return 140
    },
    fill() {
      const showImages = this.settings.hasOwnProperty("renderImages")
        ? this.settings.renderImages
        : true

      if (this.node.nodeType !== "grandchild") {
        if (showImages && this.thumbnailURL) {
          return `url(#node-image-${this.node.id})`
        } else {
          return this.node.backgroundColor
        }
      } else if (this.selected) {
        return "var(--highlight-color)"
      } else {
        return TinyColor(this.node.backgroundColor)
      }
    },
    overlayFill() {
      if (this.selected) {
        return "var(--highlight-color)8a"
      } else if (!this.node.accessible) {
        return "#8a8a8cb3"
      }
      return this.thumbnailURL ? "#33333366" : "transparent"
    },
    // NOTE: This function is currently not used, but we may want to use it in the future for accessibility
    /*
    textColorReadable() {
      let color = this.node.textColor
      let tries = 0
      while (!TinyColor.isReadable(this.node.backgroundColor, color, {level:"AA",size:"large"}) && tries++ < 2) {
        if (TinyColor(this.node.backgroundColor).isDark()) {
          color = TinyColor(color).lighten().toString()
        }
        else {
          color = TinyColor(color).darken().toString()
        }
      }
      return color
    },
    */
    progressBackgroundColor() {
      let color = TinyColor(this.node.backgroundColor)
        .darken()
        .toString()
      while (!TinyColor(color).isDark()) {
        color = TinyColor(color)
          .darken()
          .toString()
      }
      return color
    },
    buttonBackgroundColor() {
      return TinyColor(this.progressBackgroundColor)
        .darken()
        .desaturate()
        .toString()
    },
    thumbnailURL() {
      return !this.node.accessible && this.node.lockedImageURL
        ? this.node.lockedImageURL
        : this.node.imageURL
    },
    selected() {
      return this.selection.includes(this.node.id)
    },
    progress() {
      if (this.node.mediaType !== "multi-content") {
        return this.node.progress
      }
      const rows = this.getDirectChildren(this.node.id)
        .map(this.getNode)
        .filter(n => n.status !== "draft")

      if (rows.length === 0) return 0
      return rows.filter(row => row.completed).length / rows.length
    },
    highlightNode() {
      return (
        this.node.reviewStatus !== nodeStatus.ACCEPT ||
        this.settings.showAcceptedHighlight
      )
    },
  },
  watch: {
    radius(newRadius) {
      d3.select(this.$refs.circle)
        .transition()
        .duration(800)
        .ease(d3.easePolyOut)
        .on("start", () => {
          this.transitioning = true
        })
        .on("end", () => {
          this.transitioning = false
        })
        .attr("r", newRadius)
    },
  },
  mounted() {
    this.$emit("mounted")
    this.$refs.circle.setAttribute("r", this.radius)
    const nodeRef = this.$refs.node
    d3.select(nodeRef).call(
      d3
        .drag()
        .on("start", () => {
          this.coordinates = {}
          if (this.selection.length) {
            this.coordinates = this.selection.reduce((coordinates, nodeId) => {
              const node = this.getNode(nodeId)
              coordinates[nodeId] = {
                x: node.coordinates.x,
                y: node.coordinates.y,
              }
              return coordinates
            }, {})
          } else {
            this.coordinates[this.node.id] = {
              x: this.node.coordinates.x,
              y: this.node.coordinates.y,
            }
          }
        })
        .on("drag", () => {
          for (const id of Object.keys(this.coordinates)) {
            const node = this.getNode(id)
            node.coordinates.x += d3.event.dx
            node.coordinates.y += d3.event.dy
          }
        })
        .on("end", () => {
          for (const [id, originalCoordinates] of Object.entries(this.coordinates)) {
            const node = this.getNode(id)
            node.coordinates.x += d3.event.dx
            node.coordinates.y += d3.event.dy
            let coordinates = {
              x: node.coordinates.x,
              y: node.coordinates.y,
            }
            if (
              originalCoordinates.x == coordinates.x &&
              originalCoordinates.y == coordinates.y
            ) {
              continue
            }
            this.$emit("dragend")
            if (this.hasPermission("edit") || this.hasPermission("move")) {
              this.updateNodeCoordinates({
                id,
                coordinates,
                originalCoordinates,
              }).catch(() => {
                this.$emit("dragend")
              })
            }
          }
        })
    )
  },
  methods: {
    ...mapActions(["updateNodeCoordinates"]),
    ...mapMutations(["select", "unselect"]),
    updateRootNode() {
      if (!this.root) {
        this.$router.push({
          name: names.APP,
          params: { nodeId: this.node.id },
          query: this.$route.query,
          path: `/nodes/${this.node.id}`,
        })
      }
    },
    openNode(id) {
      this.$root.$emit("open-node", id)
      client.recordAnalyticsEvent("app", "open", "lightbox", id)
    },
    editNode(id) {
      this.$root.$emit("edit-node", id)
      client.recordAnalyticsEvent("user", "click", "edit-node-button", id)
    },
    reviewNode() {
      this.$router.push({
        name: names.APP,
        params: { nodeId: this.node.id },
        query: { ...this.$route.query, sidebar: "review" },
      })
      client.recordAnalyticsEvent(
        "user",
        "click",
        "review-node-button",
        this.node.id
      )
    },
    formatDuration() {
      const seconds = this.node.mediaDuration
      const hours = Math.floor(seconds / 3600)
      let minutes = Math.floor((seconds - hours * 3600) / 60)
      let sec = seconds - hours * 3600 - minutes * 60

      if (sec < 10) sec = "0" + sec

      if (hours > 0 && minutes < 10) minutes = "0" + minutes

      if (hours === 0) return minutes + ":" + sec

      return hours + ":" + minutes + ":" + sec
    },
    handleRequestOpen() {
      if (this.node.accessible || this.hasPermission("edit")) {
        this.openNode(this.node.id)
      }
      client.recordAnalyticsEvent("user", "click", "open-node-button", this.node.id)
    },
    handleMouseover() {
      this.isHovered = true

      // Move node to end of svg document so it appears on top
      const node = this.$refs.node
      node.parentNode.appendChild(node)

      bus.$emit("mouseover", this.node.id)
      this.$emit("mouseover")
    },
    handleMouseleave() {
      this.isHovered = false
      this.$emit("mouseleave")
    },
    handleClick(evt) {
      if (
        this.hasPermission("edit") &&
        (evt.ctrlKey || evt.metaKey || evt.shiftKey)
      ) {
        this.selected ? this.unselect(this.node.id) : this.select(this.node.id)
      } else if (this.node.accessible || this.hasPermission("edit")) {
        this.root && this.node.hideMedia
          ? this.openNode(this.node.id)
          : this.updateRootNode()
      }
      client.recordAnalyticsEvent("user", "click", "node", this.node.id)
    },
    hasPermission(action) {
      return Helpers.hasPermission(this.node, action, this.settings.showRejected)
    },
  },
}
</script>

<style lang="scss" scoped>
.opaque {
  opacity: 0.2;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.5s;
}

.fade-enter,
.fade-leave-to {
  opacity: 0;
}
</style>

<style lang="scss">
.node-button {
  height: 55px;
  width: 55px;
  background: #666;
  border-radius: 50%;
  border: 3px solid white;
  color: white;
  font-size: 30px;
  position: relative;

  > * {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
  }

  > .fas.fa-play {
    left: 55%;
  }

  span {
    font-size: 28px;
    font-weight: bolder;
  }

  &-wrapper {
    width: 65px;
    height: 65px;
  }
}

.meta {
  color: white;
  min-height: 100%;
  pointer-events: none;
  display: flex;
  justify-content: center;
  align-items: center;
  flex-direction: column;
  text-align: center;
  font-size: 30px;
  .title {
    padding-left: 0;
    margin-top: 12px;
    margin-bottom: 0;
    font-weight: bold;
  }
}
</style>
