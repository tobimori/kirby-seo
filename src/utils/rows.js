/**
 * Name of a row for screen readers, e.g. of its checkbox: the title & the page it belongs to,
 * as images with the same file name might be stored on multiple pages
 */
export const rowName = (row) =>
	row.title?.info ? `${row.title.text} (${row.title.info})` : row.title?.text
