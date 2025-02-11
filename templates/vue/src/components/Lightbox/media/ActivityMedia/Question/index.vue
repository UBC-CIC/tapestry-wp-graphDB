<template>
  <div class="question">
    <button v-if="showBackBtn" class="button-nav m-auto" @click="back">
      <i class="fas fa-arrow-left"></i>
    </button>
    <loading v-if="submitting" label="Submitting..." />
    <div v-else class="question-wrapper" :class="{ 'with-back-btn': showBackBtn }">
      <div v-if="question.followUp.enabled" class="follow-up">
        <div
          v-if="previousQuestionAnswers.length"
          class="answer-container mx-auto mb-3"
        >
          <h3 class="mb-4">
            {{ question.followUp.text || "Previously, you said:" }}
          </h3>
          <b-tabs vertical no-nav-style nav-class="nav-tablist">
            <b-tab
              v-for="previousAnswer in previousQuestionAnswers"
              :key="previousAnswer[0]"
            >
              <template #title>
                <div class="icon">
                  <tapestry-icon :icon="getIcon(previousAnswer[0])" />
                </div>
              </template>
              <completed-activity-media
                :type="previousAnswer[0]"
                :answerData="previousAnswer[1]"
                :question="getQuestion(question.followUp.questionId)"
              ></completed-activity-media>
            </b-tab>
          </b-tabs>
        </div>
        <div v-else>
          <p>You haven't done the previous activity yet.</p>
        </div>
      </div>
      <h1 class="question-title">
        {{ question.text }}
      </h1>
      <b-alert
        v-if="!isLoggedIn"
        show
        variant="warning"
        class="loggedout-alert mx-auto"
      >
        Please login to have your answers saved.
      </b-alert>
      <div class="question-body">
        <div v-if="formOpened">
          <component
            :is="formType + '-question'"
            :id="question.id"
            :node="node"
            :question="question"
            :answer="answer || ''"
            @skipQuestion="$emit('skipQuestion')"
            @submit="handleSubmit"
          ></component>
        </div>
        <div v-else class="question-answer-types">
          <p class="question-answer-text">I want to answer with...</p>
          <div class="button-container">
            <answer-button
              v-for="enabledAnswerType in enabledAnswerTypes"
              :key="enabledAnswerType[0]"
              class="text-capitalize"
              :completed="isFormCompleted(enabledAnswerType[0])"
              :icon="enabledAnswerType[0]"
              :data-qa="`answer-button-${enabledAnswerType[0]}`"
              @click="openForm(enabledAnswerType[0])"
            >
              {{ enabledAnswerType[0].replace(/([A-Z])/g, " $1").trim() }}
            </answer-button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapGetters, mapState } from "vuex"
import client from "@/services/TapestryAPI"
import AnswerButton from "./AnswerButton"
import AudioQuestion from "./AudioQuestion"
import TextQuestion from "./TextQuestion"
import DragDropQuestion from "./DragDropQuestion"
import MultipleChoiceQuestion from "./MultipleChoiceQuestion"
import Loading from "@/components/common/Loading"
import CompletedActivityMedia from "../../common/CompletedActivityMedia"
import TapestryIcon from "@/components/common/TapestryIcon"
import * as wp from "@/services/wp"

export default {
  name: "question",
  components: {
    AnswerButton,
    AudioQuestion,
    TextQuestion,
    DragDropQuestion,
    MultipleChoiceQuestion,
    Loading,
    CompletedActivityMedia,
    TapestryIcon,
  },
  props: {
    question: {
      type: Object,
      required: true,
    },
    node: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      submitting: false,
      formOpened: false,
      formType: "",
      answers: {},
      prevQuestionId: null,
    }
  },
  computed: {
    ...mapGetters(["getAnswers", "getQuestion"]),
    ...mapState(["userAnswers"]),
    isLoggedIn() {
      return wp.isLoggedIn()
    },
    previousQuestionAnswers() {
      if (this.question.followUp.questionId !== null) {
        for (let i = 0; i < Object.keys(this.userAnswers).length; i++) {
          let tempNodeId = Object.keys(this.userAnswers)[i]
          if (
            this.userAnswers[tempNodeId].activity?.[
              this.question.followUp.questionId
            ]
          ) {
            // eslint-disable-next-line vue/no-side-effects-in-computed-properties
            this.question.followUp.nodeId = tempNodeId
            // eslint-disable-next-line vue/no-side-effects-in-computed-properties
            this.prevQuestionId = this.question.followUp.questionId
          }
        }
      }
      let answerObject = this.getAnswers(
        this.question.followUp.nodeId,
        this.question.followUp.questionId
      )
      return answerObject ? Object.entries(answerObject) : null
    },
    enabledAnswerTypes() {
      return Object.entries(this.question.answerTypes).filter(([, value]) => {
        return value.enabled
      })
    },
    answer() {
      if (this.formOpened && this.answers?.[this.formType]) {
        return this.answers[this.formType]
      }
      switch (this.formType) {
        case "text":
          return []
        default:
          return null
      }
    },
    showBackBtn() {
      return this.formOpened && this.enabledAnswerTypes.length > 1
    },
  },
  watch: {
    question() {
      this.answers = this.getAnswers(this.node.id, this.question.id)
      this.openFormIfSingle()
    },
  },
  created() {
    this.answers = this.getAnswers(this.node.id, this.question.id)
  },
  mounted() {
    this.openFormIfSingle()
  },
  methods: {
    ...mapActions(["completeQuestion", "saveAudio"]),
    back() {
      client.recordAnalyticsEvent("user", "back", "question", this.question.id)
      this.formOpened = false
    },
    openFormIfSingle() {
      if (this.enabledAnswerTypes.length === 1) {
        this.formType = this.enabledAnswerTypes.map(item => item[0])[0]
        this.formOpened = true
      } else {
        this.formOpened = false
      }
    },
    isFormCompleted(type) {
      return !!this.userAnswers?.[this.node.id]?.activity?.[this.question.id]
        ?.answers[type]
    },
    openForm(answerType) {
      client.recordAnalyticsEvent(
        "user",
        "click",
        "answer-button",
        this.question.id,
        {
          type: answerType,
        }
      )
      this.formType = answerType
      this.formOpened = true
    },
    async handleSubmit(formData) {
      this.formOpened = false
      this.submitting = true

      if (!this.isLoggedIn) {
        return this.$emit("submit")
      }

      let submittedAnswer = null

      switch (this.formType) {
        case "audio": {
          const audioFile = formData
          const savedUrl = await this.saveAudio({
            nodeId: this.node.id,
            questionId: this.question.id,
            audio: audioFile.replace("data:audio/ogg; codecs=opus;base64,", ""),
          })
          submittedAnswer = { url: savedUrl }
          break
        }
        default: {
          submittedAnswer = formData
          break
        }
      }
      await this.completeQuestion({
        nodeId: this.node.id,
        questionId: this.question.id,
        answerType: this.formType,
        answer: submittedAnswer,
      })
      this.submitting = false

      client.recordAnalyticsEvent("user", "submit", "question", this.question.id, {
        type: this.formType,
      })
      this.$emit("submit")
    },
    formIsCompleted(type) {
      return !!this.userAnswers?.[this.node.id]?.activity?.[this.question.id]
        ?.answers?.[type]
    },
    getIcon(answerType) {
      if (answerType == "multipleChoice") {
        return "tasks"
      }
      return answerType
    },
  },
}
</script>

<style lang="scss" scoped>
.answer-container {
  width: 75%;
}

.question {
  display: flex;
  flex-direction: column;
  justify-content: center;
  height: 100%;
  width: 100%;
  max-width: 800px;
  margin: auto;

  .button-nav {
    border-radius: 50%;
    width: 80px;
    height: 80px;
    background: var(--text-color-primary);
    color: var(--bg-color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 56px;
    margin: 0;
    margin-right: 12px;
    opacity: 0.9;
    transition: all 0.1s ease-out;
    position: absolute;
    top: 74px;
    left: 24px;
    z-index: 20;

    &:hover {
      background: var(--highlight-color);
      opacity: 1;
    }

    &:disabled {
      opacity: 0.4;
      pointer-events: none;
      cursor: not-allowed;
    }

    &:last-child {
      margin-right: 0;
    }
  }

  .loading {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 30;
    border-radius: 15px;
  }

  &-title {
    position: relative;
    font-size: 28px;
    font-weight: 600 !important;
    padding-top: 16px;
    margin-bottom: 36px;

    &:before {
      display: none;
    }

    + .recorder {
      margin-top: 4em;
    }
  }

  &-wrapper.with-back-btn {
    margin: 0 100px;
  }

  &-body {
    min-height: 250px;
  }

  .loggedout-alert {
    max-width: 500px;
  }

  &-form {
    max-width: 500px;
    margin: 0 auto;
  }

  &-answer-types {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;

    .question-answer-text {
      width: 100%;
      padding: 0;
      font-size: 28px;
      font-style: italic;
    }

    .button-container {
      width: auto;
      display: flex;
      justify-content: center;

      > * {
        margin: 0 15px;
      }
    }
  }
}
</style>
