<script setup>
import { computed, ref, usePanel } from "kirbyuse"

const props = defineProps({
	state: String,
	local: Boolean
})

const message = computed(() => {
	if (["expired", "revoked"].includes(props.state)) return `seo.license.${props.state}`
	return props.local ? "seo.license.cta" : "seo.license.missing"
})

const STORAGE_KEY = "kirby$seo$license$banner"

const isClosed = ref(window.sessionStorage.getItem(STORAGE_KEY) === "true")

const close = () => {
	window.sessionStorage.setItem(STORAGE_KEY, "true")
	isClosed.value = true
}

const panel = usePanel()
</script>

<template>
	<div v-if="!local || !isClosed" class="k-seo-license">
		<div class="k-seo-license__info">
			<a href="https://www.andkindness.com/seo" target="_blank" class="k-seo-license__logo">
				<k-icon type="search" />
				<span>Kirby SEO</span>
			</a>
			<p v-text="$t(message)" />
		</div>
		<div class="k-seo-license__actions">
			<a href="https://www.andkindness.com/buy?plugin=seo" target="_blank">
				{{ $t("seo.license.buy") }}
			</a>
			<k-button-group layout="collapsed">
				<k-button
					size="sm"
					theme="pink"
					variant="filled"
					icon="key"
					:text="$t('seo.license.activate')"
					@click="panel.dialog.open('seo/activate')"
				/>
				<k-button
					v-if="local"
					size="sm"
					theme="pink"
					variant="filled"
					icon="cancel-small"
					:title="$t('close')"
					@click="close()"
				/>
			</k-button-group>
		</div>
	</div>
</template>

<style>
.k-seo-license {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: var(--spacing-2);
	padding: var(--spacing-1);
	background: var(--color-pink-300);
	border-radius: var(--rounded-lg);
	color: var(--color-black);
}

.k-seo-license__info,
.k-seo-license__actions {
	display: flex;
	align-items: center;
}

.k-seo-license__info {
	flex-wrap: wrap;
	gap: var(--spacing-1) var(--spacing-4);
	padding: var(--spacing-1);
}

.k-seo-license__logo {
	display: flex;
	align-items: center;
	gap: var(--spacing-2);
	color: var(--color-pink-800);
	font-weight: var(--font-semi);

	.k-icon {
		--icon-size: 1rem;
	}
}

.k-seo-license__actions {
	gap: var(--spacing-3);
	margin-left: auto;

	a {
		color: var(--color-pink-800);
		text-decoration: underline;
		text-underline-offset: 0.125rem;
	}

	.k-button-group[data-layout="collapsed"] > .k-button {
		--theme-color-border: var(--color-pink-300);
	}
}
</style>
