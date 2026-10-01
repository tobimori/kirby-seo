<script setup>
import { computed, nextTick, ref, watch } from "kirbyuse"

const props = defineProps({
	column: Object,
	row: Object,
	value: Object
})

const input = ref(null)
const aiButton = ref(null)
const display = ref(null)
const draft = ref("")
// prevents saving twice, e.g. on Enter and the following focusout
const committed = ref(false)

// anything but `fields` means the value is not set on the page itself
const inherited = computed(() => props.value.source && props.value.source !== "fields")
const editable = computed(() => props.column.isEditable?.(props.row) ?? false)
const editing = computed(() => props.column.isEditing?.(props.row) ?? false)
const inRange = computed(() => props.column.inRange?.(props.row) ?? false)
const rangeEdge = computed(() => props.column.rangeEdge?.(props.row) ?? null)
// value typed into the range editor, shown live in all selected cells
const rangeText = computed(() => (inRange.value ? props.column.rangeText() : null))

watch(
	editing,
	async (value, previous) => {
		if (!value) {
			if (previous) {
				await nextTick()

				if (window.document.activeElement === window.document.body) {
					display.value?.focus()
				}
			}

			return
		}

		draft.value = props.value.value ?? ""
		committed.value = false
		await nextTick()
		input.value?.focus()
	},
	{ immediate: true }
)

const generate = () => {
	commit()
	props.column.generate(props.row)
}

const commit = (direction = null) => {
	if (committed.value) {
		return
	}

	committed.value = true
	props.column.commitEdit(props.row, draft.value, direction)
}

// like in spreadsheets: the first mod+a selects the text of the cell,
// the second one (or the first one in an empty cell) selects the whole column
const onSelectAll = (event) => {
	const text = event.currentTarget.querySelector(".ProseMirror")?.textContent ?? ""
	const selected = window.getSelection()?.toString() ?? ""

	if (text === "" || selected === text) {
		event.preventDefault()
		props.column.selectAll()
	}
}

const onKeydown = (event) => {
	const onAiButton = aiButton.value?.$el?.contains(event.target) ?? false

	if (onAiButton && (event.key === "Enter" || event.key === " ")) {
		return
	}

	if (event.key === "Tab" && onAiButton && event.shiftKey) {
		event.preventDefault()
		return input.value?.focus()
	}

	if (event.key === "Tab" && !onAiButton && !event.shiftKey && aiButton.value) {
		event.preventDefault()
		return aiButton.value.$el.focus()
	}

	if (event.key === "a" && (event.metaKey || event.ctrlKey) && !onAiButton) {
		return onSelectAll(event)
	}

	if (event.key === "Escape") {
		event.preventDefault()
		committed.value = true
		props.column.cancelEdit()
	}

	if (event.key === "Enter") {
		event.preventDefault()
		event.stopPropagation()
		commit(event.shiftKey ? "up" : "down")
	}

	if (event.key === "Tab") {
		event.preventDefault()
		commit(event.shiftKey ? "prev" : "next")
	}
}

const onInput = (value) => {
	draft.value = value
	props.column.setDraft?.(props.row, value)
}

// shift-click selects a range; on mousedown, so the editor keeps the focus
const onMousedown = (event) => {
	if (event.shiftKey && editable.value) {
		event.preventDefault()
		props.column.extendRange(props.row)
	}
}

const onClick = (event) => {
	if (editable.value && !event.shiftKey) {
		props.column.startEdit(props.row)
	}
}

// clicking outside of the cell saves
const onFocusout = (event) => {
	if (!event.currentTarget.contains(event.relatedTarget)) {
		commit()
	}
}
</script>

<template>
	<div
		v-if="editing"
		:data-in-range="inRange"
		:data-range-start="rangeEdge?.start"
		:data-range-end="rangeEdge?.end"
		class="k-seo-meta-cell"
		data-editing="true"
		@keydown.capture="onKeydown"
		@focusout="onFocusout"
	>
		<k-seo-writer-input
			ref="input"
			:value="draft"
			:placeholder="value.placeholder"
			:inline="true"
			:marks="false"
			:nodes="false"
			class="k-seo-meta-cell-input"
			@input="onInput"
		/>
		<k-button
			v-if="value.ai && column.generate"
			ref="aiButton"
			:title="$t('seo.ai.action.generate')"
			icon="seo-ai"
			size="xs"
			variant="filled"
			class="k-seo-meta-cell-ai"
			@mousedown.native.prevent
			@click="generate"
		/>
	</div>

	<component
		:is="editable ? 'button' : 'div'"
		v-else
		ref="display"
		:data-inherited="inherited && rangeText === null"
		:data-in-range="inRange"
		:data-range-start="rangeEdge?.start"
		:data-range-end="rangeEdge?.end"
		:type="editable ? 'button' : null"
		class="k-seo-meta-cell"
		@mousedown="onMousedown"
		@click="onClick"
	>
		<p v-if="rangeText !== null" class="k-seo-meta-cell-text">
			<span v-if="rangeText">{{ rangeText }}</span>
			<span v-else class="k-seo-meta-cell-empty">—</span>
		</p>
		<p v-else class="k-seo-meta-cell-text">
			<k-tag
				v-if="inherited"
				:text="$t(`seo.overview.sourceLabel.${value.source}`)"
				:title="$t(`seo.overview.source.${value.source}`)"
				element="span"
				theme="light"
				class="k-seo-meta-cell-source"
			/>
			<span v-if="value.text">{{ value.text }}</span>
			<span v-else class="k-seo-meta-cell-empty">—</span>
		</p>
	</component>
</template>

<style>
.k-seo-meta-cell {
	display: flex;
	align-items: center;
	width: 100%;
	/* fill the whole cell, also when other cells make the row taller */
	height: 100%;
	min-height: var(--table-row-height);
	padding: var(--spacing-2) var(--table-cell-padding);
	text-align: start;
}

button.k-seo-meta-cell {
	cursor: text;

	&:hover:not([data-in-range="true"]) {
		background: light-dark(var(--color-gray-200), var(--color-gray-750, var(--color-gray-800)));
	}

	&:focus-visible {
		outline: var(--outline);
		outline-offset: -2px;
	}
}

.k-seo-meta-cell[data-in-range="true"] {
	--range-top: 0 0 0 transparent;
	--range-bottom: 0 0 0 transparent;
	background: color-mix(in srgb, var(--color-focus) 12%, transparent);
	/* sides on every cell, top & bottom only on the outer cells of the range,
	   the dividers in between are colored by the table */
	box-shadow:
		inset 1px 0 0 var(--range-border),
		inset -1px 0 0 var(--range-border),
		var(--range-top),
		var(--range-bottom);

	/* inset shadows follow the border radius, which rounds the outer corners */
	&[data-range-start="true"] {
		--range-top: inset 0 1px 0 var(--range-border);
		border-start-start-radius: var(--rounded);
		border-start-end-radius: var(--rounded);
	}

	&[data-range-end="true"] {
		--range-bottom: inset 0 -1px 0 var(--range-border);
		border-end-start-radius: var(--rounded);
		border-end-end-radius: var(--rounded);
	}
}

.k-seo-meta-cell[data-editing="true"] {
	background: var(--input-color-back);
	outline: var(--outline);
	outline-offset: -2px;
}

.k-seo-meta-cell-ai {
	flex-shrink: 0;
	margin-inline-start: var(--spacing-2);
}

.k-seo-meta-cell-input {
	/* same text position & line height as in the display state */
	--input-padding-multiline: 0;
	--text-line-height: var(--leading-normal);
	flex-grow: 1;
	min-width: 0;
	line-height: var(--leading-normal);

	.ProseMirror {
		outline: none;
	}

	/* same as in the seo-writer field */
	img.ProseMirror-separator {
		display: inline-block;
		width: 0;
		height: 0;
		margin: 0;
		padding: 0;
		border: 0;
		overflow: hidden;
	}

	br.ProseMirror-trailingBreak {
		display: none;
	}
}

.k-seo-meta-cell-text {
	flex-grow: 1;
	min-width: 0;
	display: -webkit-box;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: 2;
	overflow: hidden;
	line-height: var(--leading-normal);
	white-space: normal;
}

.k-seo-meta-cell[data-inherited="true"] .k-seo-meta-cell-text,
.k-seo-meta-cell-empty {
	color: var(--color-text-dimmed);
}

.k-seo-meta-cell-source.k-tag {
	--tag-height: 1.375rem;
	--tag-text-size: var(--text-xs);
	--tag-color-back: light-dark(var(--color-gray-200), var(--color-gray-700));
	--tag-color-text: var(--color-text);
	display: inline-flex;
	vertical-align: baseline;
	width: auto;
	margin-inline-end: var(--spacing-2);
	margin-block: -0.125rem;
	user-select: none;

	.k-tag-text {
		padding-inline: var(--spacing-2);
	}
}
</style>
