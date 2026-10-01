<script setup>
import { computed } from "kirbyuse"

const props = defineProps({
	column: Object,
	row: Object,
	value: Object
})

const inherited = computed(() => props.value.source && props.value.source !== "fields")
</script>

<template>
	<div :data-inherited="inherited" class="k-seo-image-cell">
		<k-image-frame
			:src="value.src"
			:title="inherited ? $t(`seo.overview.source.${value.source}`) : null"
			back="pattern"
			ratio="1200/630"
			cover
		/>
	</div>
</template>

<style>
.k-seo-image-cell {
	display: flex;
	align-items: center;
	height: 100%;
	min-height: var(--table-row-height);
	padding: var(--spacing-1) var(--spacing-2);

	.k-frame {
		width: 100%;
		border-radius: var(--rounded-sm);
	}

	&[data-inherited="true"] .k-frame {
		opacity: 0.5;
	}
}
</style>
