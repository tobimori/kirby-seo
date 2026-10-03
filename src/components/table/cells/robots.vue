<script setup>
defineProps({
	column: Object,
	row: Object,
	/** Same icon, text & theme as the robots view button: `{ icon, text, theme, value }` */
	value: Object
})
</script>

<template>
	<k-button
		:icon="value.icon"
		:theme="value.theme"
		:title="`${value.text} (${value.value})`"
		:link="`${row.link}?tab=seo`"
		size="md"
		class="k-seo-robots-cell"
	/>
</template>

<style>
.k-seo-robots-cell {
	--button-height: var(--table-row-height);
	--button-width: 100%;
	outline-offset: -2px;
}
</style>
