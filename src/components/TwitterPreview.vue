<template>
  <div>
    <div
      class="k-twitter-preview"
      :class="{ 'is-horizontal': twitterCardType !== 'summary_large_image' || !ogImage }"
    >
      <div class="k-twitter-preview__image">
        <img :src="ogImage" class="k-twitter-preview__img" :class="{ 'is-hidden': !ogImage }" />
      </div>
      <div class="k-twitter-preview__content">
        <span class="k-twitter-preview__url">{{ host }}</span>
        <span class="k-twitter-preview__title">{{ ogTitle }}</span>
        <p class="k-twitter-preview__description">{{ ogDescription }}</p>
      </div>
    </div>
    <k-box
      class="k-twitter-preview-notice"
      theme="info"
      v-if="twitterCardType === 'summary_large_image' && !ogImage"
    >
      <k-icon type="alert" />
      <k-text>{{ $t('twitter-card-type-not-respected') }}</k-text>
    </k-box>
  </div>
</template>

<script>
export default {
  props: {
    ogTitle: String,
    url: String,
    ogDescription: String,
    twitterCardType: String,
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
.k-twitter-preview {
  $c: &;

  border-radius: 1rem;
  border: 1px solid rgb(207, 217, 222);
  background: #fff;
  overflow: hidden;
  transition: background-color 0.2s;

  &:hover {
    background: rgb(247, 249, 249);
  }

  &-notice {
    margin-top: var(--spacing-3);
    display: flex;
    align-items: flex-start;

    > .k-icon {
      margin-top: var(--spacing-1);
      margin-right: var(--spacing-3);
    }
  }

  &.is-horizontal {
    display: flex;

    #{$c} {
      &__image {
        width: 8.125rem;
        padding-bottom: 8.125rem;
        flex-shrink: 0;
      }

      &__content {
        border-top: 0;
        border-left: 1px solid rgb(207, 217, 222);
        display: flex;
        flex-direction: column;
        justify-content: center;
      }

      &__description {
        -webkit-line-clamp: 2;
      }
    }
  }

  &__content {
    border-top: 1px solid rgb(207, 217, 222);
    padding: 0.75rem;
    font-size: 0.9375rem;
    line-height: 1.33;
  }

  &__title,
  &__description,
  &__url {
    color: rgb(83, 100, 113);
    display: -webkit-box;
    margin: 0.15rem 0;
    -webkit-box-orient: vertical;
    overflow: hidden;
    -webkit-line-clamp: 1;
  }

  &__title {
    color: rgb(15, 20, 25);
  }

  &__image {
    width: 100%;
    height: 0;
    padding-bottom: 52.355%;
    position: relative;
    background: rgb(247, 249, 249);

    img {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;

      &.is-hidden {
        display: none;
      }
    }

    &::before {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      content: url("data:image/svg+xml,%3Csvg width='24' height='24' viewBox='0 0 24 24' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M2 5.5A2.5 2.5 0 0 1 4.5 3h15A2.5 2.5 0 0 1 22 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-15A2.5 2.5 0 0 1 2 18.5v-13ZM4.5 5a.5.5 0 0 0-.5.5v13c0 .28.22.5.5.5h15a.5.5 0 0 0 .5-.5v-13a.5.5 0 0 0-.5-.5h-15ZM6 7h6v6H6V7Zm2 2v2h2V9H8Zm10 0h-4V7h4v2Zm0 4h-4v-2h4v2Zm0 4H6v-2h12v2Z' fill='%23536471'/%3E%3C/svg%3E");
    }
  }
}
</style>