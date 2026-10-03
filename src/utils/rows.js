// Include the parent title to distinguish images with the same filename
export const rowName = (row) =>
	row.title?.info ? `${row.title.text} (${row.title.info})` : row.title?.text
