<script setup>
import { computed, onBeforeUnmount, onMounted, ref, usePanel, watch } from "kirbyuse"

import { useColumnSettings, useTableQuery } from "../composables/overview-table.js"
import { SEVERITY_ICONS } from "../utils/checks.js"

const props = defineProps({
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
	/** Number of links per state */
	summary: {
		type: Object,
		default: () => ({})
	},
	/** Health of titles, descriptions, alt texts & links, shown above the tabs */
	stats: Object,
	tab: String,
	tabs: Array,
	/** Active filter: `broken`, `anchor`, `redirect`, `unknown` or `issues` (broken, anchors & redirects) */
	issue: String,
	/** `content` to only list links in the main content of pages */
	scope: String,
	/** `{ pages: { done, total }, urls: { done, total }, done, running, queue }` */
	progress: Object,
	/** Whether any page has been checked yet */
	scanned: Boolean,
	// only read by `panel.content` (e.g. when switching languages), declared so they don't end up as attributes
	api: String,
	lock: Object,
	versions: Object
})

const panel = usePanel()

const { reload, searchterm, isSearching, toggleSearch, onSort, onPaginate } = useTableQuery(props)
const { settings, isVisible, columnOptions, visibleColumnKeys, onColumns } = useColumnSettings(
	props,
	"kirby-seo.overview.links.table"
)

/**
 * The check runs in steps: without queues, the Panel runs them while the tab is open,
 * with queues, the Panel only shows the progress of the queue worker
 */
const POLL_DELAY = 5000

const progress = ref(props.progress)
watch(
	() => props.progress,
	(value) => (progress.value = value)
)

const isScanning = computed(() => !progress.value.done)
const isCheckingPages = computed(() => progress.value.pages.done < progress.value.pages.total)

const progressText = computed(() => {
	const { pages, urls } = progress.value
	return isCheckingPages.value
		? panel.t("seo.overview.links.progress.pages", pages)
		: panel.t("seo.overview.links.progress.urls", urls)
})

let isActive = false
const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms))

const run = async () => {
	if (isActive) {
		return
	}

	isActive = true
	let hadPages = !isCheckingPages.value

	try {
		while (isActive && !progress.value.done) {
			const response = await panel.api.post("seo/overview/links/scan", {}, { silent: true })

			if (!isActive) {
				return
			}

			// a template ended the request while rendering, the next step continues
			if (response.retry) {
				continue
			}

			progress.value = response

			// internal links are complete before the external ones are checked
			if (!hadPages && !isCheckingPages.value) {
				hadPages = true
				await panel.view.reload()
			}

			// another scan (or the queue worker) is running
			if (response.queue || response.running) {
				await wait(POLL_DELAY)
			}
		}
	} catch (error) {
		isActive = false
		return panel.notification.error(error)
	}

	if (isActive) {
		isActive = false
		panel.view.reload()
	}
}

// rendering all pages again takes a while
const rescan = () =>
	panel.dialog.open({
		component: "k-text-dialog",
		props: {
			text: panel.t("seo.overview.links.rescan.confirm", { pages: progress.value.pages.total }),
			submitButton: { text: panel.t("seo.overview.links.rescan"), icon: "refresh" }
		},
		on: {
			submit: async () => {
				panel.dialog.close()
				progress.value = await panel.api.post("seo/overview/links/rescan")
				run()
			}
		}
	})

const recheck = async (row) => {
	progress.value = await panel.api.post("seo/overview/links/recheck", { url: row.id })
	run()
}

onMounted(() => {
	if (isScanning.value) {
		run()
	}
})

onBeforeUnmount(() => (isActive = false))

/**
 * Filters: the dropdown shows the number of links per state & filters the table by them
 */
const filtersDropdown = ref(null)

const SEVERITIES = { broken: "negative", anchor: "notice", redirect: "notice", unknown: "unknown" }
const ICONS = { ...SEVERITY_ICONS, unknown: "question" }

const filters = computed(() =>
	Object.entries(props.summary).map(([type, count]) => ({
		type,
		count,
		theme: SEVERITIES[type] === "unknown" ? null : `${SEVERITIES[type]}-icon`,
		icon: ICONS[SEVERITIES[type]]
	}))
)

const filterLabel = computed(() =>
	panel.t(
		props.issue ? `seo.overview.links.filter.${props.issue}` : "seo.overview.links.filter.all"
	)
)

const setFilter = (issue = "") => reload({ issue, page: "1" })
const toggleScope = () => reload({ scope: props.scope ? "" : "content", page: "1" })

// the stats filter this tab or another one
const onFilter = (issue, tab) =>
	tab === props.tab ? setFilter(issue) : panel.view.open(`seo/${tab}`, { query: { issue } })

const copyUrl = async (url) => {
	await window.navigator.clipboard.writeText(url)
	panel.notification.success(panel.t("copy.success"))
}

const items = computed(() =>
	props.rows.map((row) => ({
		...row,
		options: [
			{
				icon: "open",
				text: panel.t("seo.overview.links.options.open"),
				link: row.url.href,
				target: "_blank"
			},
			{
				icon: "copy",
				text: panel.t("copy.url"),
				click: () => copyUrl(row.url.href)
			},
			"-",
			{
				icon: "refresh",
				text: panel.t("seo.overview.links.recheck"),
				disabled: isScanning.value,
				click: () => recheck(row)
			},
			"-",
			{
				icon: "page",
				text: panel.t("seo.overview.links.pages.show"),
				click: () => openPages(row)
			}
		]
	}))
)

const columnsDropdown = ref(null)

const visibleColumns = computed(() =>
	Object.fromEntries(Object.entries(props.columns).filter(([key]) => isVisible(key)))
)

// like in Retour, a click on a row opens its details
const openPages = (row) => panel.drawer.open("seo/links/pages", { query: { url: row.id } })
</script>

<template>
	<k-seo-view
		:buttons="buttons"
		:stats="stats"
		:tab="tab"
		:tabs="tabs"
		class="k-seo-overview-view k-seo-links-view"
		@filter="onFilter"
	>
		<template #toolbar>
			<k-button v-if="isScanning" :text="progressText" icon="loader" size="xs" variant="filled" />
			<k-button
				v-else
				:text="$t('seo.overview.links.rescan')"
				icon="refresh"
				size="xs"
				variant="filled"
				@click="rescan"
			/>

			<template v-if="scanned">
				<k-seo-search
					:value="searchterm"
					:searching="isSearching"
					@input="searchterm = $event"
					@toggle="toggleSearch"
				/>
				<!-- the active filter & its reset belong together -->
				<k-button-group layout="collapsed">
					<k-button
						:text="filterLabel"
						:theme="issue || scope ? 'info' : null"
						:dropdown="true"
						icon="checklist"
						size="xs"
						variant="filled"
						class="k-seo-overview-checks-button"
						@click="filtersDropdown.toggle()"
					/>
					<k-button
						v-if="issue"
						:title="$t('seo.overview.links.filter.clear')"
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

				<k-dropdown-content ref="filtersDropdown" align-x="end">
					<k-dropdown-item :current="!issue" icon="url" @click="setFilter()">
						{{ $t("seo.overview.links.filter.all") }}
					</k-dropdown-item>
					<k-dropdown-item :current="issue === 'issues'" icon="alert" @click="setFilter('issues')">
						{{ $t("seo.overview.links.filter.issues") }}
					</k-dropdown-item>
					<hr />
					<k-dropdown-item
						v-for="filter in filters"
						:key="filter.type"
						:current="issue === filter.type"
						:disabled="filter.count === 0 && issue !== filter.type"
						:theme="filter.theme"
						:icon="filter.icon"
						class="k-seo-overview-checks-item"
						@click="setFilter(filter.type)"
					>
						{{ $t(`seo.overview.links.filter.${filter.type}`) }}
						<span class="k-seo-overview-checks-count">{{ filter.count }}</span>
					</k-dropdown-item>
					<hr />
					<k-dropdown-item
						:current="scope === 'content'"
						:icon="scope === 'content' ? 'toggle-on' : 'toggle-off'"
						@click="toggleScope"
					>
						{{ $t("seo.overview.links.filter.content") }}
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

		<k-empty v-if="!scanned" :icon="isScanning ? 'loader' : 'url'" layout="table">
			{{
				$t(isScanning ? "seo.overview.links.empty.scanning" : "seo.overview.links.empty.noLinks")
			}}
		</k-empty>

		<k-seo-table
			v-else
			:columns="visibleColumns"
			:rows="items"
			:pagination="pagination"
			:sort="sort"
			:dir="dir"
			:widths.sync="settings.widths"
			:empty="$t('seo.overview.links.empty')"
			resizable
			@cell="({ row, columnIndex }) => columnIndex !== '_index' && openPages(row)"
			@sort="onSort"
			@paginate="onPaginate"
		/>
	</k-seo-view>
</template>

<style>
/* a click on a row opens its details */
.k-seo-links-view .k-table-cell {
	cursor: pointer;
}
</style>
