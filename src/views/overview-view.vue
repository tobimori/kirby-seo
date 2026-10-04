<script setup>
import { computed, ref, usePanel, useHelpers } from "kirbyuse"

import { useOverviewTable } from "../composables/overview-table.js"
import { SEVERITY_ICONS } from "../utils/checks.js"

import { editableOverviewProps } from "./props.js"

const props = defineProps({
	...editableOverviewProps,
	gsc: Boolean,
	/** Active duplicate group: { kind, text } */
	group: Object
})

const panel = usePanel()
const helpers = useHelpers()

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
	endpoint: "seo/overview/pages",
	storageKey: "kirby-seo.overview.table",
	// writer fields store HTML, the table shows plain text
	applyPending: (cell, { value }) => ({
		...cell,
		value,
		text: value ? helpers.string.unescapeHTML(helpers.string.stripHTML(value)) : cell.placeholder,
		source: value ? "fields" : cell.placeholderSource
	})
})

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
					click: { global: "clipboard.write", payload: item.url }
				},
				"-",
				...(props.gsc
					? [
							{
								icon: "google",
								text: panel.t("seo.sections.searchConsole.title"),
								drawer: `seo/gsc/data/${helpers.string.ltrim(item.link, "/")}`
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

const tableColumns = computed(() =>
	Object.fromEntries(
		Object.entries(visibleColumns.value).map(([key, column]) => {
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

const filters = computed(() =>
	Object.entries(serverSummary.value).map(([type, count]) => ({
		type,
		count,
		text: panel.t(`seo.overview.checks.${type}`),
		theme: `${props.severity[type]}-icon`,
		icon: SEVERITY_ICONS[props.severity[type]]
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

const setFilter = (issue = "") => {
	// duplicates are sorted by their value, so pages sharing the same one are next to each other
	const sort = { titleDuplicate: "metaTitle", descriptionDuplicate: "metaDescription" }[issue]
	onFilter(issue, { group: "", ...(sort && { sort, dir: "asc" }) })
}

const filterGroup = (group) => onFilter("", { group })

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
		:license="license"
		:stats="serverStats"
		:tab="tab"
		:tabs="tabs"
		:busy="isGenerating"
		:status="status"
		class="k-seo-overview-view"
		@filter="setFilter"
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
			<k-seo-generation v-if="generation" :generation="generation" @cancel="cancelGeneration" />
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
				<k-seo-selection
					v-if="selected.length"
					:count="selected.length"
					:total="ids.length"
					:all="isAllSelected"
					@all="selectAll"
					@clear="selected = []"
				/>
				<k-seo-search
					:value="searchterm"
					:searching="isSearching"
					@input="searchterm = $event"
					@toggle="toggleSearch"
				/>
				<k-seo-filter
					:label="checksLabel"
					:title="group ? group.text : null"
					:active="Boolean(issue || group)"
					:clearable="Boolean(issue || group)"
					:clear="$t('seo.overview.checks.filter.clear')"
					:all="{
						text: $t('seo.overview.checks.filter.all'),
						icon: 'page',
						current: !issue && !group
					}"
					:current="issue"
					:filters="filters"
					@filter="setFilter"
				/>
				<k-seo-columns :options="columnOptions" :value="visibleColumnKeys" @input="onColumns" />
			</template>
		</template>

		<div :inert="isGenerating">
			<k-seo-table
				:columns="tableColumns"
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
</style>
