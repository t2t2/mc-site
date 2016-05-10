import $ from 'jquery'

const youtube_re = /youtube\.com\/embed\/(.+)\?/
const twitch_re = /twitch\.tv\/(.+)\/embed/
var old_data;

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
	check(live_config_url, live_check_interval, $config, $members)
}

/**
 * Fetches and displays member livestream data
 *
 * @param url livehub /live/config url
 * @param interval interval between requests (in ms)
 * @param $stream_section JQuery live section element
 * @param $members JQuery array of member elements (if any, when on members page)
 */
function check(url, interval, $stream_section, $members) {
	$.ajax({
		url: url,
		type: "GET",
		dataType: "text",
		crossDomain: true,
		success: data => {
			if (old_data !== data) {
				old_data = data;
				jsonData = $.parseJSON(data)
				dataMap = convertDataToMap(jsonData.streams.data)

				if ($members.length > 0) {
					// Check to see if member page, or members listing
					if ($($members[0]).hasClass('member-live-streams'))  {
						update_member_page(dataMap, $($members[0]))
					} else {
						update_members(dataMap, $members)
					}
				}

				update_live_notification(dataMap, $stream_section)
			}
		},
		complete: () => {
			setTimeout(check,interval,url,interval,$stream_section,$members)
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

		if (slug in streams.members)
		{
			$twitch_link = $member.children(".site-link-twitch")
			if ("twitch" in streams.members[slug]) {
				const stream = streams.members[slug]["twitch"]

				$twitch_link.attr("href", stream.stream_url)
				$twitch_link.attr("title", stream.stream_title)
				$twitch_link.fadeIn().css("display","inline-block");
			} else if ($twitch_link.css('display') !== 'none') {
				$twitch_link.fadeOut()
			}

			$youtube_link = $member.children(".site-link-youtube")
			if ("youtube" in streams.members[slug]) {
				const stream = streams.members[slug]["youtube"]

				$youtube_link.attr("href", stream.stream_url)
				$youtube_link.attr("title", stream.stream_title)
				$youtube_link.fadeIn().css("display","inline-block");
			} else if ($youtube_link.css('display') !== 'none') {
				$youtube_link.fadeOut()
			}

			$member.fadeIn()

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
	const slug = $member.data('member-slug')

	if (slug in streams.members) {
		$twitch_section = $member.children(".member-vid-twitch-live")
		if ("twitch" in streams.members[slug]) {
			const stream = streams.members[slug]["twitch"]
			if ($twitch_section.data("live-stream-id") !== stream.stream_id) {
				$twitch_section.find("iframe").attr("src", stream.video_url)
				$twitch_section.data("live-stream-id", stream.stream_id)
				$twitch_section.find(".important-link").attr("href", stream.stream_url)
			}
			$twitch_section.slideDown()
		} else if ($twitch_section.css('display') !== 'none') {
			$twitch_section.slideUp()
			$twitch_section.data("live-stream-id","")
			$twitch_section.find("iframe").attr("src", "")
		}

		$youtube_section = $member.children(".member-vid-youtube-live")
		if ("youtube" in streams.members[slug]) {
			const stream = streams.members[slug]["youtube"]
			if ($youtube_section.data("live-stream-id") != stream.stream_id) {
				$youtube_section.find("iframe").attr("src", stream.video_url)
				$youtube_section.data("live-stream-id", stream.stream_id)
				$youtube_section.find(".important-link").attr("href", stream.stream_url)
			}
			$youtube_section.slideDown()
		} else if ($youtube_section.css('display') !== 'none') {
			$youtube_section.slideUp()
			$youtube_section.data("live-stream-id","")
			$youtube_section.find("iframe").attr("src", "")
		}
	}
}

/**
 * Updates the livestream notification section with the livestream data
 *
 * @param streams the processed map of the stream data
 * @param $stream_section JQuery live section element
 */
function update_live_notification(streams, $stream_section) {
	// Do work on all existing member elements
	const $member_elements = $('#live-members').children("div")
	$member_elements.map(function() {
		var $member = $(this)
		const slug = $member.data('member-slug')

		if (!(slug in streams.members)) {
			$member.remove()
			delete streams.members[slug]
		} else {
			const member = streams.members[slug]
			var stream_ids = []
			if ("twitch" in member) { stream_ids.push(member["twitch"].stream_id) }
			if ("youtube" in member) { stream_ids.push(member["youtube"].stream_id) }

			if (stream_ids.join(',') !== $member.data('member-stream-ids')) {
				$twitch_link = $member.children(".site-link-twitch")
				if ("twitch" in member) {
					const stream = member["twitch"]
					if ($twitch_link.data("live-stream-id") != stream.stream_id) {
						$twitch_link.data("live-stream-id", stream.stream_id)
						$twitch_link.attr("href", stream.stream_url)
						$twitch_link.attr("title", stream.stream_title)
						$twitch_link.fadeIn().css("display","inline-block");
					}
				} else {
					$twitch_link.fadeOut()
				}
				$youtube_link = $member.children(".site-link-youtube")
				if ("youtube" in member) {
					const stream = member["youtube"]
					if ($youtube_link.data("live-stream-id") != stream.stream_id) {
						$youtube_link.data("live-stream-id", stream.stream_id)
						$youtube_link.attr("href", stream.stream_url)
						$youtube_link.attr("title", stream.stream_title)
						$youtube_link.fadeIn().css("display","inline-block");
					}
				} else {
					$youtube_link.fadeOut()
				}
			}
			$member.data("member-stream-ids", stream_ids.join(','))

			delete streams.members[slug]
		}
	})

	// Add new elements
	if (streams.count > 0) {
		$stream_section.find(".live-notification").html(streams.count + (streams.count > 1 ? " members": " member") + " are streaming. Click to view")
		$stream_section.slideDown()
		var member_elements = ""
		for (var member_slug in streams.members) {
			if (streams.members.hasOwnProperty(member_slug)) {
				const member = streams.members[member_slug]
				var $member = $("<div class='member-live' data-member-slug='" + member_slug + "'>" + member.name + " <a class='site-link site-link-twitch' href='#' title=''></a><a class='site-link site-link-youtube' href='#' title=''></a></div>")
				
				var stream_links = ""
				var stream_ids = []
				$twitch_link = $member.children(".site-link-twitch")
				if ("twitch" in member) {
					const stream = member["twitch"]
					$twitch_link.attr("data-live-stream-id", stream.stream_id)
					$twitch_link.attr("href", stream.stream_url)
					$twitch_link.attr("title", stream.stream_title)
					$twitch_link.fadeIn().css("display","inline-block");
					stream_ids.push(stream.stream_id)
				}
				$youtube_link = $member.children(".site-link-youtube")
				if ("youtube" in member) {
					const stream = member["youtube"]
					$youtube_link.attr("data-live-stream-id", stream.stream_id)
					$youtube_link.attr("href", stream.stream_url)
					$youtube_link.attr("title", stream.stream_title)
					$youtube_link.fadeIn().css("display","inline-block");
					stream_ids.push(stream.stream_id)
				}
				$member.attr("data-member-stream-ids", stream_ids.join(","))
				$("#live-members").append($member)
			}
		}

		// Sort elements
		$("#live-members div").sort(function(a,b) {
			return ($(a).data("member-slug") < $(b).data("member-slug") ? -1: ($(a).data("member-slug") > $(b).data("member-slug")? 1 : 0))
		}).appendTo("#live-members")
	} else {
		$stream_section.slideUp()
	}
}



/**
 * Convert the data returned from livehub into a more usable map for our purpose
 *
 * @param data the response data from livehub (with info about streams)
 * @param $members JQuery array of member elements (if any, when on members page)
 */
function convertDataToMap(data) {
	var map = {"members":{},"count":0}
	var count = 0;
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
				updated_item["stream_id"] = youtube_match[1]
			} else if (twitch_match) {
				updated_item["stream_type"] = "twitch"
				updated_item["stream_url"] = "https://twitch.tv/" + twitch_match[1]
				updated_item["video_url"] = "https://twitch.tv/" + twitch_match[1] + "/embed"
				updated_item["stream_id"] = twitch_match[1]
			}

			if (("stream_type" in updated_item) && ("stream_url" in updated_item) && (updated_item.stream_url !== "")) {
				if (updated_item.slug in map.members) {
					map.members[updated_item.slug][updated_item.stream_type] = updated_item
				} else {
					++count;
					map.members[updated_item.slug] = {}
					map.members[updated_item.slug][updated_item.stream_type] = updated_item
					map.members[updated_item.slug]["name"] = updated_item.name
				}
			}
		}
	})
	map.count = count;

	return map
}