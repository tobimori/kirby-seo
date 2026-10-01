<script>
// only one popover is shown at a time, across all cells
let active = null
</script>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, usePanel } from "kirbyuse"

import { SEVERITY, SEVERITY_ICONS } from "../../utils/checks.js"

const props = defineProps({
	column: Object,
	row: Object,
	/** `{ issues: [...] }` or `{ skipped: "draft" | "untranslated" | "noindex" }` */
	value: Object
})

const panel = usePanel()
const id = `k-seo-checks-${props.row.id.replace(/[^a-z0-9_-]/gi, "-")}`

const label = (issue) =>
	panel.t(
		issue.variant
			? `seo.overview.checks.${issue.type}.${issue.variant}`
			: `seo.overview.checks.${issue.type}`
	)

// short info, shown next to the issue like the codes in Kirby's languages dropdown
const info = (issue) => {
	if (issue.group) {
		return panel.t("seo.overview.checks.duplicate.show", { count: issue.count })
	}

	if (issue.variant) {
		return panel.t(`seo.overview.checks.length.info.${issue.variant}`, issue)
	}

	if (issue.source) {
		return panel.t(`seo.overview.sourceLabel.${issue.source}`)
	}

	return null
}

const issues = computed(() =>
	(props.value.issues ?? []).map((issue) => ({
		...issue,
		text: label(issue),
		info: info(issue),
		severity: SEVERITY[issue.type]
	}))
)

// the most severe issue decides the indicator
const severity = computed(() =>
	issues.value.some((issue) => issue.severity === "negative") ? "negative" : "notice"
)

/**
 * Disclosure (https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/): the button toggles
 * a popover with the issues & actions. It also opens on hover, then closes again when
 * the pointer leaves (after a short delay, so the pointer can move onto it). When opened
 * via click/keyboard, it stays open until Escape, clicking outside or moving the focus away.
 * Opening it doesn't move the focus, Tab continues from the button into the popover.
 */
const HIDE_DELAY = 150

const root = ref(null)
const button = ref(null)
const popover = ref(null)
const isOpen = ref(false)
const isPinned = ref(false)
const position = ref({ top: 0, left: 0 })
let hideTimer = null

// below the button & aligned to its start (like Kirby's dropdowns),
// flipped if there's not enough space below or towards the end
const place = () => {
	if (!isOpen.value) {
		return
	}

	const anchor = button.value.getBoundingClientRect()
	const { width, height } = popover.value.getBoundingClientRect()
	const gap = 4
	const fitsBelow = anchor.bottom + gap + height <= window.innerHeight
	const rtl = panel.direction === "rtl"
	const start = rtl ? anchor.right - width : anchor.left
	const end = rtl ? anchor.left : anchor.right - width
	const fitsStart = rtl ? start >= gap : start + width <= window.innerWidth - gap

	position.value = {
		top: fitsBelow ? anchor.bottom + gap : anchor.top - gap - height,
		left: Math.max(gap, fitsStart ? start : end)
	}
}

const close = () => {
	window.clearTimeout(hideTimer)

	if (!isOpen.value) {
		return
	}

	popover.value?.hidePopover()
	isOpen.value = false
	isPinned.value = false

	if (active?.close === close) {
		active = null
	}

	window.removeEventListener("pointerdown", onPointerdown)
	window.removeEventListener("keydown", onKeydown)
	window.removeEventListener("scroll", place, { capture: true })
	window.removeEventListener("resize", place)
}

const open = async () => {
	window.clearTimeout(hideTimer)

	if (isOpen.value) {
		return
	}

	// close the popover of any other cell right away
	active?.close()
	active = { close, isPinned: () => isPinned.value }

	popover.value.showPopover()
	isOpen.value = true
	await nextTick()
	place()

	window.addEventListener("pointerdown", onPointerdown)
	window.addEventListener("keydown", onKeydown)
	window.addEventListener("scroll", place, { capture: true, passive: true })
	window.addEventListener("resize", place)
}

const onEnter = () => {
	// hovering doesn't replace a popover that was opened on purpose
	if (active && active.close !== close && active.isPinned()) {
		return
	}

	open()
}

const onLeave = () => {
	if (!isPinned.value) {
		window.clearTimeout(hideTimer)
		hideTimer = window.setTimeout(close, HIDE_DELAY)
	}
}

// click, Enter & Space
const onClick = () => {
	if (isOpen.value && isPinned.value) {
		return close()
	}

	isPinned.value = true
	open()
}

// the popover is part of the cell, even though it's rendered on top of the page
const onPointerdown = (event) => {
	if (!root.value?.contains(event.target)) {
		close()
	}
}

const onKeydown = (event) => {
	if (event.key !== "Escape") {
		return
	}

	const hadFocus = root.value?.contains(document.activeElement)
	close()

	if (hadFocus) {
		button.value?.focus()
	}
}

const onFocusout = (event) => {
	if (event.relatedTarget && !root.value?.contains(event.relatedTarget)) {
		close()
	}
}

const onFilter = (issue) => {
	close()

	if (issue.group) {
		props.column.filterGroup?.(issue.group)
	} else {
		props.column.filterIssue?.(issue.type)
	}
}

onBeforeUnmount(close)
</script>

<template>
	<span
		v-if="value.skipped"
		:title="`${$t(`seo.overview.checks.skipped.${value.skipped}`)}: ${$t('seo.overview.checks.skipped')}`"
		:aria-label="$t(`seo.overview.checks.skipped.${value.skipped}`)"
		role="img"
		class="k-seo-checks-cell k-seo-checks-cell-skipped"
	>
		<k-icon type="circle-nested" />
	</span>
	<span
		v-else-if="issues.length === 0"
		:title="$t('seo.overview.checks.ok')"
		:aria-label="$t('seo.overview.checks.ok')"
		role="img"
		class="k-seo-checks-cell k-seo-checks-cell-ok"
	>
		<k-icon type="check" />
	</span>
	<div
		v-else
		ref="root"
		class="k-seo-checks-cell"
		@mouseenter="onEnter"
		@mouseleave="onLeave"
		@focusout="onFocusout"
	>
		<!-- k-button markup, as k-button doesn't pass aria attributes on -->
		<button
			ref="button"
			:aria-label="$t('seo.overview.checks.count', { count: issues.length })"
			:aria-expanded="String(isOpen)"
			:aria-controls="id"
			:data-theme="`${severity}-icon`"
			data-has-icon="true"
			data-size="md"
			type="button"
			class="k-button"
			@click="onClick"
		>
			<span class="k-button-icon"><k-icon :type="SEVERITY_ICONS[severity]" /></span>
		</button>
		<div
			:id="id"
			ref="popover"
			:style="{ top: `${position.top}px`, left: `${position.left}px` }"
			popover="manual"
			class="k-seo-checks-popover"
		>
			<!-- each issue filters the table: duplicates by the pages sharing the same value,
			     other issues by all pages with the same issue -->
			<k-dropdown-item
				v-for="issue in issues"
				:key="issue.type"
				:theme="`${issue.severity}-icon`"
				:icon="SEVERITY_ICONS[issue.severity]"
				class="k-seo-checks-popover-item"
				@click="onFilter(issue)"
			>
				{{ issue.text }}
				<span v-if="issue.info" class="k-seo-checks-popover-info">{{ issue.info }}</span>
			</k-dropdown-item>
			<hr />
			<k-dropdown-item :link="`${row.link}?tab=seo`" icon="open" class="k-seo-checks-popover-item">
				{{ $t("seo.overview.checks.open") }}
			</k-dropdown-item>
		</div>
	</div>
</template>

<style>
.k-seo-checks-cell {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	min-height: var(--table-row-height);

	> .k-button {
		--button-height: var(--table-row-height);
		--button-width: 100%;
		outline-offset: -2px;
	}
}

/* the span must be the hovered element, see page-cell */
.k-seo-checks-cell-ok,
.k-seo-checks-cell-skipped {
	.k-icon {
		pointer-events: none;
	}
}

.k-seo-checks-cell-ok {
	--icon-color: var(--color-positive);
}

.k-seo-checks-cell-skipped {
	--icon-color: var(--color-gray-400);
}

/* same look as Kirby's dropdowns, e.g. the languages dropdown */
.k-seo-checks-popover {
	position: fixed;
	inset: auto;
	margin: 0;
	width: max-content;
	min-width: 15rem;
	padding: var(--dropdown-padding);
	font-size: var(--text-sm);
	color: var(--dropdown-color-text);
	background: var(--dropdown-color-bg);
	border: 0;
	border-radius: var(--dropdown-rounded);
	box-shadow: var(--dropdown-shadow);
	text-align: start;

	hr {
		margin: 0.5rem 0;
		height: 1px;
		border: 0;
		background: var(--dropdown-color-hr);
	}
}

.k-dropdown-item.k-seo-checks-popover-item {
	/* only show the outline when navigating with the keyboard */
	&:focus:not(:focus-visible) {
		outline: none;
	}
}

/* the info is aligned to the end, like the codes in Kirby's languages dropdown */
.k-seo-checks-popover-item .k-button-text {
	display: flex;
	flex-grow: 1;
	justify-content: space-between;
	align-items: center;
	gap: var(--spacing-6);
	white-space: nowrap;
}

.k-seo-checks-popover-info {
	font-size: var(--text-xs);
	color: var(--color-gray-500);
}
</style>
