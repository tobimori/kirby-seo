<script setup>
import { computed, ref, usePanel } from "kirbyuse"

/**
 * Save & discard buttons for unsaved changes of multiple pages,
 * modelled after Kirby's `k-form-controls` of page views
 */
const props = defineProps({
	/** Pages with unsaved changes: `{ id, link, text }` */
	changes: {
		type: Array,
		default: () => []
	},
	isProcessing: Boolean
})

const emit = defineEmits(["discard", "submit"])

const panel = usePanel()
const dropdown = ref(null)

// confirmations are up to the parent, as they depend on what is being published/discarded

const buttons = computed(() => {
	if (props.changes.length === 0) {
		return []
	}

	return [
		{
			theme: "notice",
			text: panel.t("discard"),
			icon: "undo",
			responsive: true,
			click: () => emit("discard")
		},
		{
			theme: "notice",
			text: panel.t("save"),
			icon: props.isProcessing ? "loader" : "check",
			click: () => emit("submit")
		},
		{
			title: panel.t("options"),
			theme: "notice",
			icon: "dots",
			badge: { text: String(props.changes.length), theme: "notice" },
			click: () => dropdown.value.toggle()
		}
	]
})
</script>

<template>
	<div v-if="buttons.length" class="k-form-controls k-seo-changes-controls">
		<k-button-group layout="collapsed">
			<k-button
				v-for="button in buttons"
				:key="button.text ?? button.title"
				v-bind="button"
				:disabled="isProcessing"
				class="k-form-controls-button"
				variant="filled"
				size="sm"
			/>
		</k-button-group>
		<k-dropdown-content ref="dropdown" align-x="end" class="k-form-controls-dropdown">
			<p>{{ $t("form.unsaved") }}</p>
			<hr />
			<k-dropdown-item v-for="page in changes" :key="page.id" :link="page.link" icon="page">
				{{ page.text }}
			</k-dropdown-item>
		</k-dropdown-content>
	</div>
</template>

<style>
.k-seo-changes-controls .k-form-controls-dropdown {
	max-height: 50vh;
	overflow-y: auto;
}
</style>
