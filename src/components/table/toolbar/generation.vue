<script setup>
defineProps({
	/** `{ done, total }` */
	generation: Object
})

const emit = defineEmits(["cancel"])
</script>

<template>
	<k-button-group layout="collapsed">
		<k-button
			:text="$t('seo.overview.ai.progress', generation)"
			icon="loader"
			size="xs"
			variant="filled"
		/>
		<k-button
			:title="$t('seo.ai.action.stop')"
			icon="cancel-small"
			size="xs"
			variant="filled"
			@click="emit('cancel')"
		/>
	</k-button-group>
</template>
