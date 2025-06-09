<template>
	<div class="k-section k-heading-structure" v-if="data">
		<div
			class="k-field-header k-heading-structure__label k-label k-field-label"
		>
			<k-icon type="headline" />
			<span class="k-label-text">{{
				props.label || $t("seo.sections.headingStructure.title")
			}}</span>
		</div>
		<k-box theme="white">
			<ol class="k-heading-structure__list">
				<li
					v-for="(item, index) in data"
					:key="index"
					:style="`z-index: ${data.length - index}`"
					:class="`k-heading-structure__item level-${item.level} ${
						itemInvalid(item, index) ? 'is-invalid' : ''
					}`"
				>
					<span class="k-heading-structure__item__level"
						>H{{ item.level }}</span
					>
					<span class="k-heading-structure__item__text">{{ item.text }}</span>
				</li>
			</ol>
		</k-box>
		<k-box
			class="k-heading-structure__notice"
			theme="negative"
			v-if="incorrectOrder && !noH1"
		>
			<k-icon type="alert" />
			<k-text>{{
				$t("seo.sections.headingStructure.errors.incorrectOrder")
			}}</k-text>
		</k-box>
		<k-box
			class="k-heading-structure__notice"
			theme="negative"
			v-if="multipleH1"
		>
			<k-icon type="alert" />
			<k-text>{{
				$t("seo.sections.headingStructure.errors.multipleH1")
			}}</k-text>
		</k-box>
		<k-box class="k-heading-structure__notice" theme="negative" v-if="noH1">
			<k-icon type="alert" />
			<k-text>{{
				$t("seo.sections.headingStructure.errors.missingH1")
			}}</k-text>
		</k-box>
	</div>
</template>

<script setup>
import {
	ref,
	computed,
	onMounted,
	onUnmounted,
	usePanel,
	useSection
} from "kirbyuse"
import { section } from "kirbyuse/props"

const props = defineProps(section)

const panel = usePanel()
const { load } = useSection()

// State
const data = ref(null)

const incorrectOrder = computed(() =>
	data.value?.some(
		(item, index) => item.level > (data.value[index - 1]?.level ?? 0) + 1
	)
)

const multipleH1 = computed(
	() => data.value?.filter((item) => item.level === 1).length > 1
)

const noH1 = computed(
	() => data.value?.filter((item) => item.level === 1).length === 0
)

// Methods
const handleLoad = () =>
	load({
		parent: props.parent,
		name: props.name
	}).then((response) => {
		data.value = response.data
	})

const itemInvalid = (item, index) => {
	if (item.level > (data.value[index - 1]?.level ?? 0) + 1) return true // wrong order
	if (item.level === 1 && data.value[index - 1]) return true // wrong order
	if (
		item.level === 1 &&
		data.value.filter((item) => item.level === 1).length > 1
	)
		return true // multiple h1

	return false
}

// Lifecycle
onMounted(() => {
	handleLoad()

	// this will trigger after the server has finished processing the request as changes
	panel.events.on("content.save", (_event) => {
		handleLoad()
	})
})

onUnmounted(() => panel.events.off("content.save"))
</script>

<style>
.k-heading-structure__label {
	display: flex;
	align-items: center;
	justify-content: flex-start;
	gap: var(--spacing-2);

	> .k-icon {
		color: var(--color-gray-700);
	}

	> .k-loader {
		margin-left: auto;
		color: var(--color-gray-700);
	}
}

.k-heading-structure__notice {
	margin-top: var(--spacing-2);
	display: flex;
	align-items: flex-start;

	> .k-icon {
		margin-top: 0.125rem;
		margin-right: var(--spacing-1);
		color: var(--color-red);
	}
}

.k-heading-structure__list {
	overflow: hidden;
}

.k-heading-structure__item {
	position: relative;
	background: var(--theme-color-back);
	padding-block: var(--spacing-px);
	display: flex;

	&.is-invalid {
		color: var(--color-red);
	}
}

.k-heading-structure__item__level {
	font-family: var(--font-mono);
	font-weight: 700;
	margin-right: var(--spacing-2);
}

.k-heading-structure__item__text {
	white-space: nowrap;
	text-overflow: ellipsis;
	overflow: hidden;
}

/* Level-specific styling */
.k-heading-structure__item.level-2 {
	margin-left: 0;
	padding-left: 1.6rem;

	&::before {
		content: "";
		position: absolute;
		top: calc(50% - 0.0625rem);
		left: 0.4rem;
		width: 0.8rem;
		height: 0.125rem;
		background-color: currentColor;
	}

	&::after {
		content: "";
		position: absolute;
		bottom: calc(50% - 0.0625rem);
		left: 0.4rem;
		height: 9999px;
		width: 0.125rem;
		background-color: currentColor;
	}
}

.k-heading-structure__item.level-3 {
	margin-left: 1.6rem;
	padding-left: 1.6rem;

	&::before {
		content: "";
		position: absolute;
		top: calc(50% - 0.0625rem);
		left: 0.4rem;
		width: 0.8rem;
		height: 0.125rem;
		background-color: currentColor;
	}

	&::after {
		content: "";
		position: absolute;
		bottom: calc(50% - 0.0625rem);
		left: 0.4rem;
		height: 9999px;
		width: 0.125rem;
		background-color: currentColor;
	}
}

.k-heading-structure__item.level-4 {
	margin-left: 3.2rem;
	padding-left: 1.6rem;

	&::before {
		content: "";
		position: absolute;
		top: calc(50% - 0.0625rem);
		left: 0.4rem;
		width: 0.8rem;
		height: 0.125rem;
		background-color: currentColor;
	}

	&::after {
		content: "";
		position: absolute;
		bottom: calc(50% - 0.0625rem);
		left: 0.4rem;
		height: 9999px;
		width: 0.125rem;
		background-color: currentColor;
	}
}

.k-heading-structure__item.level-5 {
	margin-left: 4.8rem;
	padding-left: 1.6rem;

	&::before {
		content: "";
		position: absolute;
		top: calc(50% - 0.0625rem);
		left: 0.4rem;
		width: 0.8rem;
		height: 0.125rem;
		background-color: currentColor;
	}

	&::after {
		content: "";
		position: absolute;
		bottom: calc(50% - 0.0625rem);
		left: 0.4rem;
		height: 9999px;
		width: 0.125rem;
		background-color: currentColor;
	}
}

.k-heading-structure__item.level-6 {
	margin-left: 6.4rem;
	padding-left: 1.6rem;

	&::before {
		content: "";
		position: absolute;
		top: calc(50% - 0.0625rem);
		left: 0.4rem;
		width: 0.8rem;
		height: 0.125rem;
		background-color: currentColor;
	}

	&::after {
		content: "";
		position: absolute;
		bottom: calc(50% - 0.0625rem);
		left: 0.4rem;
		height: 9999px;
		width: 0.125rem;
		background-color: currentColor;
	}
}
</style>
