<template>
  <div class="k-section k-heading-structure" v-if="value">
    <div class="k-field-header k-heading-structure__label k-label k-field-label">
      <k-icon type="headline" />
      <span class="k-label-text">{{ label || $t('heading-structure') }}</span>
      <k-loader v-if="isLoading" />
    </div>
    <k-box theme="white">
      <ol class="k-heading-structure__list">
        <li
          v-for="(item, index) in value"
          :key="index"
          :style="`z-index: ${value.length - index}`"
          :class="`k-heading-structure__item level-${item.level} ${
            itemInvalid(item, index) ? 'is-invalid' : ''
          }`"
        >
          <span class="k-heading-structure__item__level">H{{ item.level }}</span>
          <span class="k-heading-structure__item__text">{{ item.text }}</span>
        </li>
      </ol>
    </k-box>
    <k-box class="k-heading-structure__notice" theme="negative" v-if="incorrectOrder && !noH1">
      <k-icon type="alert" />
      <k-text>{{ $t('incorrect-heading-order') }}</k-text>
    </k-box>
    <k-box class="k-heading-structure__notice" theme="negative" v-if="multipleH1">
      <k-icon type="alert" />
      <k-text>{{ $t('multiple-h1-tags') }}</k-text>
    </k-box>
    <k-box class="k-heading-structure__notice" theme="negative" v-if="noH1">
      <k-icon type="alert" />
      <k-text>{{ $t('missing-h1-tag') }}</k-text>
    </k-box>
  </div>
</template>

<script>
export default {
  data() {
    return {
      label: null,
      value: null,
      isLoading: true
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
    incorrectOrder() {
      return this.value?.some((item, index) => item.level > (this.value[index - 1]?.level ?? 0) + 1)
    },
    multipleH1() {
      return this.value?.filter((item) => item.level === 1).length > 1
    },
    noH1() {
      return this.value?.filter((item) => item.level === 1).length === 0
    }
  },
  methods: {
    async handleLoad(changes) {
      this.isLoading = true

      const page = panel.view.props.model.id

      if (!page) {
        throw new Error('[kirby-seo] The Heading structure section is only available for pages')
      }

      const response = await panel.api.post(
        `/k-seo/${page.replaceAll('/', '+')}/heading-structure`,
        changes ?? this.changes
      )

      this.value = response
      this.isLoading = false
    },
    itemInvalid(item, index) {
      if (item.level > (this.value[index - 1]?.level ?? 0) + 1) return true // wrong order
      if (item.level === 1 && this.value[index - 1]) return true // wrong order
      if (item.level === 1 && this.value.filter((item) => item.level === 1).length > 1) return true // multiple h1

      return false
    }
  },
  watch: {
    changes(changes) {
      this.debouncedLoad(changes)
    }
  }
}
</script>

<style lang="scss">
.k-heading-structure {
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

  &__notice {
    margin-top: var(--spacing-2);
    display: flex;
    align-items: flex-start;

    > .k-icon {
      margin-top: 0.125rem;
      margin-right: var(--spacing-1);
      color: var(--color-red);
    }
  }

  &__list {
    overflow: hidden;
  }

  &__item {
    position: relative;
    background: var(--theme-color-back);
    padding-block: var(--spacing-px);
    display: flex;

    &__level {
      font-family: var(--font-mono);
      font-weight: 700;
      margin-right: var(--spacing-2);
    }

    &__text {
      white-space: nowrap;
      text-overflow: ellipsis;
      overflow: hidden;
    }

    &.is-invalid {
      color: var(--color-red);
    }

    @for $i from 2 through 6 {
      &.level-#{$i} {
        margin-left: ($i - 2) * 1.6rem;
        padding-left: 1.6rem;

        &::before {
          content: '';
          position: absolute;
          top: calc(50% - 0.0625rem);
          left: 0.4rem;
          width: 0.8rem;
          height: 0.125rem;
          background-color: currentColor;
        }

        &::after {
          content: '';
          position: absolute;
          bottom: calc(50% - 0.0625rem);
          left: 0.4rem;
          height: 9999px;
          width: 0.125rem;
          background-color: currentColor;
        }
      }
    }
  }
}
</style>
