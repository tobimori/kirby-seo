<script setup>
import { computed, onBeforeUnmount, onMounted, ref, usePanel, watch } from "kirbyuse"

import { useColumnSettings, useTableQuery } from "../composables/overview-table.js"
import { SEVERITY_ICONS } from "../utils/checks.js"

import { overviewProps } from "./props.js"

const props = defineProps({
	...overviewProps,
	/** { pages: { done, total }, urls: { done, total }, done, running, queue } */
	progress: Object,
	scanned: Boolean
})

const panel = usePanel()

const { searchterm, isSearching, toggleSearch, onSort, onPaginate, onFilter } = useTableQuery(props)
const { settings, columnOptions, visibleColumns, visibleColumnKeys, onColumns } = useColumnSettings(
	props,
	"kirby-seo.overview.links.table"
)

// Panel requests perform scan steps without queues; otherwise they only poll progress
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

			// Refresh internal link results as soon as all page scans finish
			if (!hadPages && !isCheckingPages.value) {
				hadPages = true
				await panel.view.reload()
			}

			// another scan (or the queue worker) is running
			if (response.queue || response.running) {
				await new Promise((resolve) => window.setTimeout(resolve, 5000))
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

const filters = computed(() =>
	Object.entries(props.summary).map(([type, count]) => ({
		type,
		count,
		text: panel.t(`seo.overview.links.filter.${type}`),
		theme: props.severity[type] === "unknown" ? null : `${props.severity[type]}-icon`,
		icon: SEVERITY_ICONS[props.severity[type]]
	}))
)

const filterLabel = computed(() =>
	panel.t(
		props.issue ? `seo.overview.links.filter.${props.issue}` : "seo.overview.links.filter.all"
	)
)

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
				click: { global: "clipboard.write", payload: row.url.href }
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
</script>

<template>
	<k-seo-view
		:buttons="buttons"
		:license="license"
		:stats="stats"
		:tab="tab"
		:tabs="tabs"
		:status="status"
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
				<k-seo-filter
					:label="filterLabel"
					:active="Boolean(issue)"
					:clearable="Boolean(issue)"
					:clear="$t('seo.overview.links.filter.clear')"
					:all="{ text: $t('seo.overview.links.filter.all'), icon: 'url' }"
					:current="issue"
					:filters="filters"
					@filter="onFilter"
				>
					<template #before>
						<k-dropdown-item :current="issue === 'issues'" icon="alert" @click="onFilter('issues')">
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
