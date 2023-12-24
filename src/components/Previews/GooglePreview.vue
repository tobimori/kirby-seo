<template>
  <div>
    <div class="k-google-search-preview">
      <span class="k-google-search-preview__url">
        <span>{{ origin }}</span>
        <span
          v-for="(breadcrumb, index) in breadcrumbs"
          :key="index"
          class="k-google-search-preview__url__breadcrumb"
        >
          {{ breadcrumb }}
        </span>
      </span>
      <h2 class="k-google-search-preview__headline">{{ title }}</h2>
      <p class="k-google-search-preview__paragraph">
        {{ description }}
      </p>
    </div>
    <a
      class="k-seo-preview__debugger"
      href="https://search.google.com/search-console"
      aria-label="Google Search Console"
      target="_blank"
      rel="noopener noreferrer"
    >
      {{ $t('open-search-console') }}
      <k-icon type="open" />
    </a>
  </div>
</template>

<script>
export default {
  props: {
    title: String,
    url: String,
    description: String
  },
  computed: {
    origin() {
      return new URL(this.url).origin
    },
    breadcrumbs() {
      return this.url.split('/').slice(3)
    }
  }
}
</script>

<style lang="scss">
.k-google-search-preview {
  padding: 1em;
  background: #fff;
  border: 1px solid #ccc;
  letter-spacing: -0.005em;
  border-radius: var(--rounded);

  &__headline,
  &__paragraph {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  &__headline {
    margin-top: 0;
    margin-bottom: 0.25em;
    font-size: 1.25em;
    font-weight: normal;
    color: #1a0dab;
    -webkit-line-clamp: 1;

    &:hover {
      text-decoration: underline;
    }
  }

  &__url {
    display: inline-block;
    margin-bottom: 0.5em;
    font-size: 0.875em;
    line-height: 1.3;
    color: #202124;
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
    max-width: 100%;

    > * {
      margin-right: 0.25em;
    }

    &__breadcrumb {
      color: #5f6368;
      display: inline-block;

      &::before {
        content: '› ';
      }
    }

    .k-icon {
      margin-left: 0.1em;
    }
  }

  &__paragraph {
    margin: 0;
    font-size: 0.875em;
    line-height: 1.3em;
    color: #3c4043;
    -webkit-line-clamp: 3;
  }
}
</style>
