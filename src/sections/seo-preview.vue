<template>
  <div class="k-seo-preview">
    <div class="k-seo-preview__label k-field-label">
      <k-icon type="preview" /><span>{{ label || $t('seo-preview') }}</span>
      <k-loader v-if="isLoading" />
    </div>
    <k-select-field
      type="select"
      :before="$t('seo-preview-for')"
      v-model="type"
      :options="options"
      :empty="false"
    />
    <div class="k-seo-preview__inner" v-if="value">
      <google-preview v-if="type === 'google'" v-bind="value" />
      <twitter-preview v-if="type === 'twitter'" v-bind="value" />
      <facebook-preview v-if="type === 'facebook'" v-bind="value" />
      <slack-preview v-if="type === 'slack'" v-bind="value" />
    </div>
  </div>
</template>

<script>
import FacebookPreview from '../components/FacebookPreview.vue'
import GooglePreview from '../components/GooglePreview.vue'
import TwitterPreview from '../components/TwitterPreview.vue'
import SlackPreview from '../components/SlackPreview.vue'

export default {
  components: { GooglePreview, TwitterPreview, FacebookPreview, SlackPreview },
  data() {
    const type = localStorage.getItem('kSEOPreviewType') ?? 'google'

    return {
      label: null,
      value: null,
      isLoading: true,
      type
    }
  },
  created() {
    this.isLoading = true

    this.load().then((data) => {
      this.label = data.label
    }) // loads label and properties
    this.handleLoad() // handles metadata & title change

    this.debouncedLoad = this.$helper.debounce((changes) => {
      this.handleLoad(changes)
    }, 200) // debounce function for dirty changes watcher
  },
  computed: {
    changes() {
      return this.$store.getters['content/changes']()
    },
    options() {
      return [
        {
          value: 'google',
          text: 'Google'
        },
        {
          value: 'twitter',
          text: 'Twitter'
        },
        {
          value: 'facebook',
          text: 'Facebook'
        },
        {
          value: 'slack',
          text: 'Slack'
        }
      ]
    }
  },
  methods: {
    async handleLoad(changes) {
      this.isLoading = true

      const page = this.parent.toString().split('/').pop()
      const response = await this.$api.post(`/k-seo/${page}/seo-preview`, changes ?? this.changes)

      this.value = response
      this.isLoading = false
    }
  },
  watch: {
    changes(changes) {
      this.debouncedLoad(changes)
    },
    type() {
      localStorage.setItem('kSEOPreviewType', this.type)
    }
  }
}
</script>

<style lang="scss">
.k-seo-preview {
  &__inner {
    margin-top: 1em;
  }

  &__debugger {
    margin-top: 1rem;
    display: flex;
    font-size: var(--text-sm);
    color: var(--color-gray-600);
    line-height: 1.25rem;
    width: max-content;
    margin-left: auto;

    &:hover {
      text-decoration: underline;
      color: var(--text-gray-700);
    }

    > .k-icon {
      margin-left: var(--spacing-3);
    }
  }

  &__label {
    display: flex;
    align-items: center;
    margin-bottom: -2rem;

    > .k-icon {
      margin-right: var(--spacing-3);
      color: var(--color-gray-700);
    }

    > .k-loader {
      margin-left: auto;
      color: var(--color-gray-700);
    }
  }
}
</style>