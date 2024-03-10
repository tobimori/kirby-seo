<template>
  <div class="k-section k-seo-preview">
    <div class="k-field-header k-seo-preview__label k-label k-field-label">
      <k-icon type="preview" /><span class="k-label-text">{{ label || $t('seo-preview') }}</span>
      <k-loader v-if="isLoading" />
    </div>
    <k-select-field
      type="select"
      name="seo-preview-type"
      :before="$t('seo-preview-for')"
      v-model="type"
      :options="options"
      :empty="false"
    />
    <div class="k-seo-preview__inner" v-if="value">
      <google-preview v-if="type === 'google'" v-bind="value" />
      <facebook-preview v-if="type === 'facebook'" v-bind="value" />
      <slack-preview v-if="type === 'slack'" v-bind="value" />
    </div>
  </div>
</template>

<script>
import FacebookPreview from '../components/Previews/FacebookPreview.vue'
import GooglePreview from '../components/Previews/GooglePreview.vue'
import SlackPreview from '../components/Previews/SlackPreview.vue'

export default {
  components: { GooglePreview, FacebookPreview, SlackPreview },
  data() {
    const type = localStorage.getItem('kSEOPreviewType') ?? 'google'

    return {
      label: null,
      value: null,
      isLoading: true,
      options: [],
      type
    }
  },
  created() {
    this.isLoading = true

    this.load().then((data) => {
      this.label = data.label
      this.options = data.options
    }) // loads label and properties
    this.handleLoad() // handles metadata & title change

    this.debouncedLoad = this.$helper.debounce((changes) => {
      this.handleLoad(changes)
    }, 200) // debounce function for dirty changes watcher
  },
  computed: {
    changes() {
      return this.$store.getters['content/changes']()
    }
  },
  methods: {
    async handleLoad(changes) {
      this.isLoading = true

      const page = panel.view.props.model?.id?.replaceAll('/', '+') ?? 'site'
      const response = await panel.api.post(`/k-seo/${page}/seo-preview`, changes ?? this.changes)

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
.k-field-name-seo-preview-type .k-field-header {
  display: none;
}

.k-seo-preview {
  &__inner {
    margin-top: var(--spacing-2);
  }

  &__debugger {
    margin-top: 1rem;
    display: flex;
    font-size: var(--text-sm);
    color: var(--color-gray-700);
    line-height: 1.25rem;
    width: max-content;
    margin-left: auto;

    &:hover {
      text-decoration: underline;
      color: var(--text-gray-800);
    }

    > .k-icon {
      margin-left: var(--spacing-2);
    }
  }

  &__label {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: var(--spacing-2);

    > .k-icon {
      color: var(--color-gray-700);
    }

    > .k-loader {
      margin-left: auto;
      color: var(--color-gray-700);
    }
  }
}
</style>
