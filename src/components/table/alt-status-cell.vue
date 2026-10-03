<script setup>
import { computed } from "kirbyuse"

import { SEVERITY_ICONS } from "../../utils/checks.js"

/**
 * State of an alt text: `missing`, `ai` (not reviewed yet), `decorative` or `ok`.
 * States other than `ok` filter the table by images in the same state
 */
const props = defineProps({
	column: Object,
	row: Object,
	/** `{ state, severity }` */
	value: Object
})

const icon = computed(() =>
	props.value.state === "decorative" ? "hidden" : SEVERITY_ICONS[props.value.severity]
)
</script>

<template>
	<k-button
		v-if="value.state !== 'ok' && column.filterIssue"
		:icon="icon"
		:theme="`${value.severity}-icon`"
		:title="$t(`seo.overview.images.status.${value.state}`)"
		:data-state="value.state"
		size="md"
		class="k-seo-alt-status-cell"
		@click="column.filterIssue(value.state)"
	/>
	<!-- browsers don't show tooltips for the `title` of (inline) SVGs, so the icon is wrapped -->
	<span
		v-else
		:title="$t(`seo.overview.images.status.${value.state}`)"
		:aria-label="$t(`seo.overview.images.status.${value.state}`)"
		:data-state="value.state"
		role="img"
		class="k-seo-alt-status-cell"
	>
		<k-icon :type="icon" />
	</span>
</template>

<style>
.k-seo-alt-status-cell {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	min-height: var(--table-row-height);

	&.k-button {
		--button-height: var(--table-row-height);
		--button-width: 100%;
		outline-offset: -2px;
	}

	&[data-state="ok"] {
		--icon-color: var(--color-positive);
	}

	&[data-state="decorative"] {
		--icon-color: var(--color-gray-400);
	}

	/* the span must be the hovered element, see page-cell */
	.k-icon {
		pointer-events: none;
	}
}
</style>
