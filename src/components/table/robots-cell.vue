<script setup>
defineProps({
	column: Object,
	row: Object,
	value: Object
})

// same themes as the robots view button
const THEMES = {
	index: "positive-icon",
	any: "notice-icon",
	noindex: "negative-icon"
}
</script>

<template>
	<k-button
		:icon="value.state === 'index' ? 'robots' : 'robots-off'"
		:theme="THEMES[value.state]"
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
