import { kirbyup } from 'kirbyup/plugin'

import { pageView } from './extends/pageView'
import './index.scss'

panel.plugin('tobimori/seo', {
  sections: kirbyup.import('./sections/*.vue'),
  components: {
    'k-page-view': pageView()
  }
})
