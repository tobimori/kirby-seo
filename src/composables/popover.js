import { inject, provide, ref } from "kirbyuse"

const KEY = "k-seo-popover"

/**
 * The open popover of a group (e.g. the cells of a table), so only one of them is open at a time:
 * `{ owner, pinned }`, where pinned popovers have been opened on purpose (click, keyboard)
 * and aren't replaced by hovering others
 */
export function providePopoverGroup() {
	provide(KEY, ref(null))
}

/**
 * Popovers outside of a group are independent of each other
 */
export function usePopoverGroup() {
	return inject(KEY, () => ref(null), true)
}
