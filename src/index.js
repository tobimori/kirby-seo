import { kirbyup } from 'kirbyup/plugin'

panel.plugin('tobimori/meta', {
  sections: kirbyup.import('./sections/*.vue')
})
