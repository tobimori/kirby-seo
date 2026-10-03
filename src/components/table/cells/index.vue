<script setup>
import { rowName } from "../../../utils/rows.js"

defineProps({
	column: Object,
	row: Object,
	/** Number of the row, counting across table pages */
	value: Number
})
</script>

<template>
	<k-button
		v-if="row.lock"
		:title="$t('form.locked')"
		icon="lock"
		theme="negative"
		class="k-seo-table-lock"
		@click.stop="column.openLock(row)"
	/>
	<label v-else-if="column.selectable" class="k-seo-table-index k-seo-table-select">
		<input
			:checked="column.isSelected(row)"
			:disabled="row.selectable === false"
			:aria-label="$t('seo.table.selectRow', { title: rowName(row) ?? value })"
			type="checkbox"
			@click.stop="column.toggle(row, $event)"
		/>
		<span class="k-seo-table-index-number" aria-hidden="true">{{ value }}</span>
	</label>
	<span v-else class="k-seo-table-index">
		<span class="k-seo-table-index-number">{{ value }}</span>
	</span>
</template>

<style>
.k-seo-table-lock {
	--button-height: var(--table-row-height);
	--button-width: 100%;
	--button-color-icon: var(--color-red-500);
	height: 100%;
	outline-offset: -2px;
}

/* number & checkbox on top of each other, only one of them is visible */
.k-seo-table-index {
	--checkbox: 0;
	display: grid;
	place-items: center;
	height: 100%;
	min-height: var(--table-row-height);

	> * {
		grid-area: 1 / 1;
	}

	input {
		opacity: var(--checkbox);
		cursor: pointer;
	}
}

.k-seo-table-index-number {
	opacity: calc(1 - var(--checkbox));
	font-size: var(--text-xs);
	font-variant-numeric: tabular-nums;
	line-height: 1.1em;
	color: var(--color-text-dimmed);
	pointer-events: none;
}

.k-seo-table-select:is(:has(input:checked), :has(input:focus-visible)),
.k-seo-table tr:hover .k-seo-table-select,
.k-seo-table[data-selecting="true"] .k-seo-table-select {
	--checkbox: 1;
}
</style>
