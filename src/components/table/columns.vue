<script setup>
import { ref } from "kirbyuse"

defineProps({
	/** `[{ value, text }]` of the columns that can be toggled */
	options: Array,
	/** Keys of the visible columns */
	value: Array
})

const emit = defineEmits(["input"])

const dropdown = ref(null)
</script>

<template>
	<div class="k-seo-columns">
		<k-button
			:title="$t('seo.overview.columns.toggle')"
			icon="layout-columns"
			size="xs"
			variant="filled"
			@click="dropdown.toggle()"
		/>
		<k-picklist-dropdown
			ref="dropdown"
			:options="options"
			:value="value"
			:search="false"
			@input="emit('input', $event)"
		/>
	</div>
</template>

<style>
.k-seo-columns {
	display: contents;
}
</style>
