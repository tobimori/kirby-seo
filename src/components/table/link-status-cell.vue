<script setup>
import { computed, usePanel } from "kirbyuse"

/**
 * State of a link as colored dot & its HTTP status code, like in Retour:
 * `broken`, `anchor`, `redirect`, `unknown` or `ok`
 */
const props = defineProps({
	column: Object,
	row: Object,
	/** `{ state, code }` */
	value: Object
})

const panel = usePanel()

const label = computed(() => panel.t(`seo.overview.links.status.${props.value.state}`))

// the page exists, the anchor doesn't
const code = computed(() => (props.value.state === "anchor" ? "#" : props.value.code))
</script>

<template>
	<div :title="label" :data-state="value.state" class="k-seo-link-status-cell">
		<k-icon type="circle-filled" />
		<code v-if="code">{{ code }}</code>
		<span class="sr-only">{{ label }}</span>
	</div>
</template>

<style>
.k-seo-link-status-cell {
	--icon-color: var(--color-gray-400);
	display: flex;
	align-items: center;
	gap: var(--spacing-2);
	min-height: var(--table-row-height);
	padding-inline: var(--table-cell-padding);

	.k-icon {
		--icon-size: 14px;
		flex-shrink: 0;
	}

	&[data-state="ok"] {
		--icon-color: var(--color-green-500);
	}

	&[data-state="redirect"],
	&[data-state="anchor"] {
		--icon-color: var(--color-orange-500);
	}

	&[data-state="broken"] {
		--icon-color: var(--color-red-500);
	}
}
</style>
