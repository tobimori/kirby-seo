<script setup>
import { ref, useSection, watch } from "kirbyuse"
import { section } from "kirbyuse/props"

const props = defineProps(section)

const state = ref("active")
const local = ref(false)

const loadSection = async () => {
	const { load } = useSection()
	const response = await load({
		parent: props.parent,
		name: props.name
	})

	state.value = response.state
	local.value = response.local
}

loadSection()
watch(() => props.timestamp, loadSection)
</script>

<template>
	<div class="k-section k-seo-license-section">
		<k-seo-license-banner v-if="state !== 'active'" :state="state" :local="local" />
	</div>
</template>

<style>
.k-column:has(> .k-seo-license-section:empty) {
	display: none;
}
</style>
