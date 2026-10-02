<script setup>
defineProps({
	column: Object,
	row: Object,
	value: Object
})
</script>

<template>
	<div :data-translated="value.translated !== false" class="k-seo-page-cell">
		<span class="k-seo-page-cell-heading">
			<k-link :to="value.href" class="k-seo-page-cell-title" @click.native.stop>
				{{ value.text }}
			</k-link>
			<!-- browsers don't show tooltips for the `title` of (inline) SVGs, so the icons are wrapped -->
			<span
				v-if="value.translated === false"
				:title="$t('seo.overview.notTranslated')"
				:aria-label="$t('seo.overview.notTranslated')"
				role="img"
				class="k-seo-page-cell-indicator k-seo-page-cell-untranslated"
			>
				<k-icon type="translate" />
			</span>
			<span
				v-if="value.changes"
				:title="$t('lock.unsaved')"
				:aria-label="$t('lock.unsaved')"
				role="img"
				class="k-seo-page-cell-indicator k-seo-page-cell-changes"
			>
				<k-icon type="edit-line" />
			</span>
		</span>
		<span v-if="value.info !== undefined" class="k-seo-page-cell-info">{{ value.info }}</span>
		<span v-else class="k-seo-page-cell-path">{{ value.path }}</span>
	</div>
</template>

<style>
.k-seo-page-cell {
	display: flex;
	flex-direction: column;
	justify-content: center;
	min-height: var(--table-row-height);
	padding: var(--spacing-2) var(--table-cell-padding);
	line-height: var(--leading-tight);
}

.k-seo-page-cell-heading {
	display: flex;
	align-items: center;
	gap: var(--spacing-1);
	min-width: 0;
}

.k-seo-page-cell-indicator {
	--icon-size: 14px;
	display: flex;
	flex-shrink: 0;

	/* the span must be the hovered element: Safari looks for a tooltip on the SVG only
	   and doesn't fall back to the `title` of its parents */
	.k-icon {
		pointer-events: none;
	}
}

.k-seo-page-cell-untranslated {
	--icon-color: var(--color-text-dimmed);
}

/* same as the unsaved changes indicator in Kirby's language dropdown */
.k-seo-page-cell-changes {
	--icon-color: var(--color-orange-500);
}

.k-seo-page-cell-title {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	color: var(--link-color);
	border-radius: var(--rounded-xs);

	&:hover {
		text-decoration: underline;
	}

	&:focus-visible {
		outline: var(--outline);
	}
}

.k-seo-page-cell-info {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-size: var(--text-xs);
	color: var(--color-text-dimmed);
}

.k-seo-page-cell-path {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-family: var(--font-mono);
	font-size: var(--text-xs);
	color: var(--color-text-dimmed);
}
</style>
