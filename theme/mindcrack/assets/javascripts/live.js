import $ from 'jquery'

const youtube_re = /youtube\.com\/embed\/(.+)\?/
const twitch_re = /twitch\.tv\/(.+)\/embed/

/*	
	Each member on livehub should have a corresponding show with the same slug as the member on this site,
	as well as the show name being the same as the members name here.
*/

/**
 * Initializes the fetching and display of member livestream data
 *
 * @param $config jQuery config object (containing the needed data attributes)
 * @param $members JQuery array of member elements (if any, when on members page)
 */
export function startLive($config, $members) {
	$config = $config.first()
	const live_config_url = $config.data("live-config")
	const live_check_interval = parseInt($config.data("live-check-interval"))
	check(live_config_url, live_check_interval, $members)
}

/**
 * Fetches and displays member livestream data
 *
 * @param url livehub /live/config url
 * @param interval interval between requests (in ms)
 * @param $members JQuery array of member elements (if any, when on members page)
 */
function check(url, interval, $members) {
	$.ajax({
		url: url,
		type: "GET",
		dataType: "json",
		crossDomain: true,
		success: data => {
			dataMap = convertDataToMap(data.streams.data)

			if ($members.length > 0) {
				// Check to see if member page, or members listing
				if ($($members[0]).hasClass('member-live-streams'))  {
					update_member_page(dataMap, $members[0])
				} else {
					update_members(dataMap, $members)
				}
			}

			update_live_notification(dataMap)
		},
		complete: () => {
			setTimeout(check,interval,url,interval, $members)
		}
	})
}

/**
 * Updates the member elements with the livestream data
 *
 * @param streams the processed map of the stream data
 * @param $members JQuery array of member elements (if any, when on members page)
 */
function update_members(streams, $members) {
	$members.map(function () {
		var $member = $(this)
		const slug = $member.data('member-slug')

		if (slug in streams)
		{
			$twitch_link = $member.children(".site-link-twitch")
			if ("twitch" in streams[slug]) {
				const stream = streams[slug]["twitch"]

				$twitch_link.attr("href", stream.stream_url)
				$twitch_link.attr("title", stream.stream_title)
				$twitch_link.fadeIn()
			} else if ($twitch_link.css('display') !== 'none') {
				$twitch_link.fadeOut()
			}

			$youtube_link = $member.children(".site-link-youtube")
			if ("youtube" in streams[slug]) {
				const stream = streams[slug]["youtube"]

				$youtube_link.attr("href", stream.stream_url)
				$youtube_link.attr("title", stream.stream_title)
				$youtube_link.fadeIn()
			} else if ($youtube_link.css('display') !== 'none') {
				$youtube_link.fadeOut()
			}

			if ($member.css('display') === 'none') {
				$member.fadeIn()
			}
		} else if ($member.css('display') !== 'none') {
			$member.fadeOut()
		}
	})
}

/**
 * Updates the member page with the livestream data
 *
 * @param streams the processed map of the stream data
 * @param $members JQuery array of member elements (if any, when on members page)
 */
function update_member_page(streams, $member) {
	// TODO UPDATE MEMBER PAGE WITH SOME DISPLAY OF STREAM, POTENTIALLY EMBEDDING THE STREAM
}

/**
 * Updates the livestream notification section with the livestream data
 *
 * @param streams the processed map of the stream data
 */
function update_live_notification(streams) {
	// TO DO UPDATE LIVESTREAM NOTIFICATION / DISPLAY ON EVERY PAGE
}



/**
 * Convert the data returned from livehub into a more usable map for our purpose
 *
 * @param data the response data from livehub (with info about streams)
 * @param $members JQuery array of member elements (if any, when on members page)
 */
function convertDataToMap(data) {
	var map = {}

	data.forEach(function(item, index) {
		if (item.state === "live") {
			var updated_item = {}
			
			updated_item["name"] = item.show.data.name
			updated_item["slug"] = item.show.data.slug
			updated_item["stream_title"] = item.title
			updated_item["video_url"] = item.video_url

			const youtube_match = updated_item.video_url.match(youtube_re)
			const twitch_match = updated_item.video_url.match(twitch_re)

			if (youtube_match) {
				updated_item["stream_type"] = "youtube"
				updated_item["stream_url"] = "https://youtu.be/" + youtube_match[1]
				updated_item["video_url"] = "https://www.youtube.com/embed/" + youtube_match[1] + "?autohide=1"
			} else if (twitch_match) {
				updated_item["stream_type"] = "twitch"
				updated_item["stream_url"] = "https://twitch.tv/" + twitch_match[1]
			}

			if (("stream_type" in updated_item) && ("stream_url" in updated_item) && (updated_item.stream_url !== "")) {
				if (updated_item.slug in map) {
					map[updated_item.slug][updated_item.stream_type] = updated_item
				} else {
					map[updated_item.slug] = {}
					map[updated_item.slug][updated_item.stream_type] = updated_item
				}
			}
		}
	})

	return map
}