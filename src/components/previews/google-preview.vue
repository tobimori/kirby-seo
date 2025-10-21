<script setup>
import { computed } from "kirbyuse"

const props = defineProps({
	title: String,
	url: String,
	description: String,
	ogSiteName: String
})

const origin = computed(() => new window.URL(props.url).origin)
const pathname = computed(() => new window.URL(props.url).pathname)
const domain = computed(() => new window.URL(props.url).hostname)

const breadcrumbs = computed(() => {
	const path = pathname.value
	if (!path || path === "/") return ""

	const parts = path.split("/").filter(Boolean)
	if (parts.length === 0) return ""
	if (parts.length === 1) return ` › ${parts[0]}`

	// show last part with ellipsis for middle parts
	return ` › … › ${parts[parts.length - 1]}`
})
</script>

<template>
	<div>
		<div class="k-google-search-preview">
			<div class="k-google-search-preview__header">
				<img
					class="k-google-search-preview__favicon"
					:src="`https://www.google.com/s2/favicons?domain=${domain.value}&sz=32`"
					:alt="`${ogSiteName} favicon`"
				/>
				<div class="k-google-search-preview__site-info">
					<span class="k-google-search-preview__site-title">{{
						ogSiteName
					}}</span>
					<span class="k-google-search-preview__url">
						{{ origin }}{{ breadcrumbs }}
					</span>
				</div>
			</div>

			<h3 class="k-google-search-preview__title">{{ title }}</h3>
			<p v-if="description" class="k-google-search-preview__description">
				{{ description }}
			</p>
		</div>
		<a
			class="k-seo-preview__debugger"
			href="https://search.google.com/search-console"
			aria-label="Google Search Console"
			target="_blank"
			rel="noopener noreferrer"
		>
			{{ $t("seo.sections.preview.openSearchConsole") }}
			<k-icon type="open" />
		</a>
	</div>
</template>

<style>
.k-google-search-preview {
	padding: 1rem;
	background: var(--input-color-back);
	border-radius: var(--input-rounded);
	overflow: hidden;
}

.k-google-search-preview__header {
	display: flex;
	align-items: center;
	gap: 0.75rem;
	margin-bottom: 0.75rem;
}

.k-google-search-preview__favicon {
	display: inline-flex;
	width: 26px;
	height: 26px;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
	border: 1px solid light-dark(#ecedef, #9aa0a6);
	background: light-dark(#f1f3f4, #fff);
	margin: 0;

	img {
		display: block;
		width: 18px;
		height: 18px;
	}
}

.k-google-search-preview__site-info {
	display: flex;
	flex-direction: column;
	min-width: 0;
	flex: 1;
}

.k-google-search-preview__site-title {
	font-size: 0.875rem;
	color: light-dark(#202124, #bdc1c6);
	line-height: 1.2;
	margin-bottom: 0.125rem;
	display: block;
}

.k-google-search-preview__url {
	font-size: 0.75rem;
	color: light-dark(#5f6368, #9aa0a6);
	line-height: 1.2;
	display: -webkit-box;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: 1;
	overflow: hidden;
}

.k-google-search-preview__title {
	margin: 0;
	font-size: 1.25rem;
	font-weight: normal;
	color: light-dark(#1a0dab, #99c3ff);
	line-height: 1.2;
	display: -webkit-box;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: 1;
	overflow: hidden;
}

.k-google-search-preview__description {
	margin: 0.25rem 0 0;
	font-size: 0.875rem;
	color: light-dark(#4d5156, #bfbfbf);
	line-height: 1.4;
	display: -webkit-box;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: 2;
	overflow: hidden;
}
</style>
