import $ from 'jquery'
import moment from 'moment'

/**
 * Convert times to local time
 *
 * @param $times jQuery array of times
 */
export function convert($times) {
	$times.map(function () {
		const $time = $(this)

		const eventTime = moment.unix($time.data('time'))
		const local = eventTime.local()

		// Check if different day
		let format = 'LLL'
		if ($time.data('shown-date')) {
			if ($time.data('shown-date') == local.format('Y-MM-DD')) {
				format = 'LT'
			}
		}

		let formatted = `${local.format(format)} in your time`

		if ($time.data('secondary-time')) {
			formatted = `(${formatted})`
		}

		if ($time.data('local-time') == 'title') {
			$time.attr('title', formatted)
		} else {
			$time.text(formatted)
		}
	})
}