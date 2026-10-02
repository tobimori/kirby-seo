<script setup>
import { computed, ref, usePanel, useHelpers } from "kirbyuse"

import { useOverviewTable } from "../composables/overview-table.js"
import { SEVERITY, SEVERITY_ICONS } from "../utils/checks.js"

const props = defineProps({
	buttons: {
		type: Array,
		default: () => []
	},
	changes: {
		type: Array,
		default: () => []
	},
	columns: Object,
	rows: Array,
	pagination: Object,
	search: String,
	sort: String,
	dir: String,
	/** Number of pages per issue type */
	summary: {
		type: Object,
		default: () => ({})
	},
	/** Health of titles, descriptions & alt texts, shown above the tabs */
	stats: Object,
	tab: String,
	tabs: Array,
	/** Active issue filter */
	issue: String,
	/** Whether the current user may use AI features */
	ai: Boolean,
	/** Whether Google Search Console is connected */
	gsc: Boolean,
	/** Ids of all pages matching the current search & filters */
	ids: {
		type: Array,
		default: () => []
	},
	/** Active filter for pages sharing a title/description: `{ hash, kind, text, count }` */
	group: Object,
	// only read by `panel.content` (e.g. when switching languages), declared so they don't end up as attributes
	api: String,
	lock: Object,
	versions: Object
})

const panel = usePanel()
const helpers = useHelpers()

const toText = (html) => new window.DOMParser().parseFromString(html, "text/html").body.textContent

const {
	reload,
	searchterm,
	isSearching,
	toggleSearch,
	onSort,
	onPaginate,
	selected,
	isAllSelected,
	selectAll,
	serverSummary,
	serverStats,
	fetchRows,
	onInput,
	onCommit,
	withPending,
	isSaving,
	targets,
	isProcessing,
	onSave,
	onDiscard,
	onLock,
	generation,
	isGenerating,
	runGeneration,
	confirmGeneration,
	cancelGeneration,
	settings,
	isVisible,
	columnOptions,
	visibleColumnKeys,
	onColumns
} = useOverviewTable(props, {
	endpoint: "seo/overview/pages",
	storageKey: "kirby-seo.overview.table",
	// writer fields store HTML, the table shows plain text
	applyPending: (cell, { value }) => ({
		...cell,
		value,
		text: value ? toText(value) : cell.placeholder,
		source: value ? "fields" : cell.placeholderSource
	})
})

const copyUrl = async (url) => {
	await window.navigator.clipboard.writeText(url)
	panel.notification.success(panel.t("copy.success"))
}

const items = computed(() =>
	props.rows.map((row) => {
		const item = withPending(row)

		return {
			...item,
			// same status flag as in Kirby's pages sections
			flag: {
				...helpers.page.status(item.status, item.permissions.changeStatus === false),
				class: "k-page-status-icon-option",
				dialog: item.link + "/changeStatus"
			},
			options: [
				{
					icon: "open",
					text: panel.t("seo.overview.options.open"),
					link: item.previewUrl,
					target: "_blank",
					disabled: !item.previewUrl
				},
				{
					icon: "copy",
					text: panel.t("copy.url"),
					click: () => copyUrl(item.url)
				},
				"-",
				...(props.gsc
					? [
							{
								icon: "google",
								text: panel.t("seo.sections.searchConsole.title"),
								click: () => panel.drawer.open(`seo/gsc/data/${item.link.replace(/^\//, "")}`)
							}
						]
					: []),
				{
					icon: "search",
					text: panel.t("seo.overview.checks.open"),
					link: `${item.link}?tab=seo`
				},
				{
					icon: "page",
					text: panel.t("seo.overview.options.panel"),
					link: item.link
				}
			]
		}
	})
)

const columnsDropdown = ref(null)

const visibleColumns = computed(() =>
	Object.fromEntries(
		Object.entries(props.columns)
			.filter(([key]) => isVisible(key))
			// cells receive the column config, issues filter the table
			.map(([key, column]) => {
				if (column.type === "seo-checks") {
					return [key, { ...column, filterGroup, filterIssue: setFilter }]
				}

				if (column.editable && props.ai) {
					return [key, { ...column, generate: (row) => runGeneration([row], key) }]
				}

				return [key, column]
			})
	)
)

/**
 * Checks: the filter dropdown shows the number of pages per issue type & filters the table by them
 */
const checksDropdown = ref(null)

// all checks with the number of affected pages, for the checks dropdown
const filters = computed(() =>
	Object.entries(serverSummary.value).map(([type, count]) => ({
		type,
		count,
		severity: SEVERITY[type]
	}))
)

const checksLabel = computed(() => {
	if (props.group) {
		return panel.t(`seo.overview.checks.group.${props.group.kind}`)
	}

	if (props.issue) {
		return panel.t(`seo.overview.checks.${props.issue}`)
	}

	return panel.t("seo.overview.checks.filter.all")
})

// duplicates are sorted by their value, so pages sharing the same one are next to each other
const DUPLICATE_SORT = {
	titleDuplicate: "metaTitle",
	descriptionDuplicate: "metaDescription"
}

const setFilter = (issue = "") =>
	reload({
		issue,
		group: "",
		page: "1",
		...(DUPLICATE_SORT[issue] ? { sort: DUPLICATE_SORT[issue], dir: "asc" } : {})
	})

// the stats filter this tab or another one
const onFilter = (issue, tab) =>
	tab === props.tab ? setFilter(issue) : panel.view.open(`seo/${tab}`, { query: { issue } })

// pages sharing the same title/description as a page
function filterGroup(group) {
	reload({ group, issue: "", page: "1" })
}

const generateDropdown = ref(null)

const editableColumns = computed(() =>
	Object.entries(props.columns)
		.filter(([, column]) => column.editable)
		.map(([key, column]) => ({ key, label: column.label }))
)

const onGenerate = async (column) => {
	const candidates = (await fetchRows(selected.value)).filter((row) => row[column]?.ai)

	confirmGeneration({
		rows: candidates,
		column,
		text: panel.t("seo.overview.ai.confirm", {
			count: candidates.length,
			field: props.columns[column].label
		}),
		onlyEmpty: panel.t("seo.overview.ai.onlyEmpty")
	})
}
</script>

<template>
	<k-seo-view
		:buttons="buttons"
		:stats="serverStats"
		:tab="tab"
		:tabs="tabs"
		:busy="isGenerating"
		class="k-seo-overview-view"
		@filter="onFilter"
	>
		<template #buttons>
			<k-seo-changes-controls
				:inert="isGenerating"
				:changes="targets"
				:is-processing="isProcessing || isSaving"
				@discard="onDiscard"
				@submit="onSave"
			/>
		</template>

		<template #toolbar>
			<k-button-group v-if="generation" layout="collapsed">
				<k-button
					:text="$t('seo.overview.ai.progress', generation)"
					icon="loader"
					size="xs"
					variant="filled"
				/>
				<k-button
					:title="$t('seo.ai.action.stop')"
					icon="cancel-small"
					size="xs"
					variant="filled"
					@click="cancelGeneration"
				/>
			</k-button-group>
			<template v-else-if="ai && selected.length">
				<k-button
					:text="$t('seo.overview.ai.generate')"
					:dropdown="true"
					icon="seo-ai"
					size="xs"
					variant="filled"
					@click="generateDropdown.toggle()"
				/>
				<k-dropdown-content ref="generateDropdown" align-x="end">
					<k-dropdown-item
						v-for="column in editableColumns"
						:key="column.key"
						icon="seo-ai"
						@click="onGenerate(column.key)"
					>
						{{ column.label }}
					</k-dropdown-item>
				</k-dropdown-content>
			</template>
			<template v-if="!generation">
				<k-button-group v-if="selected.length" layout="collapsed">
					<k-button
						:text="$t('seo.overview.selection.count', { count: selected.length })"
						size="xs"
						variant="filled"
					/>
					<k-button
						v-if="!isAllSelected"
						:text="$t('seo.overview.selection.all', { count: ids.length })"
						size="xs"
						variant="filled"
						@click="selectAll"
					/>
					<k-button
						:title="$t('seo.overview.selection.clear')"
						icon="cancel-small"
						size="xs"
						variant="filled"
						@click="selected = []"
					/>
				</k-button-group>

				<!-- separate buttons, like in the header of Kirby's sections -->
				<k-button
					:text="$t('filter')"
					:current="isSearching"
					icon="filter"
					size="xs"
					variant="filled"
					responsive
					@click="toggleSearch"
				/>
				<!-- the active filter & its reset belong together -->
				<k-button-group layout="collapsed">
					<k-button
						:text="checksLabel"
						:title="group ? group.text : null"
						:theme="issue || group ? 'info' : null"
						:dropdown="true"
						icon="checklist"
						size="xs"
						variant="filled"
						class="k-seo-overview-checks-button"
						@click="checksDropdown.toggle()"
					/>
					<k-button
						v-if="issue || group"
						:title="$t('seo.overview.checks.filter.clear')"
						icon="cancel-small"
						size="xs"
						variant="filled"
						theme="info"
						@click="setFilter()"
					/>
				</k-button-group>
				<k-button
					:title="$t('seo.overview.columns.toggle')"
					icon="layout-columns"
					size="xs"
					variant="filled"
					@click="columnsDropdown.toggle()"
				/>

				<k-dropdown-content ref="checksDropdown" align-x="end">
					<k-dropdown-item :current="!issue && !group" icon="page" @click="setFilter()">
						{{ $t("seo.overview.checks.filter.all") }}
					</k-dropdown-item>
					<hr />
					<k-dropdown-item
						v-for="{ type, count, severity } in filters"
						:key="type"
						:current="issue === type"
						:disabled="count === 0 && issue !== type"
						:theme="`${severity}-icon`"
						:icon="SEVERITY_ICONS[severity]"
						class="k-seo-overview-checks-item"
						@click="setFilter(type)"
					>
						{{ $t(`seo.overview.checks.${type}`) }}
						<span class="k-seo-overview-checks-count">{{ count }}</span>
					</k-dropdown-item>
				</k-dropdown-content>
				<k-picklist-dropdown
					ref="columnsDropdown"
					:options="columnOptions"
					:value="visibleColumnKeys"
					:search="false"
					@input="onColumns"
				/>
			</template>
		</template>

		<div :inert="isGenerating">
			<k-input
				v-if="isSearching"
				:autofocus="true"
				:value="searchterm"
				:placeholder="$t('filter') + ' …'"
				icon="search"
				type="text"
				class="k-seo-overview-search"
				@input="searchterm = $event"
				@keydown.native.esc="toggleSearch"
			/>

			<k-seo-table
				:columns="visibleColumns"
				:rows="items"
				:pagination="pagination"
				:sort="sort"
				:dir="dir"
				:selected.sync="selected"
				:widths.sync="settings.widths"
				:empty="$t('seo.overview.empty')"
				resizable
				selectable
				@input="onInput"
				@commit="onCommit"
				@lock="onLock"
				@sort="onSort"
				@paginate="onPaginate"
			/>
		</div>
	</k-seo-view>
</template>

<style>
/* pages without a translation in the current language show the content of the default language */
.k-seo-overview-view tbody tr:has(.k-seo-page-cell[data-translated="false"]) {
	.k-seo-page-cell-title,
	.k-seo-meta-cell-text,
	.k-seo-meta-cell-empty {
		color: var(--color-text-dimmed);
	}

	.k-seo-meta-cell-source,
	.k-seo-image-cell .k-frame {
		opacity: 0.5;
	}
}

.k-seo-overview-checks-button .k-button-text {
	max-width: 24ch;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* the number of pages, aligned to the end of the dropdown item */
.k-seo-overview-checks-item .k-button-text {
	display: flex;
	flex-grow: 1;
	gap: var(--spacing-6);
}

.k-seo-overview-checks-count {
	margin-inline-start: auto;
	font-variant-numeric: tabular-nums;
}

/* same as in Kirby's pages sections */
.k-seo-overview-search.k-input {
	--input-color-back: var(--color-border);
	--input-color-border: transparent;
	margin-bottom: var(--spacing-3);
}
</style>
