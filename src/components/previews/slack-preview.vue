<script setup>
import { computed } from "kirbyuse"

const props = defineProps({
	ogTitle: String,
	ogSiteName: String,
	ogDescription: String,
	ogImage: String,
	url: String
})

const origin = computed(() => new URL(props.url).origin)
</script>

<template>
	<div class="k-slack-preview">
		<div class="k-slack-preview__content">
			<div class="k-slack-preview__site-name">{{ ogSiteName || origin }}</div>
			<span class="k-slack-preview__title">{{ ogTitle }}</span>
			<p class="k-slack-preview__description">{{ ogDescription }}</p>
		</div>
		<div class="k-slack-preview__image" v-if="ogImage">
			<img :src="ogImage" />
		</div>
	</div>
</template>

<style>
.k-slack-preview {
	max-width: 32.5rem;
	position: relative;
	padding-left: 1rem;
	line-height: 1.46666667;
	font-size: 0.9375rem;

	&::before {
		position: absolute;
		content: "";
		top: 0;
		left: 0;
		bottom: 0;
		width: 0.25rem;
		border-radius: 0.5rem;
		background: #ddd;
	}
}

.k-slack-preview__site-name {
	display: flex;
	align-items: center;
	color: #717274;
}

.k-slack-preview__title {
	font-weight: 700;
	display: block;
	color: #0576b9;
	cursor: pointer;

	&:hover {
		text-decoration: underline;
	}
}

.k-slack-preview__description {
	color: #2c2d30;
}

.k-slack-preview__image {
	border-radius: 0.5rem;
	max-width: 22.5rem;
	overflow: hidden;
	position: relative;
	margin-top: 0.5rem;

	&::before {
		border-radius: 0.5rem;
		content: "";
		inset: 0;
		z-index: 2;
		position: absolute;
		box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.1);
	}

	img {
		width: 100%;
		height: 100%;
		display: block;
	}
}
</style>
