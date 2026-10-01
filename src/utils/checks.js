/**
 * Severity of the issue types: `negative` needs fixing, `notice` is acceptable
 */
export const SEVERITY = {
	descriptionMissing: "negative",
	descriptionDuplicate: "negative",
	titleDuplicate: "negative",
	descriptionFallback: "notice",
	titleLength: "notice",
	descriptionLength: "notice"
}

/**
 * Each severity has its own icon, so it's not only told apart by color
 */
export const SEVERITY_ICONS = {
	ok: "check",
	notice: "info",
	negative: "alert"
}
