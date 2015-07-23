var gutil = require('gulp-util');

var config = {};

config.publicDirectory = "./public";
config.themesDirectory = "./theme";
config.publicThemes = config.publicDirectory + "/theme";
config.sourceThemes = config.themesDirectory + "/*/assets";

config.watchOptions = {};

/*
 * In case default watch isn't working (eg. windows host of linux vagrant box),
 * use --polling to force polling
 */
if(gutil.env.polling) {
	config.watchOptions.usePolling = true;
	config.watchOptions.interval = 1000;
}

module.exports = config;
