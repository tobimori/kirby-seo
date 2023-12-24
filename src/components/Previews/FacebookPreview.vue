<template>
  <div>
    <div class="k-facebook-preview">
      <div class="k-facebook-preview__image" v-if="ogImage">
        <img :src="ogImage" class="k-facebook-preview__img" />
      </div>
      <div class="k-facebook-preview__content">
        <span class="k-facebook-preview__url">{{ host }}</span>
        <span class="k-facebook-preview__title">{{ ogTitle }}</span>
        <p class="k-facebook-preview__description">{{ ogDescription }}</p>
      </div>
    </div>
    <a
      class="k-seo-preview__debugger"
      href="https://developers.facebook.com/tools/debug/"
      aria-label="Facebook Sharing Debugger"
      target="_blank"
      rel="noopener noreferrer"
    >
      {{ $t('open-debugger') }}
      <k-icon type="open" />
    </a>
  </div>
</template>

<script>
export default {
  props: {
    ogTitle: String,
    url: String,
    ogDescription: String,
    ogImage: String
  },
  computed: {
    host() {
      return new URL(this.url).host
    }
  }
}
</script>

<style lang="scss">
.k-facebook-preview {
  background: #f0f2f5;
  border: 1px solid #ced0d4;
  overflow: hidden;
  border-radius: var(--rounded);

  &__image {
    width: 100%;
    height: 0;
    padding-bottom: 52.355%;
    position: relative;

    img {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }

  &__content {
    padding: 0.75rem 1rem;
  }

  &__title,
  &__description,
  &__url {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
    -webkit-line-clamp: 1;
  }

  &__url {
    color: #65676b;
    font-size: 0.75rem;
    text-transform: uppercase;
    line-height: 1.1;
    margin-bottom: 0.25rem;
  }

  &__title {
    font-weight: 600;
    line-height: 1.1765;
    font-size: 1rem;
    color: #050505;
    margin: 0.3125rem 0;
  }

  &__description {
    line-height: 1.3333;
    color: #65676b;
    font-size: 0.875rem;
  }
}
</style>
