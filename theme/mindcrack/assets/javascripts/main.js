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

import "retina.js"

// Testing code splitting
var times = $('[data-local-time]')
if (times.length) {
	console.log('Loading convertor')

	System.import("./localTimes").then(module => {
		console.log('Module loaded', module)
		module.convert()
	}).catch(err => {
		console.error('Loading failed', err)
	})
}