<script setup>
defineProps({
	column: Object,
	row: Object,
	value: String
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
	<label v-else class="k-table-select-checkbox k-seo-table-select">
		<input
			:checked="column.isSelected(row)"
			:disabled="row.selectable === false"
			:aria-label="$t('seo.table.selectRow', { title: row.title?.text ?? value })"
			type="checkbox"
			@click.stop="column.toggle(row, $event)"
		/>
	</label>
</template>

<style>
.k-seo-table-lock {
	--button-height: var(--table-row-height);
	--button-width: 100%;
	--button-color-icon: var(--color-red-500);
	height: 100%;
	outline-offset: -2px;
}
</style>
