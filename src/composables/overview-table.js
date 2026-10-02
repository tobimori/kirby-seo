import { computed, onBeforeUnmount, onMounted, ref, watch, usePanel, useHelpers } from "kirbyuse"

import { fetchSseStream } from "../helpers/ai-stream.js"

const AUTOSAVE_DELAY = 500
const AI_CONCURRENCY = 2

const isLockError = (error) => error?.key?.startsWith("error.content.lock")

/**
 * Search, sorting & pagination of the tables in the SEO area, via the query of the view
 *
 * @param {object} props Props of the view: `search`
 */
export function useTableQuery(props) {
	const panel = usePanel()
	const helpers = useHelpers()

	// the query is merged with the current one, so empty strings are used to reset values
	const reload = (query) => panel.view.reload({ query })

	/**
	 * Search: like in Kirby's pages sections, the search field is shown on demand
	 */
	const searchterm = ref(props.search ?? "")
	const isSearching = ref(Boolean(props.search))

	// hiding the search field resets the search
	const toggleSearch = () => {
		isSearching.value = !isSearching.value

		if (!isSearching.value) {
			searchterm.value = ""
		}
	}

	const onSearch = helpers.debounce((value) => reload({ search: value, page: "1" }), 300)
	watch(searchterm, onSearch)

	const onSort = ({ sort, dir }) => reload({ sort: sort ?? "", dir, page: "1" })
	const onPaginate = ({ page }) => reload({ page: String(page) })

	return { reload, searchterm, isSearching, toggleSearch, onSort, onPaginate }
}

/**
 * Columns of the tables in the SEO area that can be shown/hidden & resized
 *
 * @param {object} props Props of the view: `columns`
 * @param {string} storageKey Key for the column settings in the local storage
 */
export function useColumnSettings(props, storageKey) {
	/**
	 * Column visibility & widths are remembered per browser
	 */
	const settings = ref({
		columns: {},
		widths: {},
		...JSON.parse(window.localStorage.getItem(storageKey) ?? "{}")
	})

	watch(settings, (value) => window.localStorage.setItem(storageKey, JSON.stringify(value)), {
		deep: true
	})

	// columns with a `toggle` label can be shown/hidden
	const isVisible = (key) => {
		const column = props.columns[key]
		return !column.toggle || (settings.value.columns[key] ?? column.hidden !== true)
	}

	const columnOptions = computed(() =>
		Object.entries(props.columns)
			.filter(([, column]) => column.toggle)
			.map(([value, column]) => ({ value, text: column.toggle }))
	)

	const visibleColumnKeys = computed(() =>
		columnOptions.value.filter(({ value }) => isVisible(value)).map(({ value }) => value)
	)

	const onColumns = (values) => {
		settings.value.columns = Object.fromEntries(
			columnOptions.value.map(({ value }) => [value, values.includes(value)])
		)
		// the remaining columns should fill the table again
		settings.value.widths = {}
	}

	return { settings, isVisible, columnOptions, visibleColumnKeys, onColumns }
}

/**
 * Shared logic of the tables in the SEO area (pages, images): inline editing with autosave
 * to the changes versions, locks, publishing & discarding, selection, search, sorting,
 * column settings and AI generation.
 *
 * Rows are updated in place after saving or when checking locks, instead of reloading
 * the view: a reload would resort the table & make rows jump while editing.
 * The order only changes when the user sorts, searches or paginates.
 *
 * @param {object} props Props of the view: `rows`, `columns`, `changes`, `summary`, `stats`, `ids`, `search`
 * @param {object} options
 * @param {string} options.endpoint API endpoint of the table, e.g. `seo/overview/pages`
 * @param {string} options.storageKey Key for the column settings in the local storage
 * @param {(cell: object, pending: { value: string, source?: string }) => object} options.applyPending
 *   Shows a value that hasn't been confirmed by the server yet in its cell
 * @param {(row: object, column: string) => { url: string, body: object }} [options.aiRequest]
 *   Endpoint streaming the generated value of a cell, defaults to the AI route of the field with the column's name
 */
export function useOverviewTable(props, { endpoint, storageKey, applyPending, aiRequest }) {
	const panel = usePanel()

	const { reload, searchterm, isSearching, toggleSearch, onSort, onPaginate } = useTableQuery(props)

	/**
	 * Selection, across table pages
	 */
	const selected = ref([])

	const isAllSelected = computed(() => {
		const ids = new Set(selected.value)
		return props.ids.every((id) => ids.has(id))
	})
	const selectAll = () => (selected.value = [...new Set([...selected.value, ...props.ids])])

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

		// only the stats that changed with the edit (e.g. the alt texts aren't affected by editing pages)
		if (stats) {
			serverStats.value = { ...serverStats.value, ...stats }
		}
	}

	// shows the first error of a response
	const notifyErrors = (response) => {
		const errors = Object.values(response.errors ?? {})
		const lockError = errors.find(isLockError)

		// someone else started editing in the meantime
		if (lockError) {
			panel.content.lockDialog(lockError.details)
		} else if (errors.length) {
			panel.notification.error(errors[0].message)
		}

		return errors.length === 0
	}

	/**
	 * Autosave, same as editing a model: values are written to the changes version
	 * (which also locks the model for others) while typing. To keep the number of requests low,
	 * changes are collected & sent in a single request for all rows, at most one request is
	 * running at a time and typing only triggers a save after a short pause.
	 */

	// values that still need to be sent
	const queue = new Map()
	// values that have been typed but are not confirmed by the server yet,
	// shown in the table instead of the server values: `{ value, source }`
	const pending = ref({})
	const isSaving = ref(false)
	let timer = null
	let request = null

	// models edited in this view (by their Panel link, e.g. `/pages/blog+post`) & language,
	// their locks get released when leaving the view or switching languages
	const touched = new Map()
	const touchedKey = (link, language = currentLanguage()) => `${language}\u0000${link}`

	/**
	 * @param {Array<{ row: object, column: string, value: string, source?: string }>} changes
	 *   `source` is saved along with the value, e.g. `ai` for generated alt texts
	 */
	const onInput = (changes) => {
		for (const { row, column, value, source } of changes) {
			const key = cellKey(row.id, column)
			const saved = serverRow(row)[column]?.value ?? ""

			// nothing to do if the value is what the server has & nothing else is on its way
			if (value === saved && !source && !(key in pending.value) && !queue.has(key)) {
				continue
			}

			queue.set(key, { id: row.id, column, value, source, language: currentLanguage() })
			pending.value = { ...pending.value, [key]: { value, source } }
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
				`${endpoint}/save`,
				{
					changes: batch.flatMap(([, { id, column, value, source }]) => [
						{ id, column, value },
						...(source ? [{ id, column: "source", value: source }] : [])
					])
				},
				{ silent: true, headers: { "x-language": language } }
			)
			const response = await request
			applyResponse(response, language)
			notifyErrors(response)
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

	/**
	 * Saves values right away, outside of the autosave (e.g. bulk actions on the selection),
	 * once all pending edits are written
	 *
	 * @param {Array<{ id: string, column: string, value: any }>} changes
	 */
	const saveNow = async (changes) => {
		await settled()
		isSaving.value = true

		try {
			const response = await panel.api.post(`${endpoint}/save`, { changes })
			applyResponse(response)

			for (const row of Object.values(response.rows ?? {})) {
				touched.set(touchedKey(row.link), { api: row.link, language: currentLanguage() })
			}

			return notifyErrors(response)
		} catch (error) {
			panel.notification.error(error)
			return false
		} finally {
			isSaving.value = false
		}
	}

	// the row with the values that are still on their way
	const withPending = (row) => {
		const item = { ...row, ...serverRow(row) }

		for (const column of Object.keys(props.columns)) {
			const value = pending.value[cellKey(row.id, column)]

			if (value === undefined) {
				continue
			}

			item[column] = applyPending(item[column], value)
			item.changes = true
			// editing creates the translation
			item.title = { ...item.title, changes: true, translated: true }
		}

		return item
	}

	// all rows with unsaved changes, including the ones that are still being saved
	const changes = computed(() => {
		const list = [...serverChanges.value]

		for (const key of Object.keys(pending.value)) {
			const { language, id } = parseKey(key)

			if (language !== currentLanguage()) {
				continue
			}

			const row = props.rows.find((row) => row.id === id)

			if (row && !list.some((item) => item.id === row.id)) {
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
				`${endpoint}/rows`,
				{ ids: props.rows.map((row) => row.id) },
				{ silent: true }
			),
			language
		)
	}

	// current state of rows that might not be on the current table page, e.g. of the selection
	const fetchRows = async (ids) => {
		const { rows = {} } = await panel.api.post(`${endpoint}/rows`, { ids }, { silent: true })
		return Object.values(rows)
	}

	/**
	 * Publishing & discarding applies to the selected rows if there is a selection,
	 * otherwise to all rows with unsaved changes
	 */
	const scope = computed(() => (selected.value.length ? "selected" : "all"))

	const targets = computed(() =>
		scope.value === "selected"
			? changes.value.filter((item) => selected.value.includes(item.id))
			: changes.value
	)

	const isProcessing = ref(false)

	const runOnChanges = async (action, items) => {
		isProcessing.value = true

		try {
			const response = await panel.api.post(`${endpoint}/${action}`, {
				ids: items.map((item) => item.id)
			})
			applyResponse(response)

			if (notifyErrors(response)) {
				panel.notification.success(
					panel.t(`seo.overview.changes.${action === "publish" ? "published" : "discarded"}`, {
						count: items.length
					})
				)
			}
		} catch (error) {
			panel.notification.error(error)
		} finally {
			isProcessing.value = false
		}
	}

	// writes pending edits first, as they might add rows to the list of changes
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

		if (isProcessing.value || isGenerating.value || targets.value.length === 0) {
			return
		}

		const items = await prepare()

		if (items.length) {
			runOnChanges("publish", items)
		}
	}

	const onDiscard = async () => {
		if (isProcessing.value || targets.value.length === 0) {
			return
		}

		const items = await prepare()

		if (items.length === 0) {
			return
		}

		if (
			await confirm({
				component: "k-remove-dialog",
				text: panel.t(`seo.overview.changes.discard.confirm.${scope.value}`, {
					count: items.length
				}),
				submitButton: { theme: "notice", icon: "undo", text: panel.t("form.discard") }
			})
		) {
			runOnChanges("discard", items)
		}
	}

	const onLock = (row) => {
		panel.dialog.open({
			component: "k-lock-alert-dialog",
			props: { lock: row.lock },
			on: { submit: () => panel.dialog.close() }
		})
	}

	// same as model views: release the locks when leaving, the changes are kept
	const unlock = (filter = () => true) => {
		touched.forEach((model, key) => {
			if (filter(model)) {
				panel.content.unlockBeaconRequest(model)
				touched.delete(key)
			}
		})
	}

	// same as model views when switching languages: once the edits made in the previous language
	// are written, their locks get released
	watch(currentLanguage, async (language) => {
		await settled()
		unlock((model) => model.language !== language)
	})

	/**
	 * AI generation of the values of a column, for multiple rows at once
	 */
	const generation = ref(null)
	const isGenerating = computed(() => generation.value !== null)

	const fieldAiRequest = (row, column) => ({
		url: `${panel.urls.api}${row.link}/fields/${column.toLowerCase()}/ai/stream`,
		body: {}
	})

	const generateCell = async (row, column, signal, source) => {
		const original = serverRow(row)[column]?.value ?? ""
		let text = ""

		try {
			await fetchSseStream({
				...(aiRequest ?? fieldAiRequest)(row, column),
				signal,
				onEvent: (data) => {
					if (data.type === "text-delta") {
						text += data.text ?? ""
						onInput([{ row, column, value: text, source }])
					}
				}
			})
		} catch (error) {
			onInput([{ row, column, value: original }])
			throw error
		}
	}

	/**
	 * @param {Array<object>} rows
	 * @param {string} column
	 * @param {object} [options]
	 * @param {string} [options.source] Saved along with the generated values
	 */
	const runGeneration = async (rows, column, { source } = {}) => {
		if (generation.value) {
			return
		}

		const controller = new window.AbortController()
		const remaining = [...rows]
		const errors = []
		let generated = 0

		generation.value = { done: 0, total: rows.length, controller }

		const worker = async () => {
			while (remaining.length && !controller.signal.aborted) {
				const row = remaining.shift()

				try {
					await generateCell(row, column, controller.signal, source)
					generated++
				} catch (error) {
					if (error?.name !== "AbortError") {
						errors.push(error)
					}
				}

				generation.value.done++
			}
		}

		await Promise.all(Array.from({ length: Math.min(AI_CONCURRENCY, rows.length) }, worker))
		onCommit()
		generation.value = null

		if (errors.length) {
			panel.notification.error(errors[0]?.message ?? panel.t("seo.ai.error.request"))
		} else if (rows.length > 1) {
			panel.notification.success(panel.t("seo.overview.ai.done", { count: generated }))
		}
	}

	const cancelGeneration = () => generation.value?.controller.abort()

	/**
	 * Asks which of the given rows should be generated, then generates them
	 *
	 * @param {object} options
	 * @param {Array<object>} options.rows Rows that can be generated
	 * @param {string} options.column
	 * @param {string} options.text Confirmation text
	 * @param {string} options.onlyEmpty Label of the toggle to skip rows that have a value
	 * @param {(row: object) => boolean} [options.isEmpty]
	 * @param {string} [options.source] Saved along with the generated values
	 */
	const confirmGeneration = ({
		rows,
		column,
		text,
		onlyEmpty,
		isEmpty = (row) => !row[column].value,
		source
	}) => {
		panel.dialog.open({
			component: "k-form-dialog",
			props: {
				fields: {
					info: { type: "info", text },
					onlyEmpty: { type: "toggle", label: onlyEmpty }
				},
				value: { onlyEmpty: true },
				submitButton: { icon: "seo-ai", text: panel.t("seo.ai.dialog.custom.submit") }
			},
			on: {
				submit: ({ onlyEmpty }) => {
					panel.dialog.close()

					const targets = rows.filter((row) => !onlyEmpty || isEmpty(row))

					if (targets.length === 0) {
						return panel.notification.info(panel.t("seo.overview.ai.none"))
					}

					runGeneration(targets, column, { source })
				}
			}
		})
	}

	/**
	 * Lifecycle
	 */
	const onBeforeUnload = (event) => {
		if (request || queue.size > 0 || isGenerating.value) {
			event.preventDefault()
			event.returnValue = ""
		}

		unlock()
	}

	// Kirby doesn't push lock changes, so the visible rows are checked regularly
	// for models that others started (or stopped) editing
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
		cancelGeneration()
		// write the last changes first, otherwise they would lock the models again
		settled().then(unlock)
	})

	const { settings, isVisible, columnOptions, visibleColumnKeys, onColumns } = useColumnSettings(
		props,
		storageKey
	)

	return {
		reload,
		// search, sorting & pagination
		searchterm,
		isSearching,
		toggleSearch,
		onSort,
		onPaginate,
		// selection
		selected,
		isAllSelected,
		selectAll,
		// server state
		serverRow,
		serverSummary,
		serverStats,
		applyResponse,
		fetchRows,
		// editing
		onInput,
		onCommit,
		saveNow,
		settled,
		withPending,
		isSaving,
		// changes
		changes,
		targets,
		isProcessing,
		onSave,
		onDiscard,
		onLock,
		// AI
		generation,
		isGenerating,
		runGeneration,
		confirmGeneration,
		cancelGeneration,
		// columns
		settings,
		isVisible,
		columnOptions,
		visibleColumnKeys,
		onColumns
	}
}
