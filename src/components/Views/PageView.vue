https://github.com/getkirby/kirby/blob/main/panel/src/components/Views/Pages/PageView.vue

<template>
  <k-panel-inside
    :data-has-tabs="tabs.length > 1"
    :data-id="model.id"
    :data-locked="isLocked"
    :data-template="blueprint"
    class="k-page-view"
  >
    <template #topbar>
      <k-prev-next v-if="model.id" :prev="prev" :next="next" />
    </template>

    <k-header
      :editable="permissions.changeTitle && !isLocked"
      class="k-page-view-header"
      @edit="$dialog(id + '/changeTitle')"
    >
      {{ model.title }}
      <template #buttons>
        <k-button-group>
          <k-button
            v-if="permissions.preview && model.previewUrl"
            :link="model.previewUrl"
            :title="$t('open')"
            icon="open"
            target="_blank"
            variant="filled"
            size="sm"
            class="k-page-view-preview"
          />

          <k-button
            :disabled="isLocked === true"
            :dropdown="true"
            :title="$t('settings')"
            icon="cog"
            variant="filled"
            size="sm"
            class="k-page-view-options"
            @click="$refs.settings.toggle()"
          />
          <k-dropdown-content ref="settings" :options="$dropdown(id)" align-x="end" />

          <k-languages-dropdown />

          <k-button
            v-if="status"
            v-bind="statusBtn"
            class="k-page-view-status"
            variant="filled"
            @click="$dialog(id + '/changeStatus')"
          />

          <k-button
            class="k-page-view-status k-page-view-robots"
            v-if="robots && robots.active"
            v-bind="robotsBtn"
            @click="openSeoTab"
          />
        </k-button-group>

        <k-form-buttons :lock="lock" />
      </template>
    </k-header>

    <k-model-tabs :tab="tab.name" :tabs="tabs" />

    <k-sections
      :blueprint="blueprint"
      :empty="$t('page.blueprint', { blueprint: $esc(blueprint) })"
      :lock="lock"
      :parent="id"
      :tab="tab"
    />
  </k-panel-inside>
</template>

<script>
export default {
  extends: 'k-page-view',
  data() {
    return {
      dirty: false,
      robots: {
        active: false,
        state: []
      }
    }
  },
  async mounted() {
    await this.handleLoad()
  },
  methods: {
    openSeoTab() {
      panel.view.open(panel.view.path + '?tab=seo')
    },
    async handleLoad(changes) {
      if (!panel.view.props.tabs.some((tab) => tab.name === 'seo')) return

      const page = this.model.id.replaceAll('/', '+')
      this.robots = await panel.api.post(`/k-seo/${page}/robots`, changes ?? this.changes)
    }
  },
  computed: {
    changes() {
      return this.$store.getters['content/changes']() // TODO: new panel API for changes?
    },
    robotsBtn() {
      const btn = {
        responsive: true,
        size: 'sm',
        icon: 'robots',
        theme: 'positive',
        text: this.$t('indicator-index'),
        variant: 'filled'
      }

      if (this.robots.state.includes('no')) {
        btn.text = this.$t('indicator-any')
        btn.theme = 'notice'
        btn.icon = 'robots-off'
      }

      if (this.robots.state.includes('noindex')) {
        btn.text = this.$t('indicator-noindex')
        btn.theme = 'negative'
      }

      return btn
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
  }
}
</script>

<style lang="scss">
.k-page-view-robots {
  --color-green-boost: -15%;
}
</style>
