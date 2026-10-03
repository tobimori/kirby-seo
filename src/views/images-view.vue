<script setup>
import { computed, ref, usePanel } from "kirbyuse"

import { useOverviewTable } from "../composables/overview-table.js"
import { SEVERITY_ICONS } from "../utils/checks.js"

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
	/** Severity per state of the alt texts */
	severity: Object,
	/** Number of images per filter */
	summary: {
		type: Object,
		default: () => ({})
	},
	/** Health of titles, descriptions & alt texts, shown above the tabs */
	stats: Object,
	tab: String,
	tabs: Array,
	/** Active filter: `missing`, `ai`, `decorative` or `issues` (missing & AI-generated) */
	issue: String,
	/** Whether the current user may use AI features */
	ai: Boolean,
	/** Ids of all rows matching the current search & filters */
	ids: {
		type: Array,
		default: () => []
	},
	// only read by `panel.content` (e.g. when switching languages), declared so they don't end up as attributes
	api: String,
	lock: Object,
	versions: Object
})

const panel = usePanel()

const AI_SOURCES = ["ai", "reviewed"]

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
	saveNow,
	withPending,
	isSaving,
	targets,
	isProcessing,
	onSave,
	onDiscard,
	status,
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
	endpoint: "seo/overview/images",
	storageKey: "kirby-seo.overview.images.table",
	// same rules as the server: a description makes the image non-decorative,
	// editing an AI-generated text means it has been reviewed
	applyPending: (cell, { value, source }) => ({
		...cell,
		value,
		text: value,
		decorative: value ? false : cell.decorative,
		source:
			source ??
			(value === cell.value
				? cell.source
				: AI_SOURCES.includes(cell.source)
					? "reviewed"
					: "manual")
	}),
	// same checks as the table, e.g. fields with `ai: false`
	aiRequest: (row) => ({
		url: `${panel.urls.api}/seo/overview/images/generate`,
		body: { id: row.id }
	})
})

/**
 * State of an alt text, like the checks of pages
 */
const stateOf = (alt) => {
	if (alt.decorative) {
		return "decorative"
	}

	if (!alt.value?.trim()) {
		return "missing"
	}

	return alt.source === "ai" ? "ai" : "ok"
}

const tagOf = (state) =>
	state === "ai"
		? {
				text: panel.t(`seo.overview.images.tag.${state}`),
				title: panel.t(`seo.overview.images.status.${state}`)
			}
		: null

const copyUrl = async (url) => {
	await window.navigator.clipboard.writeText(url)
	panel.notification.success(panel.t("copy.success"))
}

/**
 * Bulk actions: marking AI-generated texts as reviewed & images as decorative (or not)
 */
const canReview = (row) => row.editable && row.alt.source === "ai"
const canSetDecorative = (row, decorative) => row.editable && row.alt.decorative !== decorative

const mark = async (rows, action) => {
	const changes = {
		reviewed: (row) => canReview(row) && { column: "source", value: "reviewed" },
		decorative: (row) => canSetDecorative(row, true) && { column: "decorative", value: true },
		described: (row) => canSetDecorative(row, false) && { column: "decorative", value: false }
	}[action]

	const list = rows.flatMap((row) => {
		const change = changes(row)
		return change ? [{ id: row.id, ...change }] : []
	})

	if (list.length === 0) {
		return panel.notification.info(panel.t("seo.overview.images.mark.none"))
	}

	if ((await saveNow(list)) && rows.length > 1) {
		panel.notification.success(panel.t("seo.overview.images.mark.done", { count: list.length }))
	}
}

// the decorative column: shows the new state right away, until the server confirms it
const decorativePending = ref({})

const setDecorative = async (row, value) => {
	decorativePending.value = { ...decorativePending.value, [row.id]: value }

	try {
		await saveNow([{ id: row.id, column: "decorative", value }])
	} finally {
		const next = { ...decorativePending.value }
		delete next[row.id]
		decorativePending.value = next
	}
}

// without images (with alt text fields), there's nothing to list, filter or edit
const hasImages = computed(() =>
	Object.values(serverStats.value?.images ?? {}).some((count) => count > 0)
)

const markDropdown = ref(null)
const onMark = async (action) => mark(await fetchRows(selected.value), action)

const items = computed(() =>
	props.rows.map((row) => {
		const item = withPending(row)

		if (row.id in decorativePending.value) {
			item.alt = { ...item.alt, decorative: decorativePending.value[row.id] }
		}

		const state = stateOf(item.alt)

		return {
			...item,
			status: { state, severity: props.severity[state] },
			decorative: {
				value: item.alt.decorative,
				editable: item.editable
			},
			alt: {
				...item.alt,
				// the source of alt texts is shown as status & tag, not as inherited value
				source: null,
				text: item.alt.decorative
					? panel.t("seo.overview.images.status.decorative")
					: item.alt.value,
				tag: tagOf(state),
				dimmed: item.alt.decorative
			},
			options: [
				{
					icon: "open",
					text: panel.t("seo.overview.images.options.open"),
					link: item.previewUrl,
					target: "_blank"
				},
				{
					icon: "copy",
					text: panel.t("copy.url"),
					click: () => copyUrl(item.url)
				},
				"-",
				{
					icon: "check",
					text: panel.t("seo.overview.images.options.reviewed"),
					disabled: !canReview(item),
					click: () => mark([item], "reviewed")
				},
				item.alt.decorative
					? {
							icon: "preview",
							text: panel.t("seo.overview.images.options.described"),
							disabled: !canSetDecorative(item, false),
							click: () => mark([item], "described")
						}
					: {
							icon: "hidden",
							text: panel.t("seo.overview.images.options.decorative"),
							disabled: !canSetDecorative(item, true),
							click: () => mark([item], "decorative")
						},
				"-",
				{
					icon: "image",
					text: panel.t("seo.overview.images.options.file"),
					link: item.link
				},
				{
					icon: item.parent.link.startsWith("/site") ? "home" : "page",
					text: panel.t("seo.overview.images.options.parent"),
					link: item.parent.link
				}
			]
		}
	})
)

const visibleColumns = computed(() =>
	Object.fromEntries(
		Object.entries(props.columns)
			.filter(([key]) => isVisible(key))
			// cells receive the column config, states filter the table
			.map(([key, column]) => {
				if (column.type === "seo-alt-status") {
					return [key, { ...column, filterIssue: setFilter }]
				}

				if (column.type === "seo-decorative") {
					return [key, { ...column, toggle: setDecorative }]
				}

				// like the alt text field: generating a single text means the editor sees (& reviews) it
				if (column.editable && props.ai) {
					return [
						key,
						{ ...column, generate: (row) => runGeneration([row], key, { source: "reviewed" }) }
					]
				}

				return [key, column]
			})
	)
)

/**
 * Filters: the dropdown shows the number of images per state & filters the table by them
 */
const filters = computed(() =>
	Object.entries(serverSummary.value).map(([type, count]) => ({
		type,
		count,
		text: panel.t(`seo.overview.images.filter.${type}`),
		theme: `${props.severity[type]}-icon`,
		icon: type === "decorative" ? "hidden" : SEVERITY_ICONS[props.severity[type]]
	}))
)

const filterLabel = computed(() =>
	panel.t(
		props.issue ? `seo.overview.images.filter.${props.issue}` : "seo.overview.images.filter.all"
	)
)

const setFilter = (issue = "") => reload({ issue, page: "1" })

const onGenerate = async () => {
	const candidates = (await fetchRows(selected.value)).filter(
		(row) => row.alt.ai && !row.alt.decorative
	)

	if (candidates.length === 0) {
		return panel.notification.info(panel.t("seo.overview.images.ai.none"))
	}

	confirmGeneration({
		rows: candidates,
		column: "alt",
		text: panel.t("seo.overview.images.ai.confirm", { count: candidates.length }),
		onlyEmpty: panel.t("seo.overview.images.ai.onlyEmpty"),
		// generated texts need a review
		source: "ai"
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
		:status="status"
		class="k-seo-overview-view k-seo-images-view"
		@filter="setFilter"
	>
		<template #buttons>
			<k-seo-changes-controls
				:inert="isGenerating"
				:changes="targets"
				:is-processing="isProcessing || isSaving"
				icon="image"
				@discard="onDiscard"
				@submit="onSave"
			/>
		</template>

		<template #toolbar>
			<k-seo-generation v-if="generation" :generation="generation" @cancel="cancelGeneration" />
			<template v-else-if="hasImages">
				<template v-if="selected.length">
					<k-button
						v-if="ai"
						:text="$t('seo.overview.ai.generate')"
						icon="seo-ai"
						size="xs"
						variant="filled"
						@click="onGenerate"
					/>
					<k-button
						:text="$t('seo.overview.images.mark')"
						:dropdown="true"
						icon="check"
						size="xs"
						variant="filled"
						@click="markDropdown.toggle()"
					/>
					<k-dropdown-content ref="markDropdown" align-x="end">
						<k-dropdown-item icon="check" @click="onMark('reviewed')">
							{{ $t("seo.overview.images.mark.reviewed") }}
						</k-dropdown-item>
						<k-dropdown-item icon="hidden" @click="onMark('decorative')">
							{{ $t("seo.overview.images.mark.decorative") }}
						</k-dropdown-item>
						<k-dropdown-item icon="preview" @click="onMark('described')">
							{{ $t("seo.overview.images.mark.described") }}
						</k-dropdown-item>
					</k-dropdown-content>

					<k-seo-selection
						:count="selected.length"
						:total="ids.length"
						:all="isAllSelected"
						@all="selectAll"
						@clear="selected = []"
					/>
				</template>

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
					:clear="$t('seo.overview.images.filter.clear')"
					:all="{ text: $t('seo.overview.images.filter.all'), icon: 'image' }"
					:current="issue"
					:filters="filters"
					@filter="setFilter"
				/>
				<k-seo-columns :options="columnOptions" :value="visibleColumnKeys" @input="onColumns" />
			</template>
		</template>

		<k-empty v-if="!hasImages" icon="image" layout="table">
			{{ $t("seo.overview.images.empty.noImages") }}
		</k-empty>

		<div v-else :inert="isGenerating">
			<k-seo-table
				:columns="visibleColumns"
				:rows="items"
				:pagination="pagination"
				:sort="sort"
				:dir="dir"
				:selected.sync="selected"
				:widths.sync="settings.widths"
				:empty="$t('seo.overview.images.empty')"
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
