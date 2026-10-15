<script setup>
defineOptions({ inheritAttrs: false })

defineProps({
	local: Boolean,
	state: String
})

// display fields store nothing, declaring the events keeps native events from bubbling into the form
defineEmits(["input", "focus", "submit"])
</script>

<template>
	<div :class="['k-seo-license-field', $attrs.class]" :style="$attrs.style">
		<k-seo-license-banner v-if="state !== 'active'" :state="state" :local="local" />
	</div>
</template>
