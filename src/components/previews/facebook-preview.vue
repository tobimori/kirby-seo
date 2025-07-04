<script setup>
import { computed } from "kirbyuse"

const props = defineProps({
	ogTitle: String,
	url: String,
	ogDescription: String,
	ogImage: String
})

const host = computed(() => new window.URL(props.url).host)
</script>

<template>
	<div>
		<div class="k-facebook-preview">
			<div v-if="ogImage" class="k-facebook-preview__image">
				<img :src="ogImage" class="k-facebook-preview__img" />
			</div>
			<div class="k-facebook-preview__content">
				<span class="k-facebook-preview__url">{{ host }}</span>
				<span class="k-facebook-preview__title">{{ ogTitle }}</span>
				<p class="k-facebook-preview__description">{{ ogDescription }}</p>
			</div>
		</div>
		<a
			class="k-seo-preview__debugger"
			href="https://developers.facebook.com/tools/debug/"
			aria-label="Facebook Sharing Debugger"
			target="_blank"
			rel="noopener noreferrer"
		>
			{{ $t("seo.sections.preview.openDebugger") }}
			<k-icon type="open" />
		</a>
	</div>
</template>

<style>
.k-facebook-preview {
	background: light-dark(#ffffff, #242526);
	border: 1px solid light-dark(#dadde1, #3e4042);
	overflow: hidden;
	border-radius: var(--rounded);
}

.k-facebook-preview__image {
	width: 100%;
	height: 0;
	padding-bottom: 52.355%;
	position: relative;

	img {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
}

.k-facebook-preview__content {
	padding: 0.75rem 1rem;
	background: light-dark(#f0f2f5, #363638);
}

.k-facebook-preview__title,
.k-facebook-preview__description,
.k-facebook-preview__url {
	display: -webkit-box;
	-webkit-box-orient: vertical;
	overflow: hidden;
	-webkit-line-clamp: 1;
}

.k-facebook-preview__url {
	color: light-dark(#65676b, #b0b3b8);
	font-size: 0.75rem;
	text-transform: uppercase;
	line-height: 1.1;
	margin-bottom: 0.25rem;
}

.k-facebook-preview__title {
	font-weight: 600;
	line-height: 1.1765;
	font-size: 1rem;
	color: light-dark(#050505, #e4e6eb);
	margin: 0.3125rem 0;
}

.k-facebook-preview__description {
	line-height: 1.3333;
	color: light-dark(#65676b, #b0b3b8);
	font-size: 0.875rem;
}
</style>
