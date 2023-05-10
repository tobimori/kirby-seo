<template>
  <div class="k-seo-preview">
    <k-select-field
      label="Preview"
      type="select"
      v-model="type"
      :options="options"
      :empty="false"
    />
    <div class="k-seo-preview__inner" v-if="value">
      <google-preview v-if="type === 'google'" v-bind="value" />
      <twitter-preview v-if="type === 'twitter'" v-bind="value" />
      <facebook-preview v-if="type === 'facebook'" v-bind="value" />
    </div>
  </div>
</template>

<script>
import FacebookPreview from '../components/FacebookPreview.vue'
import GooglePreview from '../components/GooglePreview.vue'
import TwitterPreview from '../components/TwitterPreview.vue'

export default {
  components: { GooglePreview, TwitterPreview, FacebookPreview },
  data() {
    const type = localStorage.getItem('kSEOPreviewType') ?? 'google'

    return {
      label: null,
      value: null,
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
}
</style>