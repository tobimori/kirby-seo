<script setup>
import { computed, ref, usePanel } from "kirbyuse"

import { useOverviewTable } from "../composables/overview-table.js"
import { SEVERITY_ICONS } from "../utils/checks.js"

import { editableOverviewProps } from "./props.js"

const props = defineProps(editableOverviewProps)

const panel = usePanel()

const {
	searchterm,
	isSearching,
	toggleSearch,
	onSort,
	onPaginate,
	onFilter,
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
	columnOptions,
	visibleColumns,
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
				: ["ai", "reviewed"].includes(cell.source)
					? "reviewed"
					: "manual")
	}),
	aiRequest: (row) => ({
		url: `${panel.urls.api}/seo/overview/images/generate`,
		body: { id: row.id }
	})
})

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

		const state = item.alt.decorative
			? "decorative"
			: !item.alt.value?.trim()
				? "missing"
				: item.alt.source === "ai"
					? "ai"
					: "ok"

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
				tag:
					state === "ai"
						? {
								text: panel.t("seo.overview.images.tag.ai"),
								title: panel.t("seo.overview.images.status.ai")
							}
						: null,
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
					click: { global: "clipboard.write", payload: item.url }
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

const tableColumns = computed(() =>
	Object.fromEntries(
		Object.entries(visibleColumns.value).map(([key, column]) => {
			if (column.type === "seo-alt-status") {
				return [key, { ...column, filterIssue: onFilter }]
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
		@filter="onFilter"
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
					@filter="onFilter"
				/>
				<k-seo-columns :options="columnOptions" :value="visibleColumnKeys" @input="onColumns" />
			</template>
		</template>

		<k-empty v-if="!hasImages" icon="image" layout="table">
			{{ $t("seo.overview.images.empty.noImages") }}
		</k-empty>

		<div v-else :inert="isGenerating">
			<k-seo-table
				:columns="tableColumns"
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
