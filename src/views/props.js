export const overviewProps = {
	buttons: {
		type: Array,
		default: () => []
	},
	columns: Object,
	rows: Array,
	pagination: Object,
	search: String,
	sort: String,
	dir: String,
	severity: Object,
	summary: {
		type: Object,
		default: () => ({})
	},
	stats: Object,
	tab: String,
	tabs: Array,
	issue: String,
	// Panel language switching needs these even though the overview has no content of its own
	api: String,
	lock: Object,
	versions: Object
}

export const editableOverviewProps = {
	...overviewProps,
	changes: {
		type: Array,
		default: () => []
	},
	ai: Boolean,
	// All matching row IDs, including rows outside the current table page
	ids: {
		type: Array,
		default: () => []
	}
}
