import $ from "jquery"

// todo: Any extra crap we can remove from here? (or, is anything from bootstrap js used?)
import "bootstrap/dist/js/umd/alert"
import "bootstrap/dist/js/umd/button"
import "bootstrap/dist/js/umd/carousel"
import "bootstrap/dist/js/umd/collapse"
import "bootstrap/dist/js/umd/dropdown"
import "bootstrap/dist/js/umd/modal"
import "bootstrap/dist/js/umd/scrollspy"
import "bootstrap/dist/js/umd/tab"
// import "bootstrap/dist/js/umd/tooltip" // Requires tether
// import "bootstrap/dist/js/umd/popover" // Requires tooltip

import {Retina} from "retina.js"

// https://github.com/t2t2/mc-site/issues/12
// Check if retina, simplified because http://caniuse.com/#feat=devicepixelratio
if (window.devicePixelRatio > 1) {
	Retina.init(window)
}


// Convert times to local time
var $times = $('[data-local-time]')
if ($times.length) {
	System.import("./localTimes").then(module => {
		module.convert($times)
	}).catch(err => {
		console.error('Loading local times failed', err)
	})
}