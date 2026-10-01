<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch, usePanel, useHelpers } from "kirbyuse"

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
	/** Active filter for pages sharing a title/description: `{ hash, kind, text, count }` */
	group: Object,
	// only read by `panel.content` (e.g. when switching languages), declared so they don't end up as attributes
	api: String,
	lock: Object,
	versions: Object
})

const panel = usePanel()
const helpers = useHelpers()

const searchterm = ref(props.search ?? "")
// like in Kirby's pages sections, the search field is shown on demand
const isSearching = ref(Boolean(props.search))

// hiding the search field resets the search
const toggleSearch = () => {
	isSearching.value = !isSearching.value

	if (!isSearching.value) {
		searchterm.value = ""
	}
}
const selected = ref([])

// the query is merged with the current one, so empty strings are used to reset values
const reload = (query) => panel.view.reload({ query })

const toText = (html) => new window.DOMParser().parseFromString(html, "text/html").body.textContent

/**
 * Edits belong to the language they were made in: saves that are still on their way
 * when switching languages must not end up in the new language
 */
const currentLanguage = () => panel.language.code
const cellKey = (id, column, language = currentLanguage()) =>
	`${language}\u0000${id}\u0000${column}`
const parseKey = (key) => {
	const [language, id, column] = key.split("\u0000")
	return { language, id, column }
}

/**
 * Rows are updated in place after saving or when checking locks, instead of reloading
 * the view: a reload would resort the table & make rows jump while editing.
 * The order only changes when the user sorts, searches or paginates.
 */
const updates = ref({})
const serverChanges = ref(props.changes)
const serverSummary = ref(props.summary)
const serverStats = ref(props.stats)

watch(
	() => props.rows,
	() => (updates.value = {})
)
watch(
	() => props.changes,
	(value) => (serverChanges.value = value)
)
watch(
	() => props.summary,
	(value) => (serverSummary.value = value)
)
watch(
	() => props.stats,
	(value) => (serverStats.value = value)
)

const serverRow = (row) => updates.value[row.id] ?? row

const applyResponse = (
	{ rows = {}, changes, summary, stats } = {},
	language = currentLanguage()
) => {
	// the response of a request made before switching languages
	if (language !== currentLanguage()) {
		return
	}

	updates.value = { ...updates.value, ...rows }

	if (changes) {
		serverChanges.value = changes
	}

	if (summary) {
		serverSummary.value = summary
	}

	// only the stats of the pages, the alt texts are updated when loading the view
	if (stats) {
		serverStats.value = { ...serverStats.value, ...stats }
	}
}

/**
 * Autosave, same as editing a page: values are written to the changes version
 * (which also locks the page for others) while typing. To keep the number of requests low,
 * changes are collected & sent in a single request for all pages, at most one request is
 * running at a time and typing only triggers a save after a short pause.
 */
const AUTOSAVE_DELAY = 500

// values that still need to be sent
const queue = new Map()
// values that have been typed but are not confirmed by the server yet,
// shown in the table instead of the server values
const pending = ref({})
const isSaving = ref(false)
let timer = null
let request = null

// pages edited in this view (by their Panel link, e.g. `/pages/blog+post`) & language,
// their locks get released when leaving the view or switching languages
const touched = new Map()
const touchedKey = (link, language = currentLanguage()) => `${language}\u0000${link}`

const isLockError = (error) => error?.key?.startsWith("error.content.lock")

const onInput = (changes) => {
	for (const { row, column, value } of changes) {
		const key = cellKey(row.id, column)
		const saved = serverRow(row)[column]?.value ?? ""

		// nothing to do if the value is what the server has & nothing else is on its way
		if (value === saved && !(key in pending.value) && !queue.has(key)) {
			continue
		}

		queue.set(key, { id: row.id, column, value, language: currentLanguage() })
		pending.value = { ...pending.value, [key]: value }
		touched.set(touchedKey(row.link), { api: row.link, language: currentLanguage() })
	}

	window.clearTimeout(timer)
	timer = window.setTimeout(flush, AUTOSAVE_DELAY)
}

// sends right away, e.g. when a cell is left
const onCommit = () => {
	window.clearTimeout(timer)
	flush()
}

const flush = async () => {
	// the running request will flush again once it's done
	if (request || queue.size === 0) {
		return
	}

	// one request per language, the others are sent right after
	const language = queue.values().next().value.language
	const batch = [...queue.entries()].filter(([, change]) => change.language === language)
	batch.forEach(([key]) => queue.delete(key))
	isSaving.value = true

	try {
		request = panel.api.post(
			"seo/overview/save",
			{ changes: batch.map(([, { id, column, value }]) => ({ id, column, value })) },
			{ silent: true, headers: { "x-language": language } }
		)
		const response = await request
		applyResponse(response, language)

		const errors = Object.values(response.errors ?? {})
		const lockError = errors.find(isLockError)

		// someone else started editing a page in the meantime
		if (lockError) {
			panel.content.lockDialog(lockError.details)
		} else if (errors.length) {
			panel.notification.error(errors[0].message)
		}
	} catch (error) {
		panel.notification.error(error)
	} finally {
		request = null

		// values that changed again in the meantime stay pending
		const next = { ...pending.value }
		batch.forEach(([key]) => {
			if (!queue.has(key)) {
				delete next[key]
			}
		})
		pending.value = next

		if (queue.size > 0) {
			flush()
		} else {
			isSaving.value = false
		}
	}
}

// waits until all edits are written, e.g. before publishing
const settled = () =>
	new Promise((resolve) => {
		onCommit()
		const check = () => (!request && queue.size === 0 ? resolve() : window.setTimeout(check, 50))
		check()
	})

const withPending = (row) => {
	const item = { ...row, ...serverRow(row) }

	for (const column of Object.keys(props.columns)) {
		const value = pending.value[cellKey(row.id, column)]

		if (value === undefined) {
			continue
		}

		item[column] = {
			...item[column],
			value,
			text: value ? toText(value) : item[column].placeholder,
			source: value ? "fields" : item[column].placeholderSource
		}
		item.changes = true
		// editing creates the translation
		item.title = { ...item.title, changes: true, translated: true }
	}

	return item
}

const items = computed(() =>
	props.rows.map((row) => {
		const item = withPending(row)

		return {
			...item,
			// same status flag & options dropdown as in Kirby's pages sections
			flag: {
				...helpers.page.status(item.status, item.permissions.changeStatus === false),
				class: "k-page-status-icon-option",
				dialog: item.link + "/changeStatus"
			},
			options: panel.dropdown.openAsync(item.link, { query: { view: "list" } })
		}
	})
)

// all pages with unsaved changes, including the ones that are still being saved
const changes = computed(() => {
	const list = [...serverChanges.value]

	for (const key of Object.keys(pending.value)) {
		const { language, id } = parseKey(key)

		if (language !== currentLanguage()) {
			continue
		}

		const row = props.rows.find((row) => row.id === id)

		if (row && !list.some((page) => page.id === row.id)) {
			list.push({ id: row.id, link: row.link, text: row.title.text })
		}
	}

	return list
})

// updates the visible rows in place (locks, values) & the list of changes
const refreshRows = async () => {
	const language = currentLanguage()

	applyResponse(
		await panel.api.post(
			"seo/overview/rows",
			{ ids: props.rows.map((row) => row.id) },
			{ silent: true }
		),
		language
	)
}

/**
 * Publishing & discarding applies to the selected pages if there is a selection,
 * otherwise to all pages with unsaved changes
 */
const scope = computed(() => (selected.value.length ? "selected" : "all"))

const targets = computed(() =>
	scope.value === "selected"
		? changes.value.filter((page) => selected.value.includes(page.id))
		: changes.value
)

const isProcessing = ref(false)

const runOnChanges = async (action, pages) => {
	isProcessing.value = true

	try {
		const response = await panel.api.post(`seo/overview/${action}`, {
			ids: pages.map((page) => page.id)
		})
		applyResponse(response)

		const errors = Object.values(response.errors ?? {})

		if (errors.some(isLockError)) {
			panel.content.lockDialog(errors.find(isLockError).details)
		} else if (errors.length) {
			panel.notification.error(errors[0].message)
		} else {
			panel.notification.success(
				panel.t(`seo.overview.changes.${action === "publish" ? "published" : "discarded"}`, {
					count: pages.length
				})
			)
		}
	} catch (error) {
		panel.notification.error(error)
	} finally {
		isProcessing.value = false
	}
}

// writes pending edits first, as they might add pages to the list of changes
const prepare = async () => {
	// commit the cell that is currently being edited
	window.document.activeElement?.blur()
	await settled()

	return [...targets.value]
}

const confirm = ({ component, text, submitButton }) =>
	new Promise((resolve) => {
		panel.dialog.open({
			component,
			props: { size: "medium", text, submitButton },
			on: {
				submit: () => {
					resolve(true)
					panel.dialog.close()
				},
				cancel: () => resolve(false),
				close: () => resolve(false)
			}
		})
	})

const onSave = async (event) => {
	event?.preventDefault?.()

	if (isProcessing.value || targets.value.length === 0) {
		return
	}

	const pages = await prepare()

	if (pages.length === 0) {
		return
	}

	runOnChanges("publish", pages)
}

const onDiscard = async () => {
	if (isProcessing.value || targets.value.length === 0) {
		return
	}

	const pages = await prepare()

	if (pages.length === 0) {
		return
	}

	if (
		await confirm({
			component: "k-remove-dialog",
			text: panel.t(`seo.overview.changes.discard.confirm.${scope.value}`, {
				count: pages.length
			}),
			submitButton: { theme: "notice", icon: "undo", text: panel.t("form.discard") }
		})
	) {
		runOnChanges("discard", pages)
	}
}

const onLock = (row) => {
	panel.dialog.open({
		component: "k-lock-alert-dialog",
		props: { lock: row.lock },
		on: { submit: () => panel.dialog.close() }
	})
}

// same as page views: release the locks when leaving, the changes are kept
const unlock = (filter = () => true) => {
	touched.forEach((page, key) => {
		if (filter(page)) {
			panel.content.unlockBeaconRequest(page)
			touched.delete(key)
		}
	})
}

// same as page views when switching languages: once the edits made in the previous language
// are written, their locks get released
watch(currentLanguage, async (language) => {
	await settled()
	unlock((page) => page.language !== language)
})

const onBeforeUnload = (event) => {
	if (request || queue.size > 0) {
		event.preventDefault()
		event.returnValue = ""
	}

	unlock()
}

// Kirby doesn't push lock changes, so the visible rows are checked regularly
// for pages that others started (or stopped) editing
const refreshLocks = () => {
	if (window.document.visibilityState === "visible" && !request && queue.size === 0) {
		refreshRows().catch(() => {})
	}
}

let interval = null

onMounted(() => {
	panel.events.on("view.save", onSave)
	panel.events.on("beforeunload", onBeforeUnload)
	window.addEventListener("focus", refreshLocks)
	interval = window.setInterval(refreshLocks, 30000)
})

onBeforeUnmount(() => {
	panel.events.off("view.save", onSave)
	panel.events.off("beforeunload", onBeforeUnload)
	window.removeEventListener("focus", refreshLocks)
	window.clearInterval(interval)
	// write the last changes first, otherwise they would lock the pages again
	settled().then(unlock)
})

// column visibility & widths are remembered per browser
const STORAGE_KEY = "kirby-seo.overview.table"
const settings = ref({
	columns: {},
	widths: {},
	...JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? "{}")
})

watch(settings, (value) => window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value)), {
	deep: true
})

const columnsDropdown = ref(null)

// columns with a `toggle` label can be shown/hidden
const isVisible = (key) => {
	const column = props.columns[key]
	return !column.toggle || (settings.value.columns[key] ?? column.hidden !== true)
}

const visibleColumns = computed(() =>
	Object.fromEntries(
		Object.entries(props.columns)
			.filter(([key]) => isVisible(key))
			// cells receive the column config, issues filter the table
			.map(([key, column]) => [
				key,
				column.type === "seo-checks" ? { ...column, filterGroup, filterIssue: setFilter } : column
			])
	)
)

const columnOptions = computed(() =>
	Object.entries(props.columns)
		.filter(([, column]) => column.toggle)
		.map(([value, column]) => ({ value, text: column.toggle }))
)

const onColumns = (values) => {
	settings.value.columns = Object.fromEntries(
		columnOptions.value.map(({ value }) => [value, values.includes(value)])
	)
	// the remaining columns should fill the table again
	settings.value.widths = {}
}

const onSearch = helpers.debounce((value) => reload({ search: value, page: "1" }), 300)
watch(searchterm, onSearch)

const onSort = ({ sort, dir }) => reload({ sort: sort ?? "", dir, page: "1" })
const onPaginate = ({ page }) => reload({ page: String(page) })

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

// pages sharing the same title/description as a page
function filterGroup(group) {
	reload({ group, issue: "", page: "1" })
}
</script>

<template>
	<k-seo-view
		:buttons="buttons"
		:stats="serverStats"
		:tab="tab"
		:tabs="tabs"
		class="k-seo-overview-view"
		@filter="setFilter"
	>
		<template #buttons>
			<k-seo-changes-controls
				:changes="targets"
				:is-processing="isProcessing || isSaving"
				@discard="onDiscard"
				@submit="onSave"
			/>
		</template>

		<template #toolbar>
			<k-button-group v-if="selected.length" layout="collapsed">
				<k-button
					:text="$t('seo.overview.selection.count', { count: selected.length })"
					size="xs"
					variant="filled"
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
				:value="columnOptions.filter(({ value }) => isVisible(value)).map(({ value }) => value)"
				:search="false"
				@input="onColumns"
			/>
		</template>

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
