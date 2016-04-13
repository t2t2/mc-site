var gulp = require('gulp');

gulp.task('watch', function () {
	var config = require('../config'),
		extensionsGlob = require('../lib/extensions-glob'),
		gutil = require('gulp-util'),
		path = require('path'),
		watch = require('gulp-watch')

	var tasks = ['assets', 'css', 'js']

	tasks.forEach(function (taskName) {
		var task = config.tasks[taskName]
		if (task) {
			var glob = [
				path.join(config.root.src, '**/*' + ('extensions' in task ? extensionsGlob(task.extensions) : '')),
			]

			if ('ignoreExtensions' in task) {
				glob.push('!' + path.join(config.root.src, '**/*' + extensionsGlob(task.ignoreExtensions)))
			}

			watch(glob, config.watch, function () {
				gutil.log('Watch: Running', taskName)
				require('./' + taskName)()
			})
		}
	})
});