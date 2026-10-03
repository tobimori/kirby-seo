<script setup>
import { computed, nextTick, onBeforeUnmount, ref, usePanel, watch } from "kirbyuse"

import { usePopoverGroup } from "../../composables/popover.js"

/**
 * Disclosure (https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/) for the cells of tables:
 * the button toggles a popover with details & actions. It also opens on hover, then closes
 * again when the pointer leaves (after a short delay, so the pointer can move onto it).
 * When opened via click/keyboard, it's pinned: it stays open until Escape, clicking outside
 * or moving the focus away, and hovering other cells doesn't replace it.
 * Opening it doesn't move the focus, Tab continues from the button into it.
 *
 * Slots: `button` for the content of the button, the default slot (with `close`) for the popover
 */
defineProps({
	/** Attributes of the button, e.g. its `class` & `aria-label` */
	button: Object
})

const HIDE_DELAY = 150

const panel = usePanel()

// only one popover of the table is open at a time
const group = usePopoverGroup()
const self = Symbol("popover")
const isOpen = computed(() => group.value?.owner === self)
const isPinned = computed(() => isOpen.value && group.value.pinned)

const root = ref(null)
const trigger = ref(null)
const popover = ref(null)
const position = ref({ top: 0, left: 0 })
let hideTimer = null

const open = (pinned) => {
	window.clearTimeout(hideTimer)
	group.value = { owner: self, pinned }
}

const close = () => {
	window.clearTimeout(hideTimer)

	if (isOpen.value) {
		group.value = null
	}
}

// below the button & aligned to its start (like Kirby's dropdowns),
// flipped if there's not enough space below or towards the end
const place = () => {
	const anchor = trigger.value.getBoundingClientRect()
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
		trigger.value?.focus()
	}
}

const listeners = {
	pointerdown: [onPointerdown],
	keydown: [onKeydown],
	scroll: [place, { capture: true, passive: true }],
	resize: [place]
}

const listen = (add) => {
	for (const [event, [listener, options]] of Object.entries(listeners)) {
		window[add ? "addEventListener" : "removeEventListener"](event, listener, options)
	}
}

watch(isOpen, async (value) => {
	listen(value)

	if (value) {
		popover.value.showPopover()
		await nextTick()
		place()
	} else {
		popover.value?.hidePopover()
	}
})

const onEnter = () => {
	// hovering doesn't replace a popover that was opened on purpose
	if (!group.value?.pinned) {
		open(false)
	}
}

const onLeave = () => {
	if (!isPinned.value) {
		window.clearTimeout(hideTimer)
		hideTimer = window.setTimeout(close, HIDE_DELAY)
	}
}

// click, Enter & Space
const onClick = () => (isPinned.value ? close() : open(true))

const onFocusout = (event) => {
	if (event.relatedTarget && !root.value?.contains(event.relatedTarget)) {
		close()
	}
}

// watchers are stopped when unmounting, so the listeners are removed here
onBeforeUnmount(() => {
	close()
	listen(false)
})
</script>

<template>
	<div
		ref="root"
		class="k-seo-popover"
		@mouseenter="onEnter"
		@mouseleave="onLeave"
		@focusout="onFocusout"
	>
		<button
			ref="trigger"
			v-bind="button"
			:aria-expanded="String(isOpen)"
			type="button"
			@click="onClick"
		>
			<slot name="button" />
		</button>
		<div
			ref="popover"
			:style="{ top: `${position.top}px`, left: `${position.left}px` }"
			popover="manual"
			class="k-seo-popover-content"
		>
			<slot :close="close" />
		</div>
	</div>
</template>

<style>
/* same look as Kirby's dropdowns, e.g. the languages dropdown */
.k-seo-popover-content {
	position: fixed;
	inset: auto;
	margin: 0;
	width: max-content;
	min-width: 15rem;
	max-width: min(30rem, calc(100vw - 2rem));
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

.k-dropdown-item.k-seo-popover-item {
	/* only show the outline when navigating with the keyboard */
	&:focus:not(:focus-visible) {
		outline: none;
	}
}

/* the info is aligned to the end, like the codes in Kirby's languages dropdown */
.k-seo-popover-item .k-button-text {
	display: flex;
	flex-grow: 1;
	justify-content: space-between;
	align-items: center;
	gap: var(--spacing-6);
	min-width: 0;
	white-space: nowrap;
}

.k-seo-popover-text {
	overflow: hidden;
	text-overflow: ellipsis;
}

.k-seo-popover-info {
	flex-shrink: 0;
	font-size: var(--text-xs);
	color: var(--color-gray-500);
}
</style>
