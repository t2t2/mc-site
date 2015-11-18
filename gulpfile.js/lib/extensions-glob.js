/**
 * Generate a glob string that targets given extensions
 *
 * @param extensions
 * @returns {string}
 */
module.exports = function (extensions) {
	if (!extensions) {
		return ''
	} else if (extensions.length == 1) {
		return extensions[0]
	} else {
		return '.{' + extensions.join(',') + '}'
	}
}