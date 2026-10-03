<script setup>
defineProps({
	value: String,
	/** Whether the field is shown */
	searching: Boolean
})

const emit = defineEmits(["input", "toggle"])
</script>

<template>
	<div class="k-seo-search">
		<k-search-input
			v-if="searching"
			:autofocus="true"
			:placeholder="$t('filter') + ' …'"
			:value="value"
			class="k-input"
			@input="emit('input', $event)"
			@keydown.native.esc="emit('toggle')"
		/>
		<k-button
			:title="$t('filter')"
			:current="searching"
			icon="filter"
			size="xs"
			variant="filled"
			@click="emit('toggle')"
		/>
	</div>
</template>

<style>
.k-seo-search {
	display: flex;
	gap: var(--spacing-2);

	.k-search-input {
		--input-color-border: transparent;
		--input-color-back: light-dark(var(--color-gray-300), var(--color-gray-950));
		min-height: auto;
		height: var(--height-xs);
	}
}
</style>
