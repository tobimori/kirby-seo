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
    this.handleLoad()
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

      let newChanges = {}
      Object.entries(changes ?? this.changes).map(([key, value]) => {
        newChanges[key] = encodeURIComponent(JSON.stringify(value))
      })
      const response = await this.$api.get(this.parent + '/sections/' + this.name, newChanges)

      this.value = response.value
      this.label = response.label

      this.isLoading = false
    }
  },
  watch: {
    changes(changes) {
      this.$helper.debounce((changes) => this.handleLoad(changes), 200)(changes)
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