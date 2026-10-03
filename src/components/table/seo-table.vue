<script setup>
import { computed, nextTick, onMounted, ref, usePanel, watch } from "kirbyuse"

import { providePopoverGroup } from "../../composables/popover.js"

const props = defineProps({
	columns: {
		type: Object,
		default: () => ({})
	},
	rows: {
		type: Array,
		default: () => []
	},
	pagination: {
		type: [Object, Boolean],
		default: false
	},
	empty: String,
	sort: String,
	dir: {
		type: String,
		default: "asc"
	},
	selectable: Boolean,
	/** Ids of the selected rows, use with `.sync` */
	selected: {
		type: Array,
		default: () => []
	},
	/** Whether columns can be resized, unless they set `resizable: false` */
	resizable: Boolean,
	/** Custom column widths as `{ [column]: "25%" }`, use with `.sync` */
	widths: {
		type: Object,
		default: () => ({})
	}
})

const panel = usePanel()

providePopoverGroup()

const emit = defineEmits([
	"commit",
	"input",
	"lock",
	"paginate",
	"sort",
	"update:selected",
	"update:widths"
])

/**
 * Inline editing: columns with `editable: true` can be edited, unless the row or
 * the cell value sets `editable: false`. The table only coordinates which cells are
 * being edited, saving is up to the parent:
 * - `input` with a list of `{ row, column, value }` on every change (also for ranges)
 * - `commit` when editing a cell ends (Enter, Tab, Escape, click outside)
 */
const editing = ref(null)

// values of the edited cells before editing, to restore them on Escape
// or when typing back to the original value
const originals = ref({})

const editableKeys = computed(() =>
	Object.keys(props.columns).filter((key) => props.columns[key].editable)
)

const isEditable = (row, key) => row.editable !== false && row[key]?.editable !== false
const rowsByKeys = (keys) => props.rows.filter((row) => keys.includes(row.id))

const remember = (rows, key) => {
	for (const row of rows) {
		if (!(row.id in originals.value)) {
			originals.value[row.id] = row[key]?.value ?? ""
		}
	}
}

const setEditing = (row, key) => {
	editing.value = { row: row.id, column: key }
	// the last edited cell is the anchor for shift-click range selections
	anchor.value = editing.value
	originals.value = {}
	remember([row], key)
}

const startEdit = (row, key) => {
	if (isEditable(row, key)) {
		clearRange()
		setEditing(row, key)
	}
}

const stopEdit = () => {
	editing.value = null
	originals.value = {}
	clearRange()
}

/**
 * Shift-click extends editing to a range in one column; the editor stays in the anchor cell
 */
const anchor = ref(null)
const range = ref(null)
// the typed value, `null` until the user changed something, so selecting a range
// doesn't copy the anchor's value into all cells
const rangeDraft = ref(null)

const clearRange = () => {
	range.value = null
	rangeDraft.value = null
}

const inRange = (row, key) => range.value?.column === key && range.value.rows.includes(row.id)

// selects the given rows as range; if something was typed already,
// rows that join the range get the value, rows that leave it are restored
const setRange = (keys, key) => {
	const previous = range.value?.rows ?? [editing.value.row]
	remember(rowsByKeys(keys), key)
	range.value = { column: key, rows: keys }

	if (rangeDraft.value !== null) {
		emit("input", [
			...rowsByKeys(keys.filter((k) => !previous.includes(k))).map((row) => ({
				row,
				column: key,
				value: rangeDraft.value
			})),
			...rowsByKeys(previous.filter((k) => !keys.includes(k))).map((row) => ({
				row,
				column: key,
				value: originals.value[row.id]
			}))
		])
	}
}

const extendRange = (row, key) => {
	const from = [editing.value, anchor.value].find((cell) => cell?.column === key)
	const start = props.rows.findIndex((item) => item.id === from?.row)
	const end = props.rows.findIndex((item) => item.id === row.id)

	if (start === -1 || end === -1) {
		return startEdit(row, key)
	}

	// keep editing the anchor cell
	if (editing.value?.row !== from.row || editing.value?.column !== key) {
		setEditing(props.rows[start], key)
	}

	setRange(
		props.rows
			.slice(Math.min(start, end), Math.max(start, end) + 1)
			.filter((item) => isEditable(item, key))
			.map((row) => row.id),
		key
	)
}

const selectAll = (key) => {
	if (editing.value?.column === key) {
		setRange(
			props.rows.filter((item) => isEditable(item, key)).map((row) => row.id),
			key
		)
	}
}

const editedRows = (key) =>
	range.value?.column === key ? rowsByKeys(range.value.rows) : rowsByKeys([editing.value?.row])

const setDraft = (row, key, value) => {
	if (range.value?.column !== key) {
		return emit("input", [{ row, column: key, value }])
	}

	// typing back to the original value restores all cells of the range
	const dirty = value !== originals.value[editing.value.row]
	rangeDraft.value = dirty ? value : null

	emit(
		"input",
		editedRows(key).map((item) => ({
			row: item,
			column: key,
			value: dirty ? value : originals.value[item.id]
		}))
	)
}

const rangeText = computed(() => {
	if (rangeDraft.value === null || props.columns[range.value?.column]?.plain) {
		return rangeDraft.value
	}

	// writer values are HTML
	return new window.DOMParser().parseFromString(rangeDraft.value, "text/html").body.textContent
})

// moves to the next editable cell: `next`/`prev` within the row (wrapping to the next/previous row), `down`/`up` within the column
const moveEdit = (row, key, direction) => {
	const columns = editableKeys.value
	let rowIndex = props.rows.findIndex((item) => item.id === row.id)
	let columnIndex = columns.indexOf(key)

	clearRange()

	for (let i = 0; i < props.rows.length * columns.length; i++) {
		if (direction === "down") rowIndex++
		if (direction === "up") rowIndex--
		if (direction === "next" && ++columnIndex >= columns.length) {
			columnIndex = 0
			rowIndex++
		}
		if (direction === "prev" && --columnIndex < 0) {
			columnIndex = columns.length - 1
			rowIndex--
		}

		const target = props.rows[rowIndex]

		if (!target) {
			break
		}

		if (isEditable(target, columns[columnIndex])) {
			setEditing(target, columns[columnIndex])
			return
		}
	}

	stopEdit()
}

const commitEdit = (row, key, value, direction = null) => {
	// the value has been sent with the last `input` already, just make sure
	setDraft(row, key, value)
	emit("commit")

	// a range ends editing, a single cell can move on
	if (range.value?.column === key || !direction) {
		return stopEdit()
	}

	moveEdit(row, key, direction)
}

const cancelEdit = (key) => {
	emit(
		"input",
		editedRows(key).map((row) => ({ row, column: key, value: originals.value[row.id] }))
	)
	emit("commit")
	stopEdit()
}

const MIN_WIDTH = 64
const KEYBOARD_STEP = 16

const isResizable = (key) => key !== "_index" && props.columns[key]?.resizable !== false

// the next resizable column gives/takes the width, so the table never grows
// wider than its container (which would require horizontal scrolling)
const neighbourOf = (key) => {
	const keys = Object.keys(props.columns)
	return keys.slice(keys.indexOf(key) + 1).find(isResizable) ?? null
}

const canResize = (key) => props.resizable && isResizable(key) && neighbourOf(key) !== null

// measures both columns, so the resize can be applied relative to the start
const measure = (handle, key) => {
	const th = handle.closest("th")
	const neighbour = neighbourOf(key)
	const next = th.parentElement.querySelector(`[data-column-id="${neighbour}"]`)

	return {
		key,
		neighbour,
		current: th.getBoundingClientRect().width,
		next: next.getBoundingClientRect().width,
		total: th.closest("table").getBoundingClientRect().width
	}
}

// widths are stored as percentages, so they adapt to the viewport
const applyResize = (start, delta) => {
	const d = Math.max(MIN_WIDTH - start.current, Math.min(delta, start.next - MIN_WIDTH))
	const percent = (px) => `${((px / start.total) * 100).toFixed(2)}%`

	emit("update:widths", {
		...props.widths,
		[start.key]: percent(start.current + d),
		[start.neighbour]: percent(start.next - d)
	})
}

const direction = () => (panel.direction === "rtl" ? -1 : 1)
const resizing = ref(null)

const onResizeStart = (event, key) => {
	const handle = event.currentTarget
	const start = measure(handle, key)
	let frame = null

	const move = (e) => {
		window.cancelAnimationFrame(frame)
		frame = window.requestAnimationFrame(() =>
			applyResize(start, (e.clientX - event.clientX) * direction())
		)
	}

	const stop = () => {
		handle.removeEventListener("pointermove", move)
		handle.removeEventListener("pointerup", stop)
		handle.removeEventListener("pointercancel", stop)
		resizing.value = null
	}

	handle.setPointerCapture(event.pointerId)
	handle.addEventListener("pointermove", move)
	handle.addEventListener("pointerup", stop)
	handle.addEventListener("pointercancel", stop)
	resizing.value = key
}

const onResizeKey = (event, key, step) => {
	applyResize(measure(event.currentTarget, key), step * direction())
}

// widths in percent of the table, screen readers read them from the resize handles.
// Measured, as columns without a stored width share the space & change with the viewport
const root = ref(null)
const sizes = ref({})

const measureSizes = () => {
	const table = root.value?.querySelector("table")

	if (!table) {
		return
	}

	const total = table.getBoundingClientRect().width
	const headers = [...table.querySelectorAll("thead th[data-column-id]")]

	sizes.value = Object.fromEntries(
		headers.map((th) => [
			th.dataset.columnId,
			Math.round((th.getBoundingClientRect().width / total) * 100)
		])
	)
}

onMounted(measureSizes)
watch(
	() => [props.widths, props.columns],
	() => nextTick(measureSizes)
)

// row index of the last toggled checkbox, used for shift-click range selection
const lastIndex = ref(null)

const isSelectable = (row) => row.selectable !== false
const isSelected = (row) => props.selected.includes(row.id)

const selectableKeys = computed(() => props.rows.filter(isSelectable).map((row) => row.id))
const allSelected = computed(
	() =>
		selectableKeys.value.length > 0 &&
		selectableKeys.value.every((key) => props.selected.includes(key))
)
const someSelected = computed(
	() => !allSelected.value && selectableKeys.value.some((key) => props.selected.includes(key))
)

const update = (keys, select) => {
	const next = new Set(props.selected)
	keys.forEach((key) => (select ? next.add(key) : next.delete(key)))
	emit("update:selected", [...next])
}

const toggle = (row, event) => {
	const index = props.rows.findIndex((item) => item.id === row.id)
	const select = !isSelected(row)
	let keys = [row.id]

	if (event?.shiftKey && lastIndex.value !== null) {
		const start = Math.min(lastIndex.value, index)
		const end = Math.max(lastIndex.value, index)
		keys = props.rows
			.slice(start, end + 1)
			.filter(isSelectable)
			.map((row) => row.id)
	}

	lastIndex.value = index
	update(keys, select)
}

const toggleAll = () => {
	lastIndex.value = null
	update(selectableKeys.value, !allSelected.value)
}

const tableColumns = computed(() => {
	const columns = Object.fromEntries(
		Object.entries(props.columns).map(([key, column]) => {
			if (isResizable(key) && props.widths[key]) {
				column = { ...column, width: props.widths[key] }
			}

			if (column.editable) {
				column = {
					...column,
					isEditable: (row) => isEditable(row, key),
					isEditing: (row) => editing.value?.row === row.id && editing.value?.column === key,
					startEdit: (row) => startEdit(row, key),
					cancelEdit: () => cancelEdit(key),
					commitEdit: (row, value, direction) => commitEdit(row, key, value, direction),
					inRange: (row) => inRange(row, key),
					// position within the range, so only the outer edges of the range get a border
					rangeEdge: (row) =>
						inRange(row, key)
							? {
									start: range.value.rows[0] === row.id,
									end: range.value.rows.at(-1) === row.id
								}
							: null,
					extendRange: (row) => extendRange(row, key),
					selectAll: () => selectAll(key),
					setDraft: (row, value) => setDraft(row, key, value),
					rangeText: () => rangeText.value
				}
			}

			return [key, column]
		})
	)

	return {
		_index: {
			label: "#",
			mobile: true,
			type: "seo-index",
			// wide enough for the highest number on the page, like Kirby's index column
			width: `max(var(--table-row-height), calc(${String(offset.value + props.rows.length).length}ch + 1.5rem))`,
			selectable: props.selectable,
			isSelected,
			toggle,
			openLock: (row) => emit("lock", row)
		},
		...columns
	}
})

const offset = computed(() =>
	props.pagination ? (props.pagination.page - 1) * props.pagination.limit : 0
)

const tableRows = computed(() =>
	props.rows.map((row, index) => ({ ...row, _index: offset.value + index + 1 }))
)

const hasPagination = computed(
	() => props.pagination !== false && props.pagination.total > props.pagination.limit
)

// the icon only shows the direction, screen readers get it as text
const sortState = computed(() => `, ${panel.t(`seo.table.sorted.${props.dir}`)}`)

const sortIcon = (columnIndex) => {
	// unsorted columns hint at the first click (ascending) on hover
	return props.sort === columnIndex && props.dir === "desc" ? "angle-down" : "angle-up"
}

// cycles through ascending > descending > unsorted
const onSort = (columnIndex) => {
	if (props.sort !== columnIndex) {
		return emit("sort", { sort: columnIndex, dir: "asc" })
	}

	if (props.dir === "asc") {
		return emit("sort", { sort: columnIndex, dir: "desc" })
	}

	emit("sort", { sort: null, dir: "asc" })
}
</script>

<template>
	<div ref="root" :data-selecting="selected.length > 0" class="k-seo-table">
		<k-table :columns="tableColumns" :rows="tableRows" :empty="empty" :index="false">
			<template #header="{ column, columnIndex, label }">
				<label
					v-if="columnIndex === '_index' && selectable"
					class="k-seo-table-index k-seo-table-select"
				>
					<input
						:checked="allSelected"
						:indeterminate.prop="someSelected"
						:disabled="selectableKeys.length === 0"
						:aria-label="$t('seo.table.selectAll')"
						type="checkbox"
						@change="toggleAll"
					/>
					<span class="k-seo-table-index-number" aria-hidden="true">#</span>
				</label>
				<span v-else-if="columnIndex === '_index'" class="k-seo-table-index">
					<span class="k-seo-table-index-number">#</span>
				</span>
				<button
					v-else-if="column.sortable"
					:data-sorted="String(sort === columnIndex)"
					:aria-label="sort === columnIndex ? `${label}${sortState}` : null"
					type="button"
					class="k-seo-table-sort"
					@click="onSort(columnIndex)"
				>
					<span>{{ label }}</span>
					<k-icon :type="sortIcon(columnIndex)" />
				</button>
				<span v-else-if="column.hideLabel" class="sr-only">{{ label }}</span>
				<template v-else>{{ label }}</template>

				<span
					v-if="canResize(columnIndex)"
					:aria-label="$t('seo.table.resize', { column: label })"
					:data-active="resizing === columnIndex"
					:title="$t('seo.table.resizeReset')"
					:aria-valuenow="sizes[columnIndex] ?? 0"
					role="separator"
					aria-orientation="vertical"
					aria-valuemin="0"
					aria-valuemax="100"
					tabindex="0"
					class="k-seo-table-resize"
					@focus="measureSizes"
					@click.stop
					@dblclick.stop="emit('update:widths', {})"
					@pointerdown.stop.prevent="onResizeStart($event, columnIndex)"
					@keydown.left.prevent="onResizeKey($event, columnIndex, -KEYBOARD_STEP)"
					@keydown.right.prevent="onResizeKey($event, columnIndex, KEYBOARD_STEP)"
				/>
			</template>
		</k-table>

		<footer v-if="hasPagination" class="k-collection-footer">
			<k-pagination
				v-bind="pagination"
				:details="true"
				class="k-seo-table-pagination"
				@paginate="emit('paginate', $event)"
			/>
		</footer>
	</div>
</template>

<style>
.k-seo-table {
	--range-border: color-mix(in srgb, var(--color-focus) 60%, transparent);

	.k-seo-table-pagination {
		margin-inline-start: auto;
	}

	th:has(> .k-seo-table-sort),
	th:has(> .k-seo-table-index),
	td:has(> .k-seo-table-index) {
		padding: 0;
	}

	td.k-table-cell:empty::after {
		content: "–";
		display: block;
		padding-inline: var(--table-cell-padding);
		color: var(--color-text-dimmed);
	}

	.k-seo-table-sort {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: var(--spacing-2);
		width: 100%;
		height: var(--table-row-height);
		padding-inline: var(--table-cell-padding);
		font: inherit;
		color: inherit;
		text-align: start;

		> span {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.k-icon {
			--icon-size: 14px;
			flex-shrink: 0;
		}

		&:hover {
			color: var(--color-text);
			background: light-dark(var(--color-gray-200), var(--color-gray-700));
		}

		&:focus-visible {
			outline: var(--outline);
			outline-offset: -2px;
		}

		&[data-sorted="true"] {
			color: var(--color-text);
		}

		&[data-sorted="false"] .k-icon {
			opacity: 0;
		}

		&[data-sorted="false"]:is(:hover, :focus-visible) .k-icon {
			opacity: 0.5;
		}
	}

	.k-seo-table-resize {
		position: absolute;
		z-index: 1;
		inset-block: 0;
		inset-inline-end: 0;
		width: 0.5rem;
		cursor: col-resize;
		touch-action: none;

		&::after {
			content: "";
			position: absolute;
			inset-block: 0;
			inset-inline-end: 0;
			width: 2px;
		}

		&:is(:hover, :focus-visible, [data-active="true"])::after {
			background: var(--color-focus);
		}

		&:focus-visible {
			outline: none;
		}
	}

	/* k-table only rounds the bottom corners via its built-in pagination bar */
	tbody tr:last-child td:first-child {
		border-end-start-radius: var(--rounded);
	}

	tbody tr:last-child td:last-child {
		border-end-end-radius: var(--rounded);
	}

	tbody tr:has(.k-seo-table-lock) {
		--lock-border: var(--color-red-500);
		--lock-top: inset 0 1px 0 var(--lock-border);
		--lock-bottom: inset 0 -1px 0 var(--lock-border);
		--lock-radius-top: var(--rounded);
		--lock-radius-bottom: var(--rounded);

		td {
			box-shadow: var(--lock-top), var(--lock-bottom);
		}

		/* inset shadows follow the border radius, which rounds the outline */
		td:first-child {
			border-start-start-radius: var(--lock-radius-top);
			border-end-start-radius: var(--lock-radius-bottom);
			box-shadow:
				inset 1px 0 0 var(--lock-border),
				var(--lock-top),
				var(--lock-bottom);
		}

		td:last-child {
			border-start-end-radius: var(--lock-radius-top);
			border-end-end-radius: var(--lock-radius-bottom);
			box-shadow:
				inset -1px 0 0 var(--lock-border),
				var(--lock-top),
				var(--lock-bottom);
		}
	}

	/* consecutive locked rows share a single line & are only rounded on the outside */
	tbody tr:has(.k-seo-table-lock) + tr:has(.k-seo-table-lock) {
		--lock-top: 0 0 0 transparent;
		--lock-radius-top: 0;
	}

	/* between locked rows, the row divider becomes the line: inset shadows are drawn
	   inside the border, so the side lines would have a gap at the divider otherwise */
	tbody tr:has(.k-seo-table-lock):has(+ tr .k-seo-table-lock) {
		--lock-bottom: 0 0 0 transparent;
		--lock-radius-bottom: 0;

		td {
			border-block-end-color: var(--lock-border);
		}
	}

	/* range selection: the row dividers between selected cells take the range color,
	   so the range has a single border (inset shadows would leave gaps at the dividers) */
	td:has(> [data-in-range="true"]:not([data-range-end="true"])) {
		border-block-end-color: var(--range-border);
	}

	tbody tr:has(.k-seo-table-select input:checked) {
		--table-color-back: light-dark(var(--color-blue-250), var(--color-blue-800));
		--table-color-hover: var(--table-color-back);
	}
}
</style>
