import "./index.css"

import SeoWriterField from "./fields/seo-writer.vue"
import SeoWriterInput from "./fields/seo-writer-input.vue"
import UtmShareDialog from "./components/utm-share-dialog.vue"
import GscDrawer from "./components/gsc-drawer.vue"
import { createTemplateVariableNode } from "./nodes/template-variable.js"
import HeadingStructure from "./sections/heading-structure.vue"
import SeoPreview from "./sections/seo-preview.vue"
import SearchConsole from "./sections/search-console.vue"

panel.plugin("tobimori/seo", {
	icons: {
		"seo-ai": `<path d="M16.4356 3.21188C16.8261 2.82185 17.4592 2.82157 17.8496 3.21188L20.6777 6.04099C21.0681 6.43152 21.0682 7.06457 20.6777 7.45505L7.2422 20.8896H3.00001V16.6475L16.4356 3.21188ZM5.00001 17.4756V18.8896H6.41407L15.7276 9.57615L14.3135 8.16208L5.00001 17.4756ZM4.5293 1.3193C4.70583 0.893505 5.29418 0.893508 5.47071 1.3193L5.72364 1.93063C6.15555 2.97342 6.96155 3.80613 7.97462 4.2568L8.69239 4.57614C9.10267 4.75896 9.10262 5.35616 8.69239 5.53903L7.93263 5.87692C6.94497 6.3162 6.15339 7.11943 5.71387 8.1279L5.4668 8.69334C5.28636 9.10747 4.71366 9.10747 4.53321 8.69334L4.28614 8.1279C3.84661 7.11943 3.05506 6.3162 2.06739 5.87692L1.30762 5.53903C0.897483 5.35617 0.897435 4.75896 1.30762 4.57614L2.0254 4.2568C3.03845 3.80614 3.84446 2.97344 4.27637 1.93063L4.5293 1.3193ZM15.7276 6.74802L17.1426 8.16208L18.5567 6.74802L17.1426 5.33395L15.7276 6.74802Z" />`,
		robots: `<path d="M13.5 2c0 .444-.193.843-.5 1.118V5h5a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V8a3 3 0 0 1 3-3h5V3.118A1.5 1.5 0 1 1 13.5 2ZM6 7a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H6Zm-4 3H0v6h2v-6Zm20 0h2v6h-2v-6ZM9 14.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm6 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />`,
		"robots-off": `<path fill-rule="evenodd" clip-rule="evenodd" d="M21 16.786V8a3 3 0 0 0-3-3h-5V3.118a1.5 1.5 0 1 0-2 0V5H9.214l2 2H18a1 1 0 0 1 1 1v6.786l2 2ZM2.093 3.507l2.099 2.099A2.995 2.995 0 0 0 3 8v10a3 3 0 0 0 3 3h12c.463 0 .902-.105 1.293-.292l1.9 1.9 1.414-1.415-6.88-6.88a1.5 1.5 0 1 0-2.04-2.04L3.508 2.093 2.093 3.507ZM5 8a1 1 0 0 1 .65-.937L17.585 19H6a1 1 0 0 1-1-1V8Zm-5 2h2v6H0v-6Zm24 0h-2v6h2v-6Zm-13.5 3a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />`
	},
	sections: {
		"heading-structure": HeadingStructure,
		"seo-preview": SeoPreview,
		"seo-search-console": SearchConsole
	},
	fields: {
		"seo-writer": SeoWriterField
	},
	components: {
		"k-seo-writer-input": SeoWriterInput,
		"k-seo-utm-share-dialog": UtmShareDialog,
		"k-gsc-drawer": GscDrawer
	},
	writerNodes: {
		seoTemplateTitle: createTemplateVariableNode({
			name: "seoTemplateTitle",
			icon: "page",
			variable: "title",
			label: "seo.writerNodes.template.title",
			theme: "blue"
		}),
		seoTemplateSiteTitle: createTemplateVariableNode({
			name: "seoTemplateSiteTitle",
			icon: "globe",
			variable: "site.title",
			label: "seo.writerNodes.template.siteTitle",
			theme: "purple"
		})
	}
})
