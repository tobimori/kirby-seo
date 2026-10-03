<script setup>
defineProps({
	count: Number,
	/** Number of all rows matching the current search & filters */
	total: Number,
	/** Whether all of them are selected */
	all: Boolean
})

const emit = defineEmits(["all", "clear"])
</script>

<template>
	<k-button-group layout="collapsed">
		<k-button :text="$t('seo.overview.selection.count', { count })" size="xs" variant="filled" />
		<k-button
			v-if="!all"
			:text="$t('seo.overview.selection.all', { count: total })"
			size="xs"
			variant="filled"
			@click="emit('all')"
		/>
		<k-button
			:title="$t('seo.overview.selection.clear')"
			icon="cancel-small"
			size="xs"
			variant="filled"
			@click="emit('clear')"
		/>
	</k-button-group>
</template>
