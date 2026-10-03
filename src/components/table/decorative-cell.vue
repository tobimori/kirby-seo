<script setup>
import { computed, useHelpers } from "kirbyuse"

import { rowName } from "../../utils/rows.js"

const props = defineProps({
	column: Object,
	row: Object,
	/** `{ value, editable }` */
	value: Object
})

const helpers = useHelpers()

// the label of the toggle is HTML & only read by screen readers, e.g. "Decorative: dog.jpg (Team)"
const label = computed(() =>
	helpers.string.escapeHTML(`${props.column.label}: ${rowName(props.row)}`)
)
</script>

<template>
	<div class="k-seo-decorative-cell" @click.stop>
		<k-toggle-input
			:value="value.value"
			:disabled="!value.editable"
			:text="label"
			@input="column.toggle(row, $event)"
		/>
	</div>
</template>

<style>
.k-seo-decorative-cell {
	height: 100%;

	.k-choice-input {
		display: flex;
		align-items: center;
		height: 100%;
		min-height: var(--table-row-height);
		padding-inline: var(--table-cell-padding);

		&:not([aria-disabled="true"]) {
			cursor: pointer;
		}
	}

	.k-choice-input-label {
		position: absolute;
		width: 1px;
		height: 1px;
		padding: 0;
		margin: -1px;
		overflow: hidden;
		clip: rect(0, 0, 0, 0);
		white-space: nowrap;
		border-width: 0;
	}
}
</style>
