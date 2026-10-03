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
	/** Severity per state of the links */
	severity: Object,
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
	const { pages, urls, queue, running } = progress.value

	// the job hasn't started yet, or no worker is running
	if (queue && !running) {
		return panel.t("seo.overview.links.progress.queue")
	}

	return isCheckingPages.value
		? panel.t("seo.overview.links.progress.pages", pages)
		: panel.t("seo.overview.links.progress.urls", urls)
})

// for screen readers: only when the phase changes, the numbers change every few seconds
const phase = computed(() => {
	const { queue, running, done } = progress.value

	if (done) {
		return "done"
	}

	return queue && !running ? "queue" : isCheckingPages.value ? "pages" : "urls"
})

const status = ref("")
watch(phase, (value) => {
	status.value = value === "done" ? panel.t("seo.overview.links.progress.done") : progressText.value
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
const ICONS = { ...SEVERITY_ICONS, unknown: "question" }

const filters = computed(() =>
	Object.entries(props.summary).map(([type, count]) => ({
		type,
		count,
		text: panel.t(`seo.overview.links.filter.${type}`),
		theme: props.severity[type] === "unknown" ? null : `${props.severity[type]}-icon`,
		icon: ICONS[props.severity[type]]
	}))
)

const filterLabel = computed(() =>
	panel.t(
		props.issue ? `seo.overview.links.filter.${props.issue}` : "seo.overview.links.filter.all"
	)
)

const setFilter = (issue = "") => reload({ issue, page: "1" })

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
			}
		]
	}))
)

const visibleColumns = computed(() =>
	Object.fromEntries(Object.entries(props.columns).filter(([key]) => isVisible(key)))
)
</script>

<template>
	<k-seo-view
		:buttons="buttons"
		:stats="stats"
		:tab="tab"
		:tabs="tabs"
		:status="status"
		class="k-seo-overview-view k-seo-links-view"
		@filter="setFilter"
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
				<k-seo-filter
					:label="filterLabel"
					:active="Boolean(issue)"
					:clearable="Boolean(issue)"
					:clear="$t('seo.overview.links.filter.clear')"
					:all="{ text: $t('seo.overview.links.filter.all'), icon: 'url' }"
					:current="issue"
					:filters="filters"
					@filter="setFilter"
				>
					<template #before>
						<k-dropdown-item
							:current="issue === 'issues'"
							icon="alert"
							@click="setFilter('issues')"
						>
							{{ $t("seo.overview.links.filter.issues") }}
						</k-dropdown-item>
					</template>
				</k-seo-filter>
				<k-seo-columns :options="columnOptions" :value="visibleColumnKeys" @input="onColumns" />
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
			@sort="onSort"
			@paginate="onPaginate"
		/>
	</k-seo-view>
</template>
