export const pageView = () => {
  return {
    extends: 'k-page-view',
    async mounted() {
      await this.handleLoad()
    },
    computed: {
      changes() {
        return this.$store.getters['content/changes']()
      }
    },
    watch: {
      changes(changes) {
        if (Object.keys(changes).some((key) => key.includes('robots')) || this.dirty) {
          this.dirty = false
          this.handleLoad(changes)
          if (changes) this.dirty = true
        }
      }
    },
    methods: {
      async handleLoad(changes) {
        if (!this.tabs.some((tab) => tab.name === 'seo')) return

        const page = this.model.id.replaceAll('/', '+')
        const response = await this.$api.post(`/k-seo/${page}/robots`, changes ?? this.changes)

        if (!response.active) return

        const el = this.$el.querySelector('.k-button.k-status-icon')

        el.setAttribute('data-robots', response.state)

        let label = this.$t('indicator-index')
        if (response.state.includes('no')) label = this.$t('indicator-any')
        if (response.state.includes('noindex')) label = this.$t('indicator-noindex')
        el.setAttribute('data-robots-label', label)

        document.querySelectorAll('.k-toggles-text').forEach((el) => {
          if (el.textContent.includes('page:')) {
            // does robots string contain value after page::?
            const type = el.textContent.split('page:')[1]

            let value = this.$t('yes')
            // we add 'no' to exclude `imageindex` for `index` field
            if (response.defaults.includes(`no${type}`)) value = this.$t('no')
            el.textContent = el.textContent.replace(`page:${type}`, value)
          }
        })
      }
    }
  }
}
