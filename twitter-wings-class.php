<?php
if (!function_exists('is_admin')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

if (!class_exists('TwitterWingsStart')) :

class TwitterWingsStart {
			
	/**
	 * 
	 * Create array from Twitter API response
	 * 
	 */	
	public function tw_getApiData($options, $inst=0) {
		
		// Explode users, makesure number is a number
		$options['tw_usernames'] = explode(',',$options['tw_usernames']);
		$options['tw_number'] = (is_numeric($options['tw_number'])) ? $options['tw_number'] : 15;
		$sts = array();
		
		if ($options['consumerKey'] && $options['consumerSecret'] && $options['accessToken'] && $options['accessTokenSecret']) {
			$oauth = true;
			// build query array arg
			$oath_args = array('include_entities' => 1);
			$oath_args['exclude_replies'] = (!$options['tw_reply']) ? 1 : '';
			$oath_args['include_rts'] = ($options['tw_retweet']) ? 1 : '';		
			$oath_args['count'] = ($options['tw_number'] > 20) ? $options['tw_number'] : 20;
		} else {
			$oauth = false;
			// Build query string
			$api_query = '&include_entities=1';
			$api_query .= (!$options['tw_reply']) ? '&exclude_replies=1' : '';
			$api_query .= ($options['tw_retweet']) ? '&include_rts=1' : '';
			$api_query .= ($options['tw_number'] > 20) ? "&count={$options['tw_number']}" : '';
		}
		
		foreach ($options['tw_usernames'] as $name) {
			
			$name = trim($name);
			if (!$name) continue;
			
			if ($oauth) {
			
				$oath_args['screen_name'] = $name;
				
				require_once('twitteroauth/twitteroauth.php');
				$twitteroauth = new TwitterOAuth($options['consumerKey'], $options['consumerSecret'], $options['accessToken'], $options['accessTokenSecret']);
				$tw_object = $twitteroauth->get('statuses/user_timeline', $oath_args);
				
			} else {
	
				$url = 'http://api.twitter.com/1/statuses/user_timeline.json/?screen_name=' . $name . $api_query;
			
				$json = @file_get_contents($url);
				if ($json === false) continue;
				
				$tw_object = json_decode($json);
			}
			
			if (!$tw_object) continue;
														
			foreach ($tw_object as $x) {
																
				// CONTENT (IF IT'S A RETWEET GET THE ORIGINAL)
				if ($x->retweeted_status) {
					$tmp['text'] = 'RT @' . (string)$x->retweeted_status->user->screen_name . ': ' . (string)$x->retweeted_status->text;
				} else {
					$tmp['text'] = (string)$x->text;
				}
				
				// MENTIONS (IF IT'S A RETWEET GET THE ORIGINAL)
				$tmp['mentions'] = '';
				if ($options['tw_retweet'] && $x->retweeted_status->entities->user_mentions) {
					foreach ($x->retweeted_status->entities->user_mentions as $mention) {
						$m['screen_name'] = (string)$mention->screen_name;
						$tmp['mentions'][] = $m;  // ah sss push it
					}
					// add the user you are retweeting to the array!
					$m['screen_name'] = (string)$x->retweeted_status->user->screen_name;
					$tmp['mentions'][] = $m;  // ah sss push it
				
				} elseif ($x->entities->user_mentions) {
					foreach ($x->entities->user_mentions as $mention) {
						$m['screen_name'] = (string)$mention->screen_name;
						$tmp['mentions'][] = $m; // ah sss push it
					}
				}
							
				// URLS (IF IT'S A RETWEET GET THE ORIGINAL)
				$tmp['urls'] = '';
				if ($options['tw_retweet'] && $x->retweeted_status->entities->urls) {
					
					
					foreach ($x->retweeted_status->entities->urls as $url) { 
						$l['url'] = (string)$url->url;
						$l['display_url'] = (string)$url->display_url;
						$tmp['urls'][] = $l; // push it real good
					}
				} elseif ($x->entities->urls) {
					
					foreach ($x->entities->urls as $url) { 
						$l['url'] = (string)$url->url;
						$l['display_url'] = (string)$url->display_url;
						$tmp['urls'][] = $l; // push it real good
					}
				}
				
				// HASHTAGS (IF IT'S A RETWEET GET THE ORIGINAL)
				$tmp['hashtags'] = '';
				if ($options['tw_retweet'] && $x->retweeted_status->entities->hashtags) {
					foreach ($x->retweeted_status->entities->hashtags as $hashtag) {
						$h['hashtag'] = (string)$hashtag->text;
						$tmp['hashtags'][] = $h; // ah sss push it
					}
				} elseif ($x->entities->hashtags) {
					foreach ($x->entities->hashtags as $hashtag) {
						$h['hashtag'] = (string)$hashtag->text;
						$tmp['hashtags'][] = $h; // ah sss push it
					}
				}
				
				$tmp['time'] = (string)$x->created_at;
				$tmp['timestamp'] = (string)strtotime($x->created_at);
				$tmp['name'] = (string)$x->user->name;
				$tmp['username'] = (string)$x->user->screen_name;
				$tmp['avatar']  = (string)$x->user->profile_image_url;
				$tmp['permalink'] = (string)'http://twitter.com/' . $x->user->screen_name . '/status/' . $x->id;
				$sts[] = $tmp;
			} 
		
		}
		
		/* sort statuses array by timestamp */
		$tmp = $sts;
		foreach ($tmp as $key=>$row) {
			$text[$key] = $row['timestamp'];
		}
	
		if ($text && $tmp) {
			array_multisort($text,SORT_DESC,$tmp);
			$sts = $tmp;
			/* end sorting */
			/* put data in transient for latter use : cache */	
	    	$this->tw_save_to_cache($sts, $options, $inst);
		}
				
		return $sts;	
	}
	
	private function tw_save_to_cache($sts, $options, $inst=0) {
		if (!empty($sts)) {
		
			if ($options['tw_cache'] && is_numeric((int)$options['tw_cache_time'])) {
				$seconds = 60 * (int)$options['tw_cache_time'];
			} else {
				$seconds = 60 * 60;
			}
			set_transient("tw_tweet_cache_$inst", $sts, $seconds);
			update_option("tw_tweet_option_cache_$inst", $sts);
		}
	}
	
} // end class 
endif;