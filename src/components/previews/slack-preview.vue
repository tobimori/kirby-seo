<script setup>
import { computed, ref } from "kirbyuse"

const props = defineProps({
	ogTitle: String,
	ogSiteName: String,
	ogDescription: String,
	ogImage: String,
	url: String
})

const origin = computed(() => new window.URL(props.url).origin)
const domain = computed(() => new window.URL(props.url).hostname)

const showImage = ref(true)
</script>

<template>
	<div class="k-slack-preview">
		<div class="k-slack-preview__content">
			<div class="k-slack-preview__site-name">
				<img
					class="k-slack-preview__favicon"
					:src="`https://www.google.com/s2/favicons?domain=${domain}&sz=16`"
					:alt="`${ogSiteName} favicon`"
				/>
				{{ ogSiteName || origin }}
			</div>
			<span class="k-slack-preview__title">{{ ogTitle }}</span>
			<p class="k-slack-preview__description">
				{{ ogDescription }}
				<button
					v-if="ogImage"
					class="k-slack-preview__image-toggle"
					@click="showImage = !showImage"
				>
					{{ showImage ? "▼" : "▶" }}
				</button>
			</p>
		</div>
		<div v-if="ogImage && showImage" class="k-slack-preview__image">
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
		background: light-dark(#ddd, #4a4b4d);
	}
}

.k-slack-preview__site-name {
	display: flex;
	align-items: center;
	gap: 0.25rem;
	color: light-dark(#616061, #d1d2d3);
	font-size: 0.75rem;
	margin-bottom: 0.25rem;
}

.k-slack-preview__favicon {
	width: 16px;
	height: 16px;
}

.k-slack-preview__title {
	font-weight: 700;
	display: block;
	color: light-dark(#1264a3, #1d9bd1);
	cursor: pointer;
	margin-bottom: 0.25rem;

	&:hover {
		text-decoration: underline;
	}
}

.k-slack-preview__description {
	color: light-dark(#1d1c1d, #d1d2d3);
	margin: 0 0 0.25rem 0;
}

.k-slack-preview__image-toggle {
	color: light-dark(#1264a3, #1d9bd1);
	margin-left: 0.25rem;
	font-size: 0.625rem;
	background: none;
	border: none;
	padding: 0;
	cursor: pointer;
	font-family: inherit;

	&:hover {
		opacity: 0.7;
	}
}

.k-slack-preview__image {
	border-radius: 0.5rem;
	max-width: 22.5rem;
	overflow: hidden;
	position: relative;

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
