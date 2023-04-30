<template>
  <div class="k-seo-preview">
    <k-select-field
      label="Preview Type"
      type="select"
      v-model="type"
      :options="options"
      :empty="false"
    />
    <div class="k-seo-preview__inner">
      <google-preview
        v-if="type === 'google'"
        url="https://pitchguru.com"
        title="PitchGuru | Professionelle Präsentation für Ihr Unternehmen"
        description="PitchGuru ist der Ansprechpartner, dem Unternehmen bei ihren Präsentationen vertrauen. Schnell, einfach und effizient Zeit und Geld sparen."
      />
      <twitter-preview
        v-if="type === 'twitter'"
        url="https://pitchguru.com"
        ogTitle="Professionelle Präsentation für Ihr Unternehmen"
        ogDescription="PitchGuru ist der Ansprechpartner, dem Unternehmen bei ihren Präsentationen vertrauen. Schnell, einfach und effizient Zeit und Geld sparen."
        ogImage="http://127.0.0.1:3000/media/site/be4ab3677e-1678205309/tmm03294-1-1200x630-crop-1.jpg"
      />
      <facebook-preview
        v-if="type === 'facebook'"
        url="https://pitchguru.com"
        ogTitle="Professionelle Präsentation für Ihr Unternehmen"
        ogDescription="PitchGuru ist der Ansprechpartner, dem Unternehmen bei ihren Präsentationen vertrauen. Schnell, einfach und effizient Zeit und Geld sparen."
        ogImage="http://127.0.0.1:3000/media/site/be4ab3677e-1678205309/tmm03294-1-1200x630-crop-1.jpg"
      />
    </div>
  </div>
</template>

<script>
import FacebookPreview from '../components/FacebookPreview.vue'
import GooglePreview from '../components/GooglePreview.vue'
import TwitterPreview from '../components/TwitterPreview.vue'

export default {
  components: { GooglePreview, TwitterPreview, FacebookPreview },
  props: {
    label: String,
    type: String
  },
  computed: {
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