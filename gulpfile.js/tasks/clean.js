var gulp = require('gulp');

module.exports = function (cb) {
	var del = require('del');
	var config = require('../config');

	del([
		config.root.dest
	]).then(function() {
		cb()
	});
}
gulp.task('clean', module.exports);
