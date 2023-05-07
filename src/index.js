import { kirbyup } from 'kirbyup/plugin'

panel.plugin('tobimori/seo', {
  sections: kirbyup.import('./sections/*.vue')
})
