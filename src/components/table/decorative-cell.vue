<script setup>
/**
 * Toggle whether an image is decorative (doesn't need an alt text)
 */
defineProps({
	column: Object,
	row: Object,
	/** `{ value, editable }` */
	value: Object
})
</script>

<template>
	<!-- the label makes the whole cell clickable -->
	<label class="k-seo-decorative-cell" @click.stop>
		<k-toggle-input
			:value="value.value"
			:disabled="!value.editable"
			text=""
			@input="column.toggle(row, $event)"
		/>
		<span class="sr-only">{{ column.label }}</span>
	</label>
</template>

<style>
.k-seo-decorative-cell {
	display: flex;
	align-items: center;
	height: 100%;
	min-height: var(--table-row-height);
	padding-inline: var(--table-cell-padding);

	&:has(.k-toggle-input:not([aria-disabled="true"])) {
		cursor: pointer;
	}
}
</style>
