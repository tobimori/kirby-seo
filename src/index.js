import { kirbyup } from 'kirbyup/plugin'
import PageView from './components/Views/PageView.vue'

panel.plugin('tobimori/seo', {
  icons: {
    robots: `<path d="M13.5 2c0 .444-.193.843-.5 1.118V5h5a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V8a3 3 0 0 1 3-3h5V3.118A1.5 1.5 0 1 1 13.5 2ZM6 7a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H6Zm-4 3H0v6h2v-6Zm20 0h2v6h-2v-6ZM9 14.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm6 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />`,
    'robots-off': `<path fill-rule="evenodd" clip-rule="evenodd" d="M21 16.786V8a3 3 0 0 0-3-3h-5V3.118a1.5 1.5 0 1 0-2 0V5H9.214l2 2H18a1 1 0 0 1 1 1v6.786l2 2ZM2.093 3.507l2.099 2.099A2.995 2.995 0 0 0 3 8v10a3 3 0 0 0 3 3h12c.463 0 .902-.105 1.293-.292l1.9 1.9 1.414-1.415-6.88-6.88a1.5 1.5 0 1 0-2.04-2.04L3.508 2.093 2.093 3.507ZM5 8a1 1 0 0 1 .65-.937L17.585 19H6a1 1 0 0 1-1-1V8Zm-5 2h2v6H0v-6Zm24 0h-2v6h2v-6Zm-13.5 3a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />`
  },
  sections: kirbyup.import('./sections/*.vue'),
  components: {
    'k-page-view': PageView
  }
})
