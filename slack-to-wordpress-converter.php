<?php
/**
 * Converting Slack export to XML file, ready for WordPress import.
 * Needs php_mbstring extension.
 *
 * With $_GET['start-date'] and $_GET['end-date'] you can set a range of days for the xml
 * or, on the command line: php slack-to-wordpress-converter.php="01.01.2016" end-date="05.01.2016".
 * You can also set either start-date or end-date.
 *
 * @link https://gist.github.com/levelsio/122907e95956602e5c09
 */

/**
 * Set high memory limit.
 */
ini_set( 'memory_limit', '1024M' );

/**
 * Set timezone to Berlin.
 */
date_default_timezone_set( 'Europe/Berlin' );

/**
 * $argv contains script arguments if the script was run from the command line.
 */
$args = $argv;

/**
 * Get start date.
 */
$start_date_arg = preg_grep( '/start-date=/', $args );

/**
 * Get end date.
 */
$end_date_arg         = preg_grep( '/end-date=/', $args );
$start_date_timestamp = '';

/**
 * Check if we have a start date.
 */
if ( ! empty( $start_date_arg ) ) {
	/**
	 * Get the start date and save the timestamp.
	 */
	$start_date_arg       = end( $start_date_arg );
	$start_date           = preg_replace( '/start-date=/', '', $start_date_arg );
	$start_date_timestamp = strtotime( $start_date );
} elseif ( isset( $_GET['start-date'] ) ) {
	/**
	 * Save the start date from the URL get param.
	 */
	$start_date_timestamp = strtotime( $_GET['start-date'] );
}
$end_date_timestamp = '';

/**
 * Check if we have an end date.
 */
if ( ! empty( $end_date_arg ) ) {
	/**
	 * Get the end date and save timestamp.
	 */
	$end_date_arg       = end( $end_date_arg );
	$end_date           = preg_replace( '/end-date=/', '', $end_date_arg );
	$end_date_timestamp = strtotime( $end_date );
} elseif ( isset( $_GET['end-date'] ) ) {
	/**
	 * Save end date from URL param.
	 */
	$end_date_timestamp = strtotime( $_GET['end-date'] );
}

/**
 * Load users from users.json.
 */
$users = json_decode( file_get_contents( __DIR__ . '/' . 'users.json' ), true );

/**
 * Build array with user ID as key and user name as value.
 */
$users_by_id = [];
foreach ( $users as $user ) {
	$users_by_id[ $user['id'] ] = $user;
}

/**
 * Load channels from channels.json.
 */
$channels = json_decode( file_get_contents( __DIR__ . '/' . 'channels.json' ), true );

/**
 * Build channels array with channel ID as key and
 * channel name as value.
 */
$channelsById = array();
foreach ( $channels as $channel ) {
	$channelsById[ $channel['id'] ] = $channel;
}

/**
 * Get all directories and files from current directory.
 */
$files            = scandir( __DIR__ );
$xml_export_array = [];

/**
 * Loop them.
 */
foreach ( $files as $channel ) {
	/**
	 * Check if we have the pseudo directories for current or parant directory.
	 */
	if ( '.' === $channel || '..' === $channel || '.idea' === $channel ) {
		continue;
	}

	/**
	 * Check if we have a directory.
	 */
	if ( is_dir( $channel ) ) {
		/**
		 * Get the json files with the chats from the dates (format: 2015-02-06.json).
		 */
		$dates = scandir( __DIR__ . '/' . $channel );

		/**
		 * Loop them.
		 */
		foreach ( $dates as $date ) {
			/**
			 * Check if we have no directory.
			 */
			if ( ! is_dir( $date ) ) {
				/**
				 * Get the messages from the current date file.
				 */
				$messages = json_decode( file_get_contents( __DIR__ . '/' . $channel . '/' . $date ), true );

				/**
				 * Date pattern.
				 */
				$pattern = '/([0-9\-]+)/';

				/**
				 * Match the date in the file name.
				 */
				preg_match( $pattern, $date, $match );

				/**
				 * Check for a match.
				 */
				if ( ! empty( $match ) ) {
					/**
					 * Save the date.
					 */
					$date = $match[0];

					/**
					 * Create timestamp.
					 */
					$date_timestamp = strtotime( $date );

					/**
					 * Create DateTime object of the file date.
					 */
					$file_date_obj = new DateTime( date( 'Y-m-d', $date_timestamp ) );

					/**
					 * Check if we have a start date from command line or get param.
					 */
					if ( '' !== $start_date_timestamp ) {
						/**
						 * Because of time zones, we check if the current file is one day earlier
						 * than the start date.
						 *
						 * @link https://stackoverflow.com/a/12855717
						 */

						/**
						 * Get previous date to start date.
						 */
						$previous_date_to_start = ( new DateTime( "$start_date-1day" ) )->format( 'Y-m-d' );

						/**
						 * Generate timestamp.
						 */
						$previous_date_to_start_timestamp = strtotime( $previous_date_to_start );

						/**
						 * Check if the directory date is smaller than the previous date
						 * of the start date.
						 */
						if ( $date_timestamp < $previous_date_to_start_timestamp ) {
							continue;
						}
					}

					/**
					 * Check if we have an end date.
					 */
					if ( '' !== $end_date_timestamp ) {
						/**
						 * Because of time zones, we check if the current file is one day after
						 * the end date.
						 *
						 * @link https://stackoverflow.com/a/12855717
						 */

						$next_date_to_end = ( new DateTime( "$end_date+1day" ) )->format( 'Y-m-d' );

						/**
						 * Generate timestamp.
						 */
						$next_date_to_end_timestamp = strtotime( $next_date_to_end );

						/**
						 * Check if the directory date is greater than the next date
						 * of the end date.
						 */
						if ( $date_timestamp > $next_date_to_end_timestamp ) {
							continue;
						}
					}
				} // End if().

				/**
				 * Check if messages are empty.
				 */
				if ( empty( $messages ) ) {
					continue;
				}

				/**
				 * Loop the messages.
				 */
				foreach ( $messages as $message ) {
					if ( empty( $message ) ) {
						continue;
					}
					if ( empty( $message['text'] ) ) {
						continue;
					}

					/**
					 * Save message subtype.
					 */
					$message_subtype = '';
					if ( isset( $message['subtype'] ) ) {
						$message_subtype = $message['subtype'];
					}

					/**
					 * Do not include channel join and leave messages.
					 */
					if ( 'channel_join' === $message_subtype || 'channel_leave' === $message_subtype ) {
						continue;
					}

					/**
					 * Check if we have a file comment. In this case, we set
					 * a few message array keys.
					 */
					if ( 'file_comment' === $message_subtype ) {
						/**
						 * Overwrite $message['text'] with the comment text ('text' includes
						 * by default something like »user commented on …«).
						 */
						$message['text'] = $message['comment']['comment'];

						/**
						 * Set the user.
						 */
						$message['user'] = $message['comment']['user'];
					}

					/**
					 * Insert user names instead of user IDs.
					 */
					if ( stripos( $message['text'], '<@' ) !== false ) {
						$users_in_message = explode( '<@', $message['text'] );
						foreach ( $users_in_message as $user_in_message ) {
							$array                   = explode( '>', $user_in_message );
							$user_handle_in_brackets = $array[0];
							$array                   = explode( '|', $array[0] );
							$user_in_message         = $array[0];
							if ( isset( $array[1] ) ) {
								$username = $array[1];
							} else {
								$username = false;
							}
							if ( empty( $username ) ) {
								if ( isset( $users_by_id[ $user_in_message ] ) ) {
									$username = $users_by_id[ $user_in_message ]['name'];
								} else {
									$username = '';
								}

							}
							$message['text'] = str_replace( '<@' . $user_handle_in_brackets . '>', '@' . $username, $message['text'] );
						}
					}

					/**
					 * Insert channel names instead of IDs.
					 */
					if ( stripos( $message['text'], '<#' ) !== false ) {
						$channels_in_message = explode( '<#', $message['text'] );
						foreach ( $channels_in_message as $channel_in_message ) {
							$array                      = explode( '>', $channel_in_message );
							$channel_handle_in_brackets = $array[0];
							$array                      = explode( '|', $array[0] );
							$channel_in_message         = $array[0];
							if ( ! empty ( $array[1] ) ) {
								$channel_name_in_message = $array[1];
							}
							if ( empty( $channel_name_in_message ) ) {
								if ( ! empty( $channelsById[ $channel_in_message ]['name'] ) ) {
									$channel_name_in_message = $channelsById[ $channel_in_message ]['name'];
								}
							}

							if ( empty( $channel_name_in_message ) ) {
								$channel_name_in_message = '';
							}
							$message['text'] = str_replace( '<#' . $channel_handle_in_brackets . '>', '#' . $channel_name_in_message, $message['text'] );
						}
					}

					/**
					 * Change <http://url> into link
					 */
					if ( stripos( $message['text'], '<http' ) !== false ) {
						$links_in_message = explode( '<http', $message['text'] );
						foreach ( $links_in_message as $linkInMessage ) {
							$array                  = explode( '>', $linkInMessage );
							$link_total_in_brackets = $array[0];
							if ( stripos( $link_total_in_brackets, '|' ) ) {
								$array           = explode( '|', $array[0] );
								$linkInMessage   = $array[0];
								$message['text'] = str_replace( '<http' . $link_total_in_brackets . '>', '<http' . $linkInMessage . '>', $message['text'] );
							}
						}
					}

					/**
					 * Change @here et cetera so that it doesn’t get converted to a mailto:link from the markdown parser
					 */
					if ( stripos( $message['text'], '<!' ) !== false ) {
						$mentions_in_message = explode( '<!', $message['text'] );
						foreach ( $mentions_in_message as $mention_in_message ) {
							$array               = explode( '>', $mention_in_message );
							$mention_in_brackets = $array[0];
							/**
							 * Check if $mention_in_brackets contains something with >.
							 */
							if ( '' !== $mention_in_brackets ) {
								/**
								 * Check if we have a mention like that: <!here|@here>. This is not always the
								 * case — sometimes it is only <!here>.
								 */
								if ( stripos( $mention_in_brackets, '|' ) ) {
									$array              = explode( '|', $array[0] );
									$mention_in_message = "@$array[0]";
									$message['text']    = str_replace( '<!' . $mention_in_brackets . '>', $mention_in_message, $message['text'] );
								} else {
									$mention_in_message = "@$mention_in_brackets";
									$message['text']    = str_replace( '<!' . $mention_in_brackets . '>', $mention_in_message, $message['text'] );
								}
							}
						} // End foreach().
					} // End if().
					$array                       = explode( '.', $message['ts'] );
					$message_timestamp           = $message['ts'];
					$message_date                = date( 'd.m.Y H:i', $message_timestamp );
					$message_date_english_format = date( 'Y-m-d', $message_timestamp );
					/**
					 * Now we need to check, if a message is not in the time range from start and end date.
					 */

					/**
					 * Check if we have a start date from command line or get param.
					 */
					if ( '' !== $start_date_timestamp ) {
						/**
						 * Check if the start date time is later than the message date.
						 */
						if ( $start_date_timestamp > strtotime( $message_date_english_format ) ) {
							continue;
						}
					}

					/**
					 * Check if we have an end date.
					 */
					if ( '' !== $end_date_timestamp ) {
						/**
						 * Check if the end date time is before the message date.
						 */
						if ( $end_date_timestamp < strtotime( $message_date_english_format ) ) {
							continue;
						}
					}

					/**
					 * Create empty $gravatar and $username variables
					 * for the case of no user.
					 */
					$gravatar = '';
					$username = '';

					/**
					 * Check if we have a $message['user'].
					 */
					if ( isset( $message['user'] ) ) {
						if ( isset( $users_by_id[ $message['user'] ] ) ) {
							/**
							 * Get the user gravatar.
							 */
							$gravatar = $users_by_id[ $message['user'] ]['profile']['image_72'];

							/**
							 * Get the username.
							 */
							$username = $users_by_id[ $message['user'] ]['name'];
						} else {
							/**
							 * Set username.
							 */
							$username = $message['user'];
						}
					} elseif ( isset( $message['username'] ) ) {
						/**
						 * Set username
						 */
						$username = $message['username'];

						/**
						 * Check for icon.
						 */
						if ( isset( $message['icons'] ) ) {
							$gravatar = end( $message['icons'] );
						}
					} // End if().

					/**
					 * Check if we have a gravatar URL.
					 */
					if ( '' !== $gravatar ) {
						$gravatar = "<img src='$gravatar'>";
					}

					/**
					 * Get the message text.
					 */
					$message_text = $message['text'];

					/**
					 * Replace &gt; on line start with > so that the markdown parser
					 * creates a blockquote
					 */
					$pattern = '/^&gt;/m';
					preg_match_all( $pattern, $message_text, $match );
					if ( ! empty( $match ) ) {
						$message_text = preg_replace( $pattern, '>', $message_text );
					}

					/**
					 * Input line break after > so that this:
					 *
					 * > Test
					 * Reaction
					 *
					 * is not parsed as one blockquote but only > Test
					 */
					$pattern = '/(^>.*(\n){1})^[^>]/m';
					preg_match_all( $pattern, $message_text, $matches );
					if ( ! empty( $matches ) ) {
						foreach ( $matches as $key => $match ) {
							if ( 1 === $key && ! empty( $match ) ) {
								foreach ( $match as $match_string ) {
									$message_text = str_replace( $match_string, $match_string . "\n", $message_text );
								}
							}
						}
					}

					/**
					 * Parse markdown.
					 */
					$parsedown = new Parsedown();
					$parsedown->setBreaksEnabled( true );
					$message_text = $parsedown->text( $message_text );
					/**
					 * EMOJI SHORT NAME’S TO HTML HEX CODE
					 *
					 * @source https://github.com/davidsword/emoji-shortname-to-hex
					 */
					$emoji_unicode = [
						'copyright'                                              => '&#x00A9;;',
						'registered'                                             => '&#x00AE;;',
						'bangbang'                                               => '&#x203C;;',
						'interrobang'                                            => '&#x2049;;',
						'tm'                                                     => '&#x2122;;',
						'information_source'                                     => '&#x2139;;',
						'left_right_arrow'                                       => '&#x2194;;',
						'arrow_up_down'                                          => '&#x2195;;',
						'arrow_upper_left'                                       => '&#x2196;;',
						'arrow_upper_right'                                      => '&#x2197;',
						'arrow_lower_right'                                      => '&#x2198;',
						'arrow_lower_left'                                       => '&#x2199;',
						'leftwards_arrow_with_hook'                              => '&#x21A9;',
						'arrow_right_hook'                                       => '&#x21AA;',
						'watch'                                                  => '&#x231A;',
						'hourglass'                                              => '&#x231B;',
						'keyboard'                                               => '&#x2328;',
						'eject'                                                  => '&#x23CF;',
						'fast_forward'                                           => '&#x23E9;',
						'rewind'                                                 => '&#x23EA;',
						'arrow_double_up'                                        => '&#x23EB;',
						'arrow_double_down'                                      => '&#x23EC;',
						'black_right_pointing_double_triangle_with_vertical_bar' => '&#x23ED;',
						'black_left_pointing_double_triangle_with_vertical_bar'  => '&#x23EE;',
						'black_right_pointing_triangle_with_double_vertical_bar' => '&#x23EF;',
						'alarm_clock'                                            => '&#x23F0;',
						'stopwatch'                                              => '&#x23F1;',
						'timer_clock'                                            => '&#x23F2;',
						'hourglass_flowing_sand'                                 => '&#x23F3;',
						'double_vertical_bar'                                    => '&#x23F8;',
						'black_square_for_stop'                                  => '&#x23F9;',
						'black_circle_for_record'                                => '&#x23FA;',
						'm'                                                      => '&#x24C2;',
						'black_small_square'                                     => '&#x25AA;',
						'white_small_square'                                     => '&#x25AB;',
						'arrow_forward'                                          => '&#x25B6;',
						'arrow_backward'                                         => '&#x25C0;',
						'white_medium_square'                                    => '&#x25FB;',
						'black_medium_square'                                    => '&#x25FC;',
						'white_medium_small_square'                              => '&#x25FD;',
						'black_medium_small_square'                              => '&#x25FE;',
						'sunny'                                                  => '&#x2600;',
						'cloud'                                                  => '&#x2601;',
						'umbrella'                                               => '&#x2602;',
						'snowman'                                                => '&#x2603;',
						'comet'                                                  => '&#x2604;',
						'phone'                                                  => '&#x260E;',
						'telephone'                                              => '&#x260E;',
						'ballot_box_with_check'                                  => '&#x2611;',
						'umbrella_with_rain_drops'                               => '&#x2614;',
						'coffee'                                                 => '&#x2615;',
						'shamrock'                                               => '&#x2618;',
						'point_up'                                               => '&#x261D;',
						'skull_and_crossbones'                                   => '&#x2620;',
						'radioactive_sign'                                       => '&#x2622;',
						'biohazard_sign'                                         => '&#x2623;',
						'orthodox_cross'                                         => '&#x2626;',
						'star_and_crescent'                                      => '&#x262A;',
						'peace_symbol'                                           => '&#x262E;',
						'yin_yang'                                               => '&#x262F;',
						'wheel_of_dharma'                                        => '&#x2638;',
						'white_frowning_face'                                    => '&#x2639;',
						'relaxed'                                                => '&#x263A;',
						'aries'                                                  => '&#x2648;',
						'taurus'                                                 => '&#x2649;',
						'gemini'                                                 => '&#x264A;',
						'cancer'                                                 => '&#x264B;',
						'leo'                                                    => '&#x264C;',
						'virgo'                                                  => '&#x264D;',
						'libra'                                                  => '&#x264E;',
						'scorpius'                                               => '&#x264F;',
						'sagittarius'                                            => '&#x2650;',
						'capricorn'                                              => '&#x2651;',
						'aquarius'                                               => '&#x2652;',
						'pisces'                                                 => '&#x2653;',
						'spades'                                                 => '&#x2660;',
						'clubs'                                                  => '&#x2663;',
						'hearts'                                                 => '&#x2665;',
						'diamonds'                                               => '&#x2666;',
						'hotsprings'                                             => '&#x2668;',
						'recycle'                                                => '&#x267B;',
						'wheelchair'                                             => '&#x267F;',
						'hammer_and_pick'                                        => '&#x2692;',
						'anchor'                                                 => '&#x2693;',
						'crossed_swords'                                         => '&#x2694;',
						'scales'                                                 => '&#x2696;',
						'alembic'                                                => '&#x2697;',
						'gear'                                                   => '&#x2699;',
						'atom_symbol'                                            => '&#x269B;',
						'fleur_de_lis'                                           => '&#x269C;',
						'warning'                                                => '&#x26A0;',
						'zap'                                                    => '&#x26A1;',
						'white_circle'                                           => '&#x26AA;',
						'black_circle'                                           => '&#x26AB;',
						'coffin'                                                 => '&#x26B0;',
						'funeral_urn'                                            => '&#x26B1;',
						'soccer'                                                 => '&#x26BD;',
						'baseball'                                               => '&#x26BE;',
						'snowman_without_snow'                                   => '&#x26C4;',
						'partly_sunny'                                           => '&#x26C5;',
						'thunder_cloud_and_rain'                                 => '&#x26C8;',
						'ophiuchus'                                              => '&#x26CE;',
						'pick'                                                   => '&#x26CF;',
						'helmet_with_white_cross'                                => '&#x26D1;',
						'chains'                                                 => '&#x26D3;',
						'no_entry'                                               => '&#x26D4;',
						'shinto_shrine'                                          => '&#x26E9;',
						'church'                                                 => '&#x26EA;',
						'mountain'                                               => '&#x26F0;',
						'umbrella_on_ground'                                     => '&#x26F1;',
						'fountain'                                               => '&#x26F2;',
						'golf'                                                   => '&#x26F3;',
						'ferry'                                                  => '&#x26F4;',
						'boat'                                                   => '&#x26F5;',
						'sailboat'                                               => '&#x26F5;',
						'skier'                                                  => '&#x26F7;',
						'ice_skate'                                              => '&#x26F8;',
						'person_with_ball'                                       => '&#x26F9;',
						'tent'                                                   => '&#x26FA;',
						'fuelpump'                                               => '&#x26FD;',
						'scissors'                                               => '&#x2702;',
						'white_check_mark'                                       => '&#x2705;',
						'airplane'                                               => '&#x2708;',
						'email'                                                  => '&#x2709;',
						'envelope'                                               => '&#x2709;',
						'fist'                                                   => '&#x270A;',
						'hand'                                                   => '&#x270B;',
						'raised_hand'                                            => '&#x270B;',
						'v'                                                      => '&#x270C;',
						'writing_hand'                                           => '&#x270D;',
						'pencil2'                                                => '&#x270F;',
						'black_nib'                                              => '&#x2712;',
						'heavy_check_mark'                                       => '&#x2714;',
						'heavy_multiplication_x'                                 => '&#x2716;',
						'latin_cross'                                            => '&#x271D;',
						'star_of_david'                                          => '&#x2721;',
						'sparkles'                                               => '&#x2728;',
						'eight_spoked_asterisk'                                  => '&#x2733;',
						'eight_pointed_black_star'                               => '&#x2734;',
						'snowflake'                                              => '&#x2744;',
						'sparkle'                                                => '&#x2747;',
						'x'                                                      => '&#x274C;',
						'negative_squared_cross_mark'                            => '&#x274E;',
						'question'                                               => '&#x2753;',
						'grey_question'                                          => '&#x2754;',
						'grey_exclamation'                                       => '&#x2755;',
						'exclamation'                                            => '&#x2757;',
						'heavy_exclamation_mark'                                 => '&#x2757;',
						'heavy_heart_exclamation_mark_ornament'                  => '&#x2763;',
						'heart'                                                  => '&#x2764;',
						'heavy_plus_sign'                                        => '&#x2795;',
						'heavy_minus_sign'                                       => '&#x2796;',
						'heavy_division_sign'                                    => '&#x2797;',
						'arrow_right'                                            => '&#x27A1;',
						'curly_loop'                                             => '&#x27B0;',
						'loop'                                                   => '&#x27BF;',
						'arrow_heading_up'                                       => '&#x2934;',
						'arrow_heading_down'                                     => '&#x2935;',
						'arrow_left'                                             => '&#x2B05;',
						'arrow_up'                                               => '&#x2B06;',
						'arrow_down'                                             => '&#x2B07;',
						'black_large_square'                                     => '&#x2B1B;',
						'white_large_square'                                     => '&#x2B1C;',
						'star'                                                   => '&#x2B50;',
						'o'                                                      => '&#x2B55;',
						'wavy_dash'                                              => '&#x3030;',
						'part_alternation_mark'                                  => '&#x303D;',
						'congratulations'                                        => '&#x3297;',
						'secret'                                                 => '&#x3299;',
						'mahjong'                                                => '&#x1F004;',
						'black_joker'                                            => '&#x1F0CF;',
						'a'                                                      => '&#x1F170;',
						'b'                                                      => '&#x1F171;',
						'o2'                                                     => '&#x1F17E;',
						'parking'                                                => '&#x1F17F;',
						'ab'                                                     => '&#x1F18E;',
						'cl'                                                     => '&#x1F191;',
						'cool'                                                   => '&#x1F192;',
						'free'                                                   => '&#x1F193;',
						'id'                                                     => '&#x1F194;',
						'new'                                                    => '&#x1F195;',
						'ng'                                                     => '&#x1F196;',
						'ok'                                                     => '&#x1F197;',
						'sos'                                                    => '&#x1F198;',
						'up'                                                     => '&#x1F199;',
						'vs'                                                     => '&#x1F19A;',
						'koko'                                                   => '&#x1F201;',
						'sa'                                                     => '&#x1F202;',
						'u7121'                                                  => '&#x1F21A;',
						'u6307'                                                  => '&#x1F22F;',
						'u7981'                                                  => '&#x1F232;',
						'u7a7a'                                                  => '&#x1F233;',
						'u5408'                                                  => '&#x1F234;',
						'u6e80'                                                  => '&#x1F235;',
						'u6709'                                                  => '&#x1F236;',
						'u6708'                                                  => '&#x1F237;',
						'u7533'                                                  => '&#x1F238;',
						'u5272'                                                  => '&#x1F239;',
						'u55b6'                                                  => '&#x1F23A;',
						'ideograph_advantage'                                    => '&#x1F250;',
						'accept'                                                 => '&#x1F251;',
						'cyclone'                                                => '&#x1F300;',
						'foggy'                                                  => '&#x1F301;',
						'closed_umbrella'                                        => '&#x1F302;',
						'night_with_stars'                                       => '&#x1F303;',
						'sunrise_over_mountains'                                 => '&#x1F304;',
						'sunrise'                                                => '&#x1F305;',
						'city_sunset'                                            => '&#x1F306;',
						'city_sunrise'                                           => '&#x1F307;',
						'rainbow'                                                => '&#x1F308;',
						'bridge_at_night'                                        => '&#x1F309;',
						'ocean'                                                  => '&#x1F30A;',
						'volcano'                                                => '&#x1F30B;',
						'milky_way'                                              => '&#x1F30C;',
						'earth_africa'                                           => '&#x1F30D;',
						'earth_americas'                                         => '&#x1F30E;',
						'earth_asia'                                             => '&#x1F30F;',
						'globe_with_meridians'                                   => '&#x1F310;',
						'new_moon'                                               => '&#x1F311;',
						'waxing_crescent_moon'                                   => '&#x1F312;',
						'first_quarter_moon'                                     => '&#x1F313;',
						'moon'                                                   => '&#x1F314;',
						'waxing_gibbous_moon'                                    => '&#x1F314;',
						'full_moon'                                              => '&#x1F315;',
						'waning_gibbous_moon'                                    => '&#x1F316;',
						'last_quarter_moon'                                      => '&#x1F317;',
						'waning_crescent_moon'                                   => '&#x1F318;',
						'crescent_moon'                                          => '&#x1F319;',
						'new_moon_with_face'                                     => '&#x1F31A;',
						'first_quarter_moon_with_face'                           => '&#x1F31B;',
						'last_quarter_moon_with_face'                            => '&#x1F31C;',
						'full_moon_with_face'                                    => '&#x1F31D;',
						'sun_with_face'                                          => '&#x1F31E;',
						'star2'                                                  => '&#x1F31F;',
						'stars'                                                  => '&#x1F320;',
						'thermometer'                                            => '&#x1F321;',
						'mostly_sunny'                                           => '&#x1F324;',
						'sun_small_cloud'                                        => '&#x1F324;',
						'barely_sunny'                                           => '&#x1F325;',
						'sun_behind_cloud'                                       => '&#x1F325;',
						'partly_sunny_rain'                                      => '&#x1F326;',
						'sun_behind_rain_cloud'                                  => '&#x1F326;',
						'rain_cloud'                                             => '&#x1F327;',
						'snow_cloud'                                             => '&#x1F328;',
						'lightning'                                              => '&#x1F329;',
						'lightning_cloud'                                        => '&#x1F329;',
						'tornado'                                                => '&#x1F32A;',
						'tornado_cloud'                                          => '&#x1F32A;',
						'fog'                                                    => '&#x1F32B;',
						'wind_blowing_face'                                      => '&#x1F32C;',
						'hotdog'                                                 => '&#x1F32D;',
						'taco'                                                   => '&#x1F32E;',
						'burrito'                                                => '&#x1F32F;',
						'chestnut'                                               => '&#x1F330;',
						'seedling'                                               => '&#x1F331;',
						'evergreen_tree'                                         => '&#x1F332;',
						'deciduous_tree'                                         => '&#x1F333;',
						'palm_tree'                                              => '&#x1F334;',
						'cactus'                                                 => '&#x1F335;',
						'hot_pepper'                                             => '&#x1F336;',
						'tulip'                                                  => '&#x1F337;',
						'cherry_blossom'                                         => '&#x1F338;',
						'rose'                                                   => '&#x1F339;',
						'hibiscus'                                               => '&#x1F33A;',
						'sunflower'                                              => '&#x1F33B;',
						'blossom'                                                => '&#x1F33C;',
						'corn'                                                   => '&#x1F33D;',
						'ear_of_rice'                                            => '&#x1F33E;',
						'herb'                                                   => '&#x1F33F;',
						'four_leaf_clover'                                       => '&#x1F340;',
						'maple_leaf'                                             => '&#x1F341;',
						'fallen_leaf'                                            => '&#x1F342;',
						'leaves'                                                 => '&#x1F343;',
						'mushroom'                                               => '&#x1F344;',
						'tomato'                                                 => '&#x1F345;',
						'eggplant'                                               => '&#x1F346;',
						'grapes'                                                 => '&#x1F347;',
						'melon'                                                  => '&#x1F348;',
						'watermelon'                                             => '&#x1F349;',
						'tangerine'                                              => '&#x1F34A;',
						'lemon'                                                  => '&#x1F34B;',
						'banana'                                                 => '&#x1F34C;',
						'pineapple'                                              => '&#x1F34D;',
						'apple'                                                  => '&#x1F34E;',
						'green_apple'                                            => '&#x1F34F;',
						'pear'                                                   => '&#x1F350;',
						'peach'                                                  => '&#x1F351;',
						'cherries'                                               => '&#x1F352;',
						'strawberry'                                             => '&#x1F353;',
						'hamburger'                                              => '&#x1F354;',
						'pizza'                                                  => '&#x1F355;',
						'meat_on_bone'                                           => '&#x1F356;',
						'poultry_leg'                                            => '&#x1F357;',
						'rice_cracker'                                           => '&#x1F358;',
						'rice_ball'                                              => '&#x1F359;',
						'rice'                                                   => '&#x1F35A;',
						'curry'                                                  => '&#x1F35B;',
						'ramen'                                                  => '&#x1F35C;',
						'spaghetti'                                              => '&#x1F35D;',
						'bread'                                                  => '&#x1F35E;',
						'fries'                                                  => '&#x1F35F;',
						'sweet_potato'                                           => '&#x1F360;',
						'dango'                                                  => '&#x1F361;',
						'oden'                                                   => '&#x1F362;',
						'sushi'                                                  => '&#x1F363;',
						'fried_shrimp'                                           => '&#x1F364;',
						'fish_cake'                                              => '&#x1F365;',
						'icecream'                                               => '&#x1F366;',
						'shaved_ice'                                             => '&#x1F367;',
						'ice_cream'                                              => '&#x1F368;',
						'doughnut'                                               => '&#x1F369;',
						'cookie'                                                 => '&#x1F36A;',
						'chocolate_bar'                                          => '&#x1F36B;',
						'candy'                                                  => '&#x1F36C;',
						'lollipop'                                               => '&#x1F36D;',
						'custard'                                                => '&#x1F36E;',
						'honey_pot'                                              => '&#x1F36F;',
						'cake'                                                   => '&#x1F370;',
						'bento'                                                  => '&#x1F371;',
						'stew'                                                   => '&#x1F372;',
						'egg'                                                    => '&#x1F373;',
						'fork_and_knife'                                         => '&#x1F374;',
						'tea'                                                    => '&#x1F375;',
						'sake'                                                   => '&#x1F376;',
						'wine_glass'                                             => '&#x1F377;',
						'cocktail'                                               => '&#x1F378;',
						'tropical_drink'                                         => '&#x1F379;',
						'beer'                                                   => '&#x1F37A;',
						'beers'                                                  => '&#x1F37B;',
						'baby_bottle'                                            => '&#x1F37C;',
						'knife_fork_plate'                                       => '&#x1F37D;',
						'champagne'                                              => '&#x1F37E;',
						'popcorn'                                                => '&#x1F37F;',
						'ribbon'                                                 => '&#x1F380;',
						'gift'                                                   => '&#x1F381;',
						'birthday'                                               => '&#x1F382;',
						'jack_o_lantern'                                         => '&#x1F383;',
						'christmas_tree'                                         => '&#x1F384;',
						'santa'                                                  => '&#x1F385;',
						'fireworks'                                              => '&#x1F386;',
						'sparkler'                                               => '&#x1F387;',
						'balloon'                                                => '&#x1F388;',
						'tada'                                                   => '&#x1F389;',
						'confetti_ball'                                          => '&#x1F38A;',
						'tanabata_tree'                                          => '&#x1F38B;',
						'crossed_flags'                                          => '&#x1F38C;',
						'bamboo'                                                 => '&#x1F38D;',
						'dolls'                                                  => '&#x1F38E;',
						'flags'                                                  => '&#x1F38F;',
						'wind_chime'                                             => '&#x1F390;',
						'rice_scene'                                             => '&#x1F391;',
						'school_satchel'                                         => '&#x1F392;',
						'mortar_board'                                           => '&#x1F393;',
						'medal'                                                  => '&#x1F396;',
						'reminder_ribbon'                                        => '&#x1F397;',
						'studio_microphone'                                      => '&#x1F399;',
						'level_slider'                                           => '&#x1F39A;',
						'control_knobs'                                          => '&#x1F39B;',
						'film_frames'                                            => '&#x1F39E;',
						'admission_tickets'                                      => '&#x1F39F;',
						'carousel_horse'                                         => '&#x1F3A0;',
						'ferris_wheel'                                           => '&#x1F3A1;',
						'roller_coaster'                                         => '&#x1F3A2;',
						'fishing_pole_and_fish'                                  => '&#x1F3A3;',
						'microphone'                                             => '&#x1F3A4;',
						'movie_camera'                                           => '&#x1F3A5;',
						'cinema'                                                 => '&#x1F3A6;',
						'headphones'                                             => '&#x1F3A7;',
						'art'                                                    => '&#x1F3A8;',
						'tophat'                                                 => '&#x1F3A9;',
						'circus_tent'                                            => '&#x1F3AA;',
						'ticket'                                                 => '&#x1F3AB;',
						'clapper'                                                => '&#x1F3AC;',
						'performing_arts'                                        => '&#x1F3AD;',
						'video_game'                                             => '&#x1F3AE;',
						'dart'                                                   => '&#x1F3AF;',
						'slot_machine'                                           => '&#x1F3B0;',
						'8ball'                                                  => '&#x1F3B1;',
						'game_die'                                               => '&#x1F3B2;',
						'bowling'                                                => '&#x1F3B3;',
						'flower_playing_cards'                                   => '&#x1F3B4;',
						'musical_note'                                           => '&#x1F3B5;',
						'notes'                                                  => '&#x1F3B6;',
						'saxophone'                                              => '&#x1F3B7;',
						'guitar'                                                 => '&#x1F3B8;',
						'musical_keyboard'                                       => '&#x1F3B9;',
						'trumpet'                                                => '&#x1F3BA;',
						'violin'                                                 => '&#x1F3BB;',
						'musical_score'                                          => '&#x1F3BC;',
						'running_shirt_with_sash'                                => '&#x1F3BD;',
						'tennis'                                                 => '&#x1F3BE;',
						'ski'                                                    => '&#x1F3BF;',
						'basketball'                                             => '&#x1F3C0;',
						'checkered_flag'                                         => '&#x1F3C1;',
						'snowboarder'                                            => '&#x1F3C2;',
						'runner'                                                 => '&#x1F3C3;',
						'running'                                                => '&#x1F3C3;',
						'surfer'                                                 => '&#x1F3C4;',
						'sports_medal'                                           => '&#x1F3C5;',
						'trophy'                                                 => '&#x1F3C6;',
						'horse_racing'                                           => '&#x1F3C7;',
						'football'                                               => '&#x1F3C8;',
						'rugby_football'                                         => '&#x1F3C9;',
						'swimmer'                                                => '&#x1F3CA;',
						'weight_lifter'                                          => '&#x1F3CB;',
						'golfer'                                                 => '&#x1F3CC;',
						'racing_motorcycle'                                      => '&#x1F3CD;',
						'racing_car'                                             => '&#x1F3CE;',
						'cricket_bat_and_ball'                                   => '&#x1F3CF;',
						'volleyball'                                             => '&#x1F3D0;',
						'field_hockey_stick_and_ball'                            => '&#x1F3D1;',
						'ice_hockey_stick_and_puck'                              => '&#x1F3D2;',
						'table_tennis_paddle_and_ball'                           => '&#x1F3D3;',
						'snow_capped_mountain'                                   => '&#x1F3D4;',
						'camping'                                                => '&#x1F3D5;',
						'beach_with_umbrella'                                    => '&#x1F3D6;',
						'building_construction'                                  => '&#x1F3D7;',
						'house_buildings'                                        => '&#x1F3D8;',
						'cityscape'                                              => '&#x1F3D9;',
						'derelict_house_building'                                => '&#x1F3DA;',
						'classical_building'                                     => '&#x1F3DB;',
						'desert'                                                 => '&#x1F3DC;',
						'desert_island'                                          => '&#x1F3DD;',
						'national_park'                                          => '&#x1F3DE;',
						'stadium'                                                => '&#x1F3DF;',
						'house'                                                  => '&#x1F3E0;',
						'house_with_garden'                                      => '&#x1F3E1;',
						'office'                                                 => '&#x1F3E2;',
						'post_office'                                            => '&#x1F3E3;',
						'european_post_office'                                   => '&#x1F3E4;',
						'hospital'                                               => '&#x1F3E5;',
						'bank'                                                   => '&#x1F3E6;',
						'atm'                                                    => '&#x1F3E7;',
						'hotel'                                                  => '&#x1F3E8;',
						'love_hotel'                                             => '&#x1F3E9;',
						'convenience_store'                                      => '&#x1F3EA;',
						'school'                                                 => '&#x1F3EB;',
						'department_store'                                       => '&#x1F3EC;',
						'factory'                                                => '&#x1F3ED;',
						'izakaya_lantern'                                        => '&#x1F3EE;',
						'lantern'                                                => '&#x1F3EE;',
						'japanese_castle'                                        => '&#x1F3EF;',
						'european_castle'                                        => '&#x1F3F0;',
						'waving_white_flag'                                      => '&#x1F3F3;',
						'waving_black_flag'                                      => '&#x1F3F4;',
						'rosette'                                                => '&#x1F3F5;',
						'label'                                                  => '&#x1F3F7;',
						'badminton_racquet_and_shuttlecock'                      => '&#x1F3F8;',
						'bow_and_arrow'                                          => '&#x1F3F9;',
						'amphora'                                                => '&#x1F3FA;',
						'skin-tone-2'                                            => '&#x1F3FB;',
						'skin-tone-3'                                            => '&#x1F3FC;',
						'skin-tone-4'                                            => '&#x1F3FD;',
						'skin-tone-5'                                            => '&#x1F3FE;',
						'skin-tone-6'                                            => '&#x1F3FF;',
						'rat'                                                    => '&#x1F400;',
						'mouse2'                                                 => '&#x1F401;',
						'ox'                                                     => '&#x1F402;',
						'water_buffalo'                                          => '&#x1F403;',
						'cow2'                                                   => '&#x1F404;',
						'tiger2'                                                 => '&#x1F405;',
						'leopard'                                                => '&#x1F406;',
						'rabbit2'                                                => '&#x1F407;',
						'cat2'                                                   => '&#x1F408;',
						'dragon'                                                 => '&#x1F409;',
						'crocodile'                                              => '&#x1F40A;',
						'whale2'                                                 => '&#x1F40B;',
						'snail'                                                  => '&#x1F40C;',
						'snake'                                                  => '&#x1F40D;',
						'racehorse'                                              => '&#x1F40E;',
						'ram'                                                    => '&#x1F40F;',
						'goat'                                                   => '&#x1F410;',
						'sheep'                                                  => '&#x1F411;',
						'monkey'                                                 => '&#x1F412;',
						'rooster'                                                => '&#x1F413;',
						'chicken'                                                => '&#x1F414;',
						'dog2'                                                   => '&#x1F415;',
						'pig2'                                                   => '&#x1F416;',
						'boar'                                                   => '&#x1F417;',
						'elephant'                                               => '&#x1F418;',
						'octopus'                                                => '&#x1F419;',
						'shell'                                                  => '&#x1F41A;',
						'bug'                                                    => '&#x1F41B;',
						'ant'                                                    => '&#x1F41C;',
						'bee'                                                    => '&#x1F41D;',
						'honeybee'                                               => '&#x1F41D;',
						'beetle'                                                 => '&#x1F41E;',
						'fish'                                                   => '&#x1F41F;',
						'tropical_fish'                                          => '&#x1F420;',
						'blowfish'                                               => '&#x1F421;',
						'turtle'                                                 => '&#x1F422;',
						'hatching_chick'                                         => '&#x1F423;',
						'baby_chick'                                             => '&#x1F424;',
						'hatched_chick'                                          => '&#x1F425;',
						'bird'                                                   => '&#x1F426;',
						'penguin'                                                => '&#x1F427;',
						'koala'                                                  => '&#x1F428;',
						'poodle'                                                 => '&#x1F429;',
						'dromedary_camel'                                        => '&#x1F42A;',
						'camel'                                                  => '&#x1F42B;',
						'dolphin'                                                => '&#x1F42C;',
						'flipper'                                                => '&#x1F42C;',
						'mouse'                                                  => '&#x1F42D;',
						'cow'                                                    => '&#x1F42E;',
						'tiger'                                                  => '&#x1F42F;',
						'rabbit'                                                 => '&#x1F430;',
						'cat'                                                    => '&#x1F431;',
						'dragon_face'                                            => '&#x1F432;',
						'whale'                                                  => '&#x1F433;',
						'horse'                                                  => '&#x1F434;',
						'monkey_face'                                            => '&#x1F435;',
						'dog'                                                    => '&#x1F436;',
						'pig'                                                    => '&#x1F437;',
						'frog'                                                   => '&#x1F438;',
						'hamster'                                                => '&#x1F439;',
						'wolf'                                                   => '&#x1F43A;',
						'bear'                                                   => '&#x1F43B;',
						'panda_face'                                             => '&#x1F43C;',
						'pig_nose'                                               => '&#x1F43D;',
						'feet'                                                   => '&#x1F43E;',
						'paw_prints'                                             => '&#x1F43E;',
						'chipmunk'                                               => '&#x1F43F;',
						'eyes'                                                   => '&#x1F440;',
						'eye'                                                    => '&#x1F441;',
						'ear'                                                    => '&#x1F442;',
						'nose'                                                   => '&#x1F443;',
						'lips'                                                   => '&#x1F444;',
						'tongue'                                                 => '&#x1F445;',
						'point_up_2'                                             => '&#x1F446;',
						'point_down'                                             => '&#x1F447;',
						'point_left'                                             => '&#x1F448;',
						'point_right'                                            => '&#x1F449;',
						'facepunch'                                              => '&#x1F44A;',
						'punch'                                                  => '&#x1F44A;',
						'wave'                                                   => '&#x1F44B;',
						'ok_hand'                                                => '&#x1F44C;',
						'+1'                                                     => '&#x1F44D;',
						'thumbsup'                                               => '&#x1F44D;',
						'-1'                                                     => '&#x1F44E;',
						'thumbsdown'                                             => '&#x1F44E;',
						'clap'                                                   => '&#x1F44F;',
						'open_hands'                                             => '&#x1F450;',
						'crown'                                                  => '&#x1F451;',
						'womans_hat'                                             => '&#x1F452;',
						'eyeglasses'                                             => '&#x1F453;',
						'necktie'                                                => '&#x1F454;',
						'shirt'                                                  => '&#x1F455;',
						'tshirt'                                                 => '&#x1F455;',
						'jeans'                                                  => '&#x1F456;',
						'dress'                                                  => '&#x1F457;',
						'kimono'                                                 => '&#x1F458;',
						'bikini'                                                 => '&#x1F459;',
						'womans_clothes'                                         => '&#x1F45A;',
						'purse'                                                  => '&#x1F45B;',
						'handbag'                                                => '&#x1F45C;',
						'pouch'                                                  => '&#x1F45D;',
						'mans_shoe'                                              => '&#x1F45E;',
						'shoe'                                                   => '&#x1F45E;',
						'athletic_shoe'                                          => '&#x1F45F;',
						'high_heel'                                              => '&#x1F460;',
						'sandal'                                                 => '&#x1F461;',
						'boot'                                                   => '&#x1F462;',
						'footprints'                                             => '&#x1F463;',
						'bust_in_silhouette'                                     => '&#x1F464;',
						'busts_in_silhouette'                                    => '&#x1F465;',
						'boy'                                                    => '&#x1F466;',
						'girl'                                                   => '&#x1F467;',
						'man'                                                    => '&#x1F468;',
						'woman'                                                  => '&#x1F469;',
						'family'                                                 => '&#x1F46A;',
						'man-woman-boy'                                          => '&#x1F46A;',
						'couple'                                                 => '&#x1F46B;',
						'man_and_woman_holding_hands'                            => '&#x1F46B;',
						'two_men_holding_hands'                                  => '&#x1F46C;',
						'two_women_holding_hands'                                => '&#x1F46D;',
						'cop'                                                    => '&#x1F46E;',
						'dancers'                                                => '&#x1F46F;',
						'bride_with_veil'                                        => '&#x1F470;',
						'person_with_blond_hair'                                 => '&#x1F471;',
						'man_with_gua_pi_mao'                                    => '&#x1F472;',
						'man_with_turban'                                        => '&#x1F473;',
						'older_man'                                              => '&#x1F474;',
						'older_woman'                                            => '&#x1F475;',
						'baby'                                                   => '&#x1F476;',
						'construction_worker'                                    => '&#x1F477;',
						'princess'                                               => '&#x1F478;',
						'japanese_ogre'                                          => '&#x1F479;',
						'japanese_goblin'                                        => '&#x1F47A;',
						'ghost'                                                  => '&#x1F47B;',
						'angel'                                                  => '&#x1F47C;',
						'alien'                                                  => '&#x1F47D;',
						'space_invader'                                          => '&#x1F47E;',
						'imp'                                                    => '&#x1F47F;',
						'skull'                                                  => '&#x1F480;',
						'information_desk_person'                                => '&#x1F481;',
						'guardsman'                                              => '&#x1F482;',
						'dancer'                                                 => '&#x1F483;',
						'lipstick'                                               => '&#x1F484;',
						'nail_care'                                              => '&#x1F485;',
						'massage'                                                => '&#x1F486;',
						'haircut'                                                => '&#x1F487;',
						'barber'                                                 => '&#x1F488;',
						'syringe'                                                => '&#x1F489;',
						'pill'                                                   => '&#x1F48A;',
						'kiss'                                                   => '&#x1F48B;',
						'love_letter'                                            => '&#x1F48C;',
						'ring'                                                   => '&#x1F48D;',
						'gem'                                                    => '&#x1F48E;',
						'couplekiss'                                             => '&#x1F48F;',
						'bouquet'                                                => '&#x1F490;',
						'couple_with_heart'                                      => '&#x1F491;',
						'wedding'                                                => '&#x1F492;',
						'heartbeat'                                              => '&#x1F493;',
						'broken_heart'                                           => '&#x1F494;',
						'two_hearts'                                             => '&#x1F495;',
						'sparkling_heart'                                        => '&#x1F496;',
						'heartpulse'                                             => '&#x1F497;',
						'cupid'                                                  => '&#x1F498;',
						'blue_heart'                                             => '&#x1F499;',
						'green_heart'                                            => '&#x1F49A;',
						'yellow_heart'                                           => '&#x1F49B;',
						'purple_heart'                                           => '&#x1F49C;',
						'gift_heart'                                             => '&#x1F49D;',
						'revolving_hearts'                                       => '&#x1F49E;',
						'heart_decoration'                                       => '&#x1F49F;',
						'diamond_shape_with_a_dot_inside'                        => '&#x1F4A0;',
						'bulb'                                                   => '&#x1F4A1;',
						'anger'                                                  => '&#x1F4A2;',
						'bomb'                                                   => '&#x1F4A3;',
						'zzz'                                                    => '&#x1F4A4;',
						'boom'                                                   => '&#x1F4A5;',
						'collision'                                              => '&#x1F4A5;',
						'sweat_drops'                                            => '&#x1F4A6;',
						'droplet'                                                => '&#x1F4A7;',
						'dash'                                                   => '&#x1F4A8;',
						'hankey'                                                 => '&#x1F4A9;',
						'poop'                                                   => '&#x1F4A9;',
						'shit'                                                   => '&#x1F4A9;',
						'muscle'                                                 => '&#x1F4AA;',
						'dizzy'                                                  => '&#x1F4AB;',
						'speech_balloon'                                         => '&#x1F4AC;',
						'thought_balloon'                                        => '&#x1F4AD;',
						'white_flower'                                           => '&#x1F4AE;',
						'100'                                                    => '&#x1F4AF;',
						'moneybag'                                               => '&#x1F4B0;',
						'currency_exchange'                                      => '&#x1F4B1;',
						'heavy_dollar_sign'                                      => '&#x1F4B2;',
						'credit_card'                                            => '&#x1F4B3;',
						'yen'                                                    => '&#x1F4B4;',
						'dollar'                                                 => '&#x1F4B5;',
						'euro'                                                   => '&#x1F4B6;',
						'pound'                                                  => '&#x1F4B7;',
						'money_with_wings'                                       => '&#x1F4B8;',
						'chart'                                                  => '&#x1F4B9;',
						'seat'                                                   => '&#x1F4BA;',
						'computer'                                               => '&#x1F4BB;',
						'briefcase'                                              => '&#x1F4BC;',
						'minidisc'                                               => '&#x1F4BD;',
						'floppy_disk'                                            => '&#x1F4BE;',
						'cd'                                                     => '&#x1F4BF;',
						'dvd'                                                    => '&#x1F4C0;',
						'file_folder'                                            => '&#x1F4C1;',
						'open_file_folder'                                       => '&#x1F4C2;',
						'page_with_curl'                                         => '&#x1F4C3;',
						'page_facing_up'                                         => '&#x1F4C4;',
						'date'                                                   => '&#x1F4C5;',
						'calendar'                                               => '&#x1F4C6;',
						'card_index'                                             => '&#x1F4C7;',
						'chart_with_upwards_trend'                               => '&#x1F4C8;',
						'chart_with_downwards_trend'                             => '&#x1F4C9;',
						'bar_chart'                                              => '&#x1F4CA;',
						'clipboard'                                              => '&#x1F4CB;',
						'pushpin'                                                => '&#x1F4CC;',
						'round_pushpin'                                          => '&#x1F4CD;',
						'paperclip'                                              => '&#x1F4CE;',
						'straight_ruler'                                         => '&#x1F4CF;',
						'triangular_ruler'                                       => '&#x1F4D0;',
						'bookmark_tabs'                                          => '&#x1F4D1;',
						'ledger'                                                 => '&#x1F4D2;',
						'notebook'                                               => '&#x1F4D3;',
						'notebook_with_decorative_cover'                         => '&#x1F4D4;',
						'closed_book'                                            => '&#x1F4D5;',
						'book'                                                   => '&#x1F4D6;',
						'open_book'                                              => '&#x1F4D6;',
						'green_book'                                             => '&#x1F4D7;',
						'blue_book'                                              => '&#x1F4D8;',
						'orange_book'                                            => '&#x1F4D9;',
						'books'                                                  => '&#x1F4DA;',
						'name_badge'                                             => '&#x1F4DB;',
						'scroll'                                                 => '&#x1F4DC;',
						'memo'                                                   => '&#x1F4DD;',
						'pencil'                                                 => '&#x1F4DD;',
						'telephone_receiver'                                     => '&#x1F4DE;',
						'pager'                                                  => '&#x1F4DF;',
						'fax'                                                    => '&#x1F4E0;',
						'satellite_antenna'                                      => '&#x1F4E1;',
						'loudspeaker'                                            => '&#x1F4E2;',
						'mega'                                                   => '&#x1F4E3;',
						'outbox_tray'                                            => '&#x1F4E4;',
						'inbox_tray'                                             => '&#x1F4E5;',
						'package'                                                => '&#x1F4E6;',
						'e-mail'                                                 => '&#x1F4E7;',
						'incoming_envelope'                                      => '&#x1F4E8;',
						'envelope_with_arrow'                                    => '&#x1F4E9;',
						'mailbox_closed'                                         => '&#x1F4EA;',
						'mailbox'                                                => '&#x1F4EB;',
						'mailbox_with_mail'                                      => '&#x1F4EC;',
						'mailbox_with_no_mail'                                   => '&#x1F4ED;',
						'postbox'                                                => '&#x1F4EE;',
						'postal_horn'                                            => '&#x1F4EF;',
						'newspaper'                                              => '&#x1F4F0;',
						'iphone'                                                 => '&#x1F4F1;',
						'calling'                                                => '&#x1F4F2;',
						'vibration_mode'                                         => '&#x1F4F3;',
						'mobile_phone_off'                                       => '&#x1F4F4;',
						'no_mobile_phones'                                       => '&#x1F4F5;',
						'signal_strength'                                        => '&#x1F4F6;',
						'camera'                                                 => '&#x1F4F7;',
						'camera_with_flash'                                      => '&#x1F4F8;',
						'video_camera'                                           => '&#x1F4F9;',
						'tv'                                                     => '&#x1F4FA;',
						'radio'                                                  => '&#x1F4FB;',
						'vhs'                                                    => '&#x1F4FC;',
						'film_projector'                                         => '&#x1F4FD;',
						'prayer_beads'                                           => '&#x1F4FF;',
						'twisted_rightwards_arrows'                              => '&#x1F500;',
						'repeat'                                                 => '&#x1F501;',
						'repeat_one'                                             => '&#x1F502;',
						'arrows_clockwise'                                       => '&#x1F503;',
						'arrows_counterclockwise'                                => '&#x1F504;',
						'low_brightness'                                         => '&#x1F505;',
						'high_brightness'                                        => '&#x1F506;',
						'mute'                                                   => '&#x1F507;',
						'speaker'                                                => '&#x1F508;',
						'sound'                                                  => '&#x1F509;',
						'loud_sound'                                             => '&#x1F50A;',
						'battery'                                                => '&#x1F50B;',
						'electric_plug'                                          => '&#x1F50C;',
						'mag'                                                    => '&#x1F50D;',
						'mag_right'                                              => '&#x1F50E;',
						'lock_with_ink_pen'                                      => '&#x1F50F;',
						'closed_lock_with_key'                                   => '&#x1F510;',
						'key'                                                    => '&#x1F511;',
						'lock'                                                   => '&#x1F512;',
						'unlock'                                                 => '&#x1F513;',
						'bell'                                                   => '&#x1F514;',
						'no_bell'                                                => '&#x1F515;',
						'bookmark'                                               => '&#x1F516;',
						'link'                                                   => '&#x1F517;',
						'radio_button'                                           => '&#x1F518;',
						'back'                                                   => '&#x1F519;',
						'end'                                                    => '&#x1F51A;',
						'on'                                                     => '&#x1F51B;',
						'soon'                                                   => '&#x1F51C;',
						'top'                                                    => '&#x1F51D;',
						'underage'                                               => '&#x1F51E;',
						'keycap_ten'                                             => '&#x1F51F;',
						'capital_abcd'                                           => '&#x1F520;',
						'abcd'                                                   => '&#x1F521;',
						'1234'                                                   => '&#x1F522;',
						'symbols'                                                => '&#x1F523;',
						'abc'                                                    => '&#x1F524;',
						'fire'                                                   => '&#x1F525;',
						'flashlight'                                             => '&#x1F526;',
						'wrench'                                                 => '&#x1F527;',
						'hammer'                                                 => '&#x1F528;',
						'nut_and_bolt'                                           => '&#x1F529;',
						'hocho'                                                  => '&#x1F52A;',
						'knife'                                                  => '&#x1F52A;',
						'gun'                                                    => '&#x1F52B;',
						'microscope'                                             => '&#x1F52C;',
						'telescope'                                              => '&#x1F52D;',
						'crystal_ball'                                           => '&#x1F52E;',
						'six_pointed_star'                                       => '&#x1F52F;',
						'beginner'                                               => '&#x1F530;',
						'trident'                                                => '&#x1F531;',
						'black_square_button'                                    => '&#x1F532;',
						'white_square_button'                                    => '&#x1F533;',
						'red_circle'                                             => '&#x1F534;',
						'large_blue_circle'                                      => '&#x1F535;',
						'large_orange_diamond'                                   => '&#x1F536;',
						'large_blue_diamond'                                     => '&#x1F537;',
						'small_orange_diamond'                                   => '&#x1F538;',
						'small_blue_diamond'                                     => '&#x1F539;',
						'small_red_triangle'                                     => '&#x1F53A;',
						'small_red_triangle_down'                                => '&#x1F53B;',
						'arrow_up_small'                                         => '&#x1F53C;',
						'arrow_down_small'                                       => '&#x1F53D;',
						'om_symbol'                                              => '&#x1F549;',
						'dove_of_peace'                                          => '&#x1F54A;',
						'kaaba'                                                  => '&#x1F54B;',
						'mosque'                                                 => '&#x1F54C;',
						'synagogue'                                              => '&#x1F54D;',
						'menorah_with_nine_branches'                             => '&#x1F54E;',
						'clock1'                                                 => '&#x1F550;',
						'clock2'                                                 => '&#x1F551;',
						'clock3'                                                 => '&#x1F552;',
						'clock4'                                                 => '&#x1F553;',
						'clock5'                                                 => '&#x1F554;',
						'clock6'                                                 => '&#x1F555;',
						'clock7'                                                 => '&#x1F556;',
						'clock8'                                                 => '&#x1F557;',
						'clock9'                                                 => '&#x1F558;',
						'clock10'                                                => '&#x1F559;',
						'clock11'                                                => '&#x1F55A;',
						'clock12'                                                => '&#x1F55B;',
						'clock130'                                               => '&#x1F55C;',
						'clock230'                                               => '&#x1F55D;',
						'clock330'                                               => '&#x1F55E;',
						'clock430'                                               => '&#x1F55F;',
						'clock530'                                               => '&#x1F560;',
						'clock630'                                               => '&#x1F561;',
						'clock730'                                               => '&#x1F562;',
						'clock830'                                               => '&#x1F563;',
						'clock930'                                               => '&#x1F564;',
						'clock1030'                                              => '&#x1F565;',
						'clock1130'                                              => '&#x1F566;',
						'clock1230'                                              => '&#x1F567;',
						'candle'                                                 => '&#x1F56F;',
						'mantelpiece_clock'                                      => '&#x1F570;',
						'hole'                                                   => '&#x1F573;',
						'man_in_business_suit_levitating'                        => '&#x1F574;',
						'sleuth_or_spy'                                          => '&#x1F575;',
						'dark_sunglasses'                                        => '&#x1F576;',
						'spider'                                                 => '&#x1F577;',
						'spider_web'                                             => '&#x1F578;',
						'joystick'                                               => '&#x1F579;',
						'linked_paperclips'                                      => '&#x1F587;',
						'lower_left_ballpoint_pen'                               => '&#x1F58A;',
						'lower_left_fountain_pen'                                => '&#x1F58B;',
						'lower_left_paintbrush'                                  => '&#x1F58C;',
						'lower_left_crayon'                                      => '&#x1F58D;',
						'raised_hand_with_fingers_splayed'                       => '&#x1F590;',
						'middle_finger'                                          => '&#x1F595;',
						'reversed_hand_with_middle_finger_extended'              => '&#x1F595;',
						'spock-hand'                                             => '&#x1F596;',
						'desktop_computer'                                       => '&#x1F5A5;',
						'printer'                                                => '&#x1F5A8;',
						'three_button_mouse'                                     => '&#x1F5B1;',
						'trackball'                                              => '&#x1F5B2;',
						'frame_with_picture'                                     => '&#x1F5BC;',
						'card_index_dividers'                                    => '&#x1F5C2;',
						'card_file_box'                                          => '&#x1F5C3;',
						'file_cabinet'                                           => '&#x1F5C4;',
						'wastebasket'                                            => '&#x1F5D1;',
						'spiral_note_pad'                                        => '&#x1F5D2;',
						'spiral_calendar_pad'                                    => '&#x1F5D3;',
						'compression'                                            => '&#x1F5DC;',
						'old_key'                                                => '&#x1F5DD;',
						'rolled_up_newspaper'                                    => '&#x1F5DE;',
						'dagger_knife'                                           => '&#x1F5E1;',
						'speaking_head_in_silhouette'                            => '&#x1F5E3;',
						'left_speech_bubble'                                     => '&#x1F5E8;',
						'right_anger_bubble'                                     => '&#x1F5EF;',
						'ballot_box_with_ballot'                                 => '&#x1F5F3;',
						'world_map'                                              => '&#x1F5FA;',
						'mount_fuji'                                             => '&#x1F5FB;',
						'tokyo_tower'                                            => '&#x1F5FC;',
						'statue_of_liberty'                                      => '&#x1F5FD;',
						'japan'                                                  => '&#x1F5FE;',
						'moyai'                                                  => '&#x1F5FF;',
						'grinning'                                               => '&#x1F600;',
						'grin'                                                   => '&#x1F601;',
						'joy'                                                    => '&#x1F602;',
						'smiley'                                                 => '&#x1F603;',
						'smile'                                                  => '&#x1F604;',
						'simple_smile'                                           => '&#x1F642;',
						'sweat_smile'                                            => '&#x1F605;',
						'laughing'                                               => '&#x1F606;',
						'satisfied'                                              => '&#x1F606;',
						'innocent'                                               => '&#x1F607;',
						'smiling_imp'                                            => '&#x1F608;',
						'wink'                                                   => '&#x1F609;',
						'blush'                                                  => '&#x1F60A;',
						'yum'                                                    => '&#x1F60B;',
						'relieved'                                               => '&#x1F60C;',
						'heart_eyes'                                             => '&#x1F60D;',
						'sunglasses'                                             => '&#x1F60E;',
						'smirk'                                                  => '&#x1F60F;',
						'neutral_face'                                           => '&#x1F610;',
						'expressionless'                                         => '&#x1F611;',
						'unamused'                                               => '&#x1F612;',
						'sweat'                                                  => '&#x1F613;',
						'pensive'                                                => '&#x1F614;',
						'confused'                                               => '&#x1F615;',
						'confounded'                                             => '&#x1F616;',
						'kissing'                                                => '&#x1F617;',
						'kissing_heart'                                          => '&#x1F618;',
						'kissing_smiling_eyes'                                   => '&#x1F619;',
						'kissing_closed_eyes'                                    => '&#x1F61A;',
						'stuck_out_tongue'                                       => '&#x1F61B;',
						'stuck_out_tongue_winking_eye'                           => '&#x1F61C;',
						'stuck_out_tongue_closed_eyes'                           => '&#x1F61D;',
						'disappointed'                                           => '&#x1F61E;',
						'worried'                                                => '&#x1F61F;',
						'angry'                                                  => '&#x1F620;',
						'rage'                                                   => '&#x1F621;',
						'cry'                                                    => '&#x1F622;',
						'persevere'                                              => '&#x1F623;',
						'triumph'                                                => '&#x1F624;',
						'disappointed_relieved'                                  => '&#x1F625;',
						'frowning'                                               => '&#x1F626;',
						'anguished'                                              => '&#x1F627;',
						'fearful'                                                => '&#x1F628;',
						'weary'                                                  => '&#x1F629;',
						'sleepy'                                                 => '&#x1F62A;',
						'tired_face'                                             => '&#x1F62B;',
						'grimacing'                                              => '&#x1F62C;',
						'sob'                                                    => '&#x1F62D;',
						'open_mouth'                                             => '&#x1F62E;',
						'hushed'                                                 => '&#x1F62F;',
						'cold_sweat'                                             => '&#x1F630;',
						'scream'                                                 => '&#x1F631;',
						'astonished'                                             => '&#x1F632;',
						'flushed'                                                => '&#x1F633;',
						'sleeping'                                               => '&#x1F634;',
						'dizzy_face'                                             => '&#x1F635;',
						'no_mouth'                                               => '&#x1F636;',
						'mask'                                                   => '&#x1F637;',
						'smile_cat'                                              => '&#x1F638;',
						'joy_cat'                                                => '&#x1F639;',
						'smiley_cat'                                             => '&#x1F63A;',
						'heart_eyes_cat'                                         => '&#x1F63B;',
						'smirk_cat'                                              => '&#x1F63C;',
						'kissing_cat'                                            => '&#x1F63D;',
						'pouting_cat'                                            => '&#x1F63E;',
						'crying_cat_face'                                        => '&#x1F63F;',
						'scream_cat'                                             => '&#x1F640;',
						'slightly_frowning_face'                                 => '&#x1F641;',
						'slightly_smiling_face'                                  => '&#x1F642;',
						'upside_down_face'                                       => '&#x1F643;',
						'face_with_rolling_eyes'                                 => '&#x1F644;',
						'no_good'                                                => '&#x1F645;',
						'ok_woman'                                               => '&#x1F646;',
						'bow'                                                    => '&#x1F647;',
						'see_no_evil'                                            => '&#x1F648;',
						'hear_no_evil'                                           => '&#x1F649;',
						'speak_no_evil'                                          => '&#x1F64A;',
						'raising_hand'                                           => '&#x1F64B;',
						'raised_hands'                                           => '&#x1F64C;',
						'person_frowning'                                        => '&#x1F64D;',
						'person_with_pouting_face'                               => '&#x1F64E;',
						'pray'                                                   => '&#x1F64F;',
						'rocket'                                                 => '&#x1F680;',
						'helicopter'                                             => '&#x1F681;',
						'steam_locomotive'                                       => '&#x1F682;',
						'railway_car'                                            => '&#x1F683;',
						'bullettrain_side'                                       => '&#x1F684;',
						'bullettrain_front'                                      => '&#x1F685;',
						'train2'                                                 => '&#x1F686;',
						'metro'                                                  => '&#x1F687;',
						'light_rail'                                             => '&#x1F688;',
						'station'                                                => '&#x1F689;',
						'tram'                                                   => '&#x1F68A;',
						'train'                                                  => '&#x1F68B;',
						'bus'                                                    => '&#x1F68C;',
						'oncoming_bus'                                           => '&#x1F68D;',
						'trolleybus'                                             => '&#x1F68E;',
						'busstop'                                                => '&#x1F68F;',
						'minibus'                                                => '&#x1F690;',
						'ambulance'                                              => '&#x1F691;',
						'fire_engine'                                            => '&#x1F692;',
						'police_car'                                             => '&#x1F693;',
						'oncoming_police_car'                                    => '&#x1F694;',
						'taxi'                                                   => '&#x1F695;',
						'oncoming_taxi'                                          => '&#x1F696;',
						'car'                                                    => '&#x1F697;',
						'red_car'                                                => '&#x1F697;',
						'oncoming_automobile'                                    => '&#x1F698;',
						'blue_car'                                               => '&#x1F699;',
						'truck'                                                  => '&#x1F69A;',
						'articulated_lorry'                                      => '&#x1F69B;',
						'tractor'                                                => '&#x1F69C;',
						'monorail'                                               => '&#x1F69D;',
						'mountain_railway'                                       => '&#x1F69E;',
						'suspension_railway'                                     => '&#x1F69F;',
						'mountain_cableway'                                      => '&#x1F6A0;',
						'aerial_tramway'                                         => '&#x1F6A1;',
						'ship'                                                   => '&#x1F6A2;',
						'rowboat'                                                => '&#x1F6A3;',
						'speedboat'                                              => '&#x1F6A4;',
						'traffic_light'                                          => '&#x1F6A5;',
						'vertical_traffic_light'                                 => '&#x1F6A6;',
						'construction'                                           => '&#x1F6A7;',
						'rotating_light'                                         => '&#x1F6A8;',
						'triangular_flag_on_post'                                => '&#x1F6A9;',
						'door'                                                   => '&#x1F6AA;',
						'no_entry_sign'                                          => '&#x1F6AB;',
						'smoking'                                                => '&#x1F6AC;',
						'no_smoking'                                             => '&#x1F6AD;',
						'put_litter_in_its_place'                                => '&#x1F6AE;',
						'do_not_litter'                                          => '&#x1F6AF;',
						'potable_water'                                          => '&#x1F6B0;',
						'non-potable_water'                                      => '&#x1F6B1;',
						'bike'                                                   => '&#x1F6B2;',
						'no_bicycles'                                            => '&#x1F6B3;',
						'bicyclist'                                              => '&#x1F6B4;',
						'mountain_bicyclist'                                     => '&#x1F6B5;',
						'walking'                                                => '&#x1F6B6;',
						'no_pedestrians'                                         => '&#x1F6B7;',
						'children_crossing'                                      => '&#x1F6B8;',
						'mens'                                                   => '&#x1F6B9;',
						'womens'                                                 => '&#x1F6BA;',
						'restroom'                                               => '&#x1F6BB;',
						'baby_symbol'                                            => '&#x1F6BC;',
						'toilet'                                                 => '&#x1F6BD;',
						'wc'                                                     => '&#x1F6BE;',
						'shower'                                                 => '&#x1F6BF;',
						'bath'                                                   => '&#x1F6C0;',
						'bathtub'                                                => '&#x1F6C1;',
						'passport_control'                                       => '&#x1F6C2;',
						'customs'                                                => '&#x1F6C3;',
						'baggage_claim'                                          => '&#x1F6C4;',
						'left_luggage'                                           => '&#x1F6C5;',
						'couch_and_lamp'                                         => '&#x1F6CB;',
						'sleeping_accommodation'                                 => '&#x1F6CC;',
						'shopping_bags'                                          => '&#x1F6CD;',
						'bellhop_bell'                                           => '&#x1F6CE;',
						'bed'                                                    => '&#x1F6CF;',
						'place_of_worship'                                       => '&#x1F6D0;',
						'hammer_and_wrench'                                      => '&#x1F6E0;',
						'shield'                                                 => '&#x1F6E1;',
						'oil_drum'                                               => '&#x1F6E2;',
						'motorway'                                               => '&#x1F6E3;',
						'railway_track'                                          => '&#x1F6E4;',
						'motor_boat'                                             => '&#x1F6E5;',
						'small_airplane'                                         => '&#x1F6E9;',
						'airplane_departure'                                     => '&#x1F6EB;',
						'airplane_arriving'                                      => '&#x1F6EC;',
						'satellite'                                              => '&#x1F6F0;',
						'passenger_ship'                                         => '&#x1F6F3;',
						'zipper_mouth_face'                                      => '&#x1F910;',
						'money_mouth_face'                                       => '&#x1F911;',
						'face_with_thermometer'                                  => '&#x1F912;',
						'nerd_face'                                              => '&#x1F913;',
						'thinking_face'                                          => '&#x1F914;',
						'face_with_head_bandage'                                 => '&#x1F915;',
						'robot_face'                                             => '&#x1F916;',
						'hugging_face'                                           => '&#x1F917;',
						'the_horns'                                              => '&#x1F918;',
						'sign_of_the_horns'                                      => '&#x1F918;',
						'crab'                                                   => '&#x1F980;',
						'lion_face'                                              => '&#x1F981;',
						'scorpion'                                               => '&#x1F982;',
						'turkey'                                                 => '&#x1F983;',
						'unicorn_face'                                           => '&#x1F984;',
						'cheese_wedge'                                           => '&#x1F9C0;',
						'hash'                                                   => '&#x0023;&#x20E3;',
						'keycap_star'                                            => '&#x002A;&#x20E3;',
						'zero'                                                   => '&#x0030;&#x20E3;',
						'one'                                                    => '&#x0031;&#x20E3;',
						'two'                                                    => '&#x0032;&#x20E3;',
						'three'                                                  => '&#x0033;&#x20E3;',
						'four'                                                   => '&#x0034;&#x20E3;',
						'five'                                                   => '&#x0035;&#x20E3;',
						'six'                                                    => '&#x0036;&#x20E3;',
						'seven'                                                  => '&#x0037;&#x20E3;',
						'eight'                                                  => '&#x0038;&#x20E3;',
						'nine'                                                   => '&#x0039;&#x20E3;',
						'flag-ac'                                                => '&#x1F1E6;&#x1F1E8;',
						'flag-ad'                                                => '&#x1F1E6;&#x1F1E9;',
						'flag-ae'                                                => '&#x1F1E6;&#x1F1EA;',
						'flag-af'                                                => '&#x1F1E6;&#x1F1EB;',
						'flag-ag'                                                => '&#x1F1E6;&#x1F1EC;',
						'flag-ai'                                                => '&#x1F1E6;&#x1F1EE;',
						'flag-al'                                                => '&#x1F1E6;&#x1F1F1;',
						'flag-am'                                                => '&#x1F1E6;&#x1F1F2;',
						'flag-ao'                                                => '&#x1F1E6;&#x1F1F4;',
						'flag-aq'                                                => '&#x1F1E6;&#x1F1F6;',
						'flag-ar'                                                => '&#x1F1E6;&#x1F1F7;',
						'flag-as'                                                => '&#x1F1E6;&#x1F1F8;',
						'flag-at'                                                => '&#x1F1E6;&#x1F1F9;',
						'flag-au'                                                => '&#x1F1E6;&#x1F1FA;',
						'flag-aw'                                                => '&#x1F1E6;&#x1F1FC;',
						'flag-ax'                                                => '&#x1F1E6;&#x1F1FD;',
						'flag-az'                                                => '&#x1F1E6;&#x1F1FF;',
						'flag-ba'                                                => '&#x1F1E7;&#x1F1E6;',
						'flag-bb'                                                => '&#x1F1E7;&#x1F1E7;',
						'flag-bd'                                                => '&#x1F1E7;&#x1F1E9;',
						'flag-be'                                                => '&#x1F1E7;&#x1F1EA;',
						'flag-bf'                                                => '&#x1F1E7;&#x1F1EB;',
						'flag-bg'                                                => '&#x1F1E7;&#x1F1EC;',
						'flag-bh'                                                => '&#x1F1E7;&#x1F1ED;',
						'flag-bi'                                                => '&#x1F1E7;&#x1F1EE;',
						'flag-bj'                                                => '&#x1F1E7;&#x1F1EF;',
						'flag-bl'                                                => '&#x1F1E7;&#x1F1F1;',
						'flag-bm'                                                => '&#x1F1E7;&#x1F1F2;',
						'flag-bn'                                                => '&#x1F1E7;&#x1F1F3;',
						'flag-bo'                                                => '&#x1F1E7;&#x1F1F4;',
						'flag-bq'                                                => '&#x1F1E7;&#x1F1F6;',
						'flag-br'                                                => '&#x1F1E7;&#x1F1F7;',
						'flag-bs'                                                => '&#x1F1E7;&#x1F1F8;',
						'flag-bt'                                                => '&#x1F1E7;&#x1F1F9;',
						'flag-bv'                                                => '&#x1F1E7;&#x1F1FB;',
						'flag-bw'                                                => '&#x1F1E7;&#x1F1FC;',
						'flag-by'                                                => '&#x1F1E7;&#x1F1FE;',
						'flag-bz'                                                => '&#x1F1E7;&#x1F1FF;',
						'flag-ca'                                                => '&#x1F1E8;&#x1F1E6;',
						'flag-cc'                                                => '&#x1F1E8;&#x1F1E8;',
						'flag-cd'                                                => '&#x1F1E8;&#x1F1E9;',
						'flag-cf'                                                => '&#x1F1E8;&#x1F1EB;',
						'flag-cg'                                                => '&#x1F1E8;&#x1F1EC;',
						'flag-ch'                                                => '&#x1F1E8;&#x1F1ED;',
						'flag-ci'                                                => '&#x1F1E8;&#x1F1EE;',
						'flag-ck'                                                => '&#x1F1E8;&#x1F1F0;',
						'flag-cl'                                                => '&#x1F1E8;&#x1F1F1;',
						'flag-cm'                                                => '&#x1F1E8;&#x1F1F2;',
						'flag-cn'                                                => '&#x1F1E8;&#x1F1F3;',
						'cn'                                                     => '&#x1F1E8;&#x1F1F3;',
						'flag-co'                                                => '&#x1F1E8;&#x1F1F4;',
						'flag-cp'                                                => '&#x1F1E8;&#x1F1F5;',
						'flag-cr'                                                => '&#x1F1E8;&#x1F1F7;',
						'flag-cu'                                                => '&#x1F1E8;&#x1F1FA;',
						'flag-cv'                                                => '&#x1F1E8;&#x1F1FB;',
						'flag-cw'                                                => '&#x1F1E8;&#x1F1FC;',
						'flag-cx'                                                => '&#x1F1E8;&#x1F1FD;',
						'flag-cy'                                                => '&#x1F1E8;&#x1F1FE;',
						'flag-cz'                                                => '&#x1F1E8;&#x1F1FF;',
						'flag-de'                                                => '&#x1F1E9;&#x1F1EA;',
						'de'                                                     => '&#x1F1E9;&#x1F1EA;',
						'flag-dg'                                                => '&#x1F1E9;&#x1F1EC;',
						'flag-dj'                                                => '&#x1F1E9;&#x1F1EF;',
						'flag-dk'                                                => '&#x1F1E9;&#x1F1F0;',
						'flag-dm'                                                => '&#x1F1E9;&#x1F1F2;',
						'flag-do'                                                => '&#x1F1E9;&#x1F1F4;',
						'flag-dz'                                                => '&#x1F1E9;&#x1F1FF;',
						'flag-ea'                                                => '&#x1F1EA;&#x1F1E6;',
						'flag-ec'                                                => '&#x1F1EA;&#x1F1E8;',
						'flag-ee'                                                => '&#x1F1EA;&#x1F1EA;',
						'flag-eg'                                                => '&#x1F1EA;&#x1F1EC;',
						'flag-eh'                                                => '&#x1F1EA;&#x1F1ED;',
						'flag-er'                                                => '&#x1F1EA;&#x1F1F7;',
						'flag-es'                                                => '&#x1F1EA;&#x1F1F8;',
						'es'                                                     => '&#x1F1EA;&#x1F1F8;',
						'flag-et'                                                => '&#x1F1EA;&#x1F1F9;',
						'flag-eu'                                                => '&#x1F1EA;&#x1F1FA;',
						'flag-fi'                                                => '&#x1F1EB;&#x1F1EE;',
						'flag-fj'                                                => '&#x1F1EB;&#x1F1EF;',
						'flag-fk'                                                => '&#x1F1EB;&#x1F1F0;',
						'flag-fm'                                                => '&#x1F1EB;&#x1F1F2;',
						'flag-fo'                                                => '&#x1F1EB;&#x1F1F4;',
						'flag-fr'                                                => '&#x1F1EB;&#x1F1F7;',
						'fr'                                                     => '&#x1F1EB;&#x1F1F7;',
						'flag-ga'                                                => '&#x1F1EC;&#x1F1E6;',
						'flag-gb'                                                => '&#x1F1EC;&#x1F1E7;',
						'gb'                                                     => '&#x1F1EC;&#x1F1E7;',
						'uk'                                                     => '&#x1F1EC;&#x1F1E7;',
						'flag-gd'                                                => '&#x1F1EC;&#x1F1E9;',
						'flag-ge'                                                => '&#x1F1EC;&#x1F1EA;',
						'flag-gf'                                                => '&#x1F1EC;&#x1F1EB;',
						'flag-gg'                                                => '&#x1F1EC;&#x1F1EC;',
						'flag-gh'                                                => '&#x1F1EC;&#x1F1ED;',
						'flag-gi'                                                => '&#x1F1EC;&#x1F1EE;',
						'flag-gl'                                                => '&#x1F1EC;&#x1F1F1;',
						'flag-gm'                                                => '&#x1F1EC;&#x1F1F2;',
						'flag-gn'                                                => '&#x1F1EC;&#x1F1F3;',
						'flag-gp'                                                => '&#x1F1EC;&#x1F1F5;',
						'flag-gq'                                                => '&#x1F1EC;&#x1F1F6;',
						'flag-gr'                                                => '&#x1F1EC;&#x1F1F7;',
						'flag-gs'                                                => '&#x1F1EC;&#x1F1F8;',
						'flag-gt'                                                => '&#x1F1EC;&#x1F1F9;',
						'flag-gu'                                                => '&#x1F1EC;&#x1F1FA;',
						'flag-gw'                                                => '&#x1F1EC;&#x1F1FC;',
						'flag-gy'                                                => '&#x1F1EC;&#x1F1FE;',
						'flag-hk'                                                => '&#x1F1ED;&#x1F1F0;',
						'flag-hm'                                                => '&#x1F1ED;&#x1F1F2;',
						'flag-hn'                                                => '&#x1F1ED;&#x1F1F3;',
						'flag-hr'                                                => '&#x1F1ED;&#x1F1F7;',
						'flag-ht'                                                => '&#x1F1ED;&#x1F1F9;',
						'flag-hu'                                                => '&#x1F1ED;&#x1F1FA;',
						'flag-ic'                                                => '&#x1F1EE;&#x1F1E8;',
						'flag-id'                                                => '&#x1F1EE;&#x1F1E9;',
						'flag-ie'                                                => '&#x1F1EE;&#x1F1EA;',
						'flag-il'                                                => '&#x1F1EE;&#x1F1F1;',
						'flag-im'                                                => '&#x1F1EE;&#x1F1F2;',
						'flag-in'                                                => '&#x1F1EE;&#x1F1F3;',
						'flag-io'                                                => '&#x1F1EE;&#x1F1F4;',
						'flag-iq'                                                => '&#x1F1EE;&#x1F1F6;',
						'flag-ir'                                                => '&#x1F1EE;&#x1F1F7;',
						'flag-is'                                                => '&#x1F1EE;&#x1F1F8;',
						'flag-it'                                                => '&#x1F1EE;&#x1F1F9;',
						'it'                                                     => '&#x1F1EE;&#x1F1F9;',
						'flag-je'                                                => '&#x1F1EF;&#x1F1EA;',
						'flag-jm'                                                => '&#x1F1EF;&#x1F1F2;',
						'flag-jo'                                                => '&#x1F1EF;&#x1F1F4;',
						'flag-jp'                                                => '&#x1F1EF;&#x1F1F5;',
						'jp'                                                     => '&#x1F1EF;&#x1F1F5;',
						'flag-ke'                                                => '&#x1F1F0;&#x1F1EA;',
						'flag-kg'                                                => '&#x1F1F0;&#x1F1EC;',
						'flag-kh'                                                => '&#x1F1F0;&#x1F1ED;',
						'flag-ki'                                                => '&#x1F1F0;&#x1F1EE;',
						'flag-km'                                                => '&#x1F1F0;&#x1F1F2;',
						'flag-kn'                                                => '&#x1F1F0;&#x1F1F3;',
						'flag-kp'                                                => '&#x1F1F0;&#x1F1F5;',
						'flag-kr'                                                => '&#x1F1F0;&#x1F1F7;',
						'kr'                                                     => '&#x1F1F0;&#x1F1F7;',
						'flag-kw'                                                => '&#x1F1F0;&#x1F1FC;',
						'flag-ky'                                                => '&#x1F1F0;&#x1F1FE;',
						'flag-kz'                                                => '&#x1F1F0;&#x1F1FF;',
						'flag-la'                                                => '&#x1F1F1;&#x1F1E6;',
						'flag-lb'                                                => '&#x1F1F1;&#x1F1E7;',
						'flag-lc'                                                => '&#x1F1F1;&#x1F1E8;',
						'flag-li'                                                => '&#x1F1F1;&#x1F1EE;',
						'flag-lk'                                                => '&#x1F1F1;&#x1F1F0;',
						'flag-lr'                                                => '&#x1F1F1;&#x1F1F7;',
						'flag-ls'                                                => '&#x1F1F1;&#x1F1F8;',
						'flag-lt'                                                => '&#x1F1F1;&#x1F1F9;',
						'flag-lu'                                                => '&#x1F1F1;&#x1F1FA;',
						'flag-lv'                                                => '&#x1F1F1;&#x1F1FB;',
						'flag-ly'                                                => '&#x1F1F1;&#x1F1FE;',
						'flag-ma'                                                => '&#x1F1F2;&#x1F1E6;',
						'flag-mc'                                                => '&#x1F1F2;&#x1F1E8;',
						'flag-md'                                                => '&#x1F1F2;&#x1F1E9;',
						'flag-me'                                                => '&#x1F1F2;&#x1F1EA;',
						'flag-mf'                                                => '&#x1F1F2;&#x1F1EB;',
						'flag-mg'                                                => '&#x1F1F2;&#x1F1EC;',
						'flag-mh'                                                => '&#x1F1F2;&#x1F1ED;',
						'flag-mk'                                                => '&#x1F1F2;&#x1F1F0;',
						'flag-ml'                                                => '&#x1F1F2;&#x1F1F1;',
						'flag-mm'                                                => '&#x1F1F2;&#x1F1F2;',
						'flag-mn'                                                => '&#x1F1F2;&#x1F1F3;',
						'flag-mo'                                                => '&#x1F1F2;&#x1F1F4;',
						'flag-mp'                                                => '&#x1F1F2;&#x1F1F5;',
						'flag-mq'                                                => '&#x1F1F2;&#x1F1F6;',
						'flag-mr'                                                => '&#x1F1F2;&#x1F1F7;',
						'flag-ms'                                                => '&#x1F1F2;&#x1F1F8;',
						'flag-mt'                                                => '&#x1F1F2;&#x1F1F9;',
						'flag-mu'                                                => '&#x1F1F2;&#x1F1FA;',
						'flag-mv'                                                => '&#x1F1F2;&#x1F1FB;',
						'flag-mw'                                                => '&#x1F1F2;&#x1F1FC;',
						'flag-mx'                                                => '&#x1F1F2;&#x1F1FD;',
						'flag-my'                                                => '&#x1F1F2;&#x1F1FE;',
						'flag-mz'                                                => '&#x1F1F2;&#x1F1FF;',
						'flag-na'                                                => '&#x1F1F3;&#x1F1E6;',
						'flag-nc'                                                => '&#x1F1F3;&#x1F1E8;',
						'flag-ne'                                                => '&#x1F1F3;&#x1F1EA;',
						'flag-nf'                                                => '&#x1F1F3;&#x1F1EB;',
						'flag-ng'                                                => '&#x1F1F3;&#x1F1EC;',
						'flag-ni'                                                => '&#x1F1F3;&#x1F1EE;',
						'flag-nl'                                                => '&#x1F1F3;&#x1F1F1;',
						'flag-no'                                                => '&#x1F1F3;&#x1F1F4;',
						'flag-np'                                                => '&#x1F1F3;&#x1F1F5;',
						'flag-nr'                                                => '&#x1F1F3;&#x1F1F7;',
						'flag-nu'                                                => '&#x1F1F3;&#x1F1FA;',
						'flag-nz'                                                => '&#x1F1F3;&#x1F1FF;',
						'flag-om'                                                => '&#x1F1F4;&#x1F1F2;',
						'flag-pa'                                                => '&#x1F1F5;&#x1F1E6;',
						'flag-pe'                                                => '&#x1F1F5;&#x1F1EA;',
						'flag-pf'                                                => '&#x1F1F5;&#x1F1EB;',
						'flag-pg'                                                => '&#x1F1F5;&#x1F1EC;',
						'flag-ph'                                                => '&#x1F1F5;&#x1F1ED;',
						'flag-pk'                                                => '&#x1F1F5;&#x1F1F0;',
						'flag-pl'                                                => '&#x1F1F5;&#x1F1F1;',
						'flag-pm'                                                => '&#x1F1F5;&#x1F1F2;',
						'flag-pn'                                                => '&#x1F1F5;&#x1F1F3;',
						'flag-pr'                                                => '&#x1F1F5;&#x1F1F7;',
						'flag-ps'                                                => '&#x1F1F5;&#x1F1F8;',
						'flag-pt'                                                => '&#x1F1F5;&#x1F1F9;',
						'flag-pw'                                                => '&#x1F1F5;&#x1F1FC;',
						'flag-py'                                                => '&#x1F1F5;&#x1F1FE;',
						'flag-qa'                                                => '&#x1F1F6;&#x1F1E6;',
						'flag-re'                                                => '&#x1F1F7;&#x1F1EA;',
						'flag-ro'                                                => '&#x1F1F7;&#x1F1F4;',
						'flag-rs'                                                => '&#x1F1F7;&#x1F1F8;',
						'flag-ru'                                                => '&#x1F1F7;&#x1F1FA;',
						'ru'                                                     => '&#x1F1F7;&#x1F1FA;',
						'flag-rw'                                                => '&#x1F1F7;&#x1F1FC;',
						'flag-sa'                                                => '&#x1F1F8;&#x1F1E6;',
						'flag-sb'                                                => '&#x1F1F8;&#x1F1E7;',
						'flag-sc'                                                => '&#x1F1F8;&#x1F1E8;',
						'flag-sd'                                                => '&#x1F1F8;&#x1F1E9;',
						'flag-se'                                                => '&#x1F1F8;&#x1F1EA;',
						'flag-sg'                                                => '&#x1F1F8;&#x1F1EC;',
						'flag-sh'                                                => '&#x1F1F8;&#x1F1ED;',
						'flag-si'                                                => '&#x1F1F8;&#x1F1EE;',
						'flag-sj'                                                => '&#x1F1F8;&#x1F1EF;',
						'flag-sk'                                                => '&#x1F1F8;&#x1F1F0;',
						'flag-sl'                                                => '&#x1F1F8;&#x1F1F1;',
						'flag-sm'                                                => '&#x1F1F8;&#x1F1F2;',
						'flag-sn'                                                => '&#x1F1F8;&#x1F1F3;',
						'flag-so'                                                => '&#x1F1F8;&#x1F1F4;',
						'flag-sr'                                                => '&#x1F1F8;&#x1F1F7;',
						'flag-ss'                                                => '&#x1F1F8;&#x1F1F8;',
						'flag-st'                                                => '&#x1F1F8;&#x1F1F9;',
						'flag-sv'                                                => '&#x1F1F8;&#x1F1FB;',
						'flag-sx'                                                => '&#x1F1F8;&#x1F1FD;',
						'flag-sy'                                                => '&#x1F1F8;&#x1F1FE;',
						'flag-sz'                                                => '&#x1F1F8;&#x1F1FF;',
						'flag-ta'                                                => '&#x1F1F9;&#x1F1E6;',
						'flag-tc'                                                => '&#x1F1F9;&#x1F1E8;',
						'flag-td'                                                => '&#x1F1F9;&#x1F1E9;',
						'flag-tf'                                                => '&#x1F1F9;&#x1F1EB;',
						'flag-tg'                                                => '&#x1F1F9;&#x1F1EC;',
						'flag-th'                                                => '&#x1F1F9;&#x1F1ED;',
						'flag-tj'                                                => '&#x1F1F9;&#x1F1EF;',
						'flag-tk'                                                => '&#x1F1F9;&#x1F1F0;',
						'flag-tl'                                                => '&#x1F1F9;&#x1F1F1;',
						'flag-tm'                                                => '&#x1F1F9;&#x1F1F2;',
						'flag-tn'                                                => '&#x1F1F9;&#x1F1F3;',
						'flag-to'                                                => '&#x1F1F9;&#x1F1F4;',
						'flag-tr'                                                => '&#x1F1F9;&#x1F1F7;',
						'flag-tt'                                                => '&#x1F1F9;&#x1F1F9;',
						'flag-tv'                                                => '&#x1F1F9;&#x1F1FB;',
						'flag-tw'                                                => '&#x1F1F9;&#x1F1FC;',
						'flag-tz'                                                => '&#x1F1F9;&#x1F1FF;',
						'flag-ua'                                                => '&#x1F1FA;&#x1F1E6;',
						'flag-ug'                                                => '&#x1F1FA;&#x1F1EC;',
						'flag-um'                                                => '&#x1F1FA;&#x1F1F2;',
						'flag-us'                                                => '&#x1F1FA;&#x1F1F8;',
						'us'                                                     => '&#x1F1FA;&#x1F1F8;',
						'flag-uy'                                                => '&#x1F1FA;&#x1F1FE;',
						'flag-uz'                                                => '&#x1F1FA;&#x1F1FF;',
						'flag-va'                                                => '&#x1F1FB;&#x1F1E6;',
						'flag-vc'                                                => '&#x1F1FB;&#x1F1E8;',
						'flag-ve'                                                => '&#x1F1FB;&#x1F1EA;',
						'flag-vg'                                                => '&#x1F1FB;&#x1F1EC;',
						'flag-vi'                                                => '&#x1F1FB;&#x1F1EE;',
						'flag-vn'                                                => '&#x1F1FB;&#x1F1F3;',
						'flag-vu'                                                => '&#x1F1FB;&#x1F1FA;',
						'flag-wf'                                                => '&#x1F1FC;&#x1F1EB;',
						'flag-ws'                                                => '&#x1F1FC;&#x1F1F8;',
						'flag-xk'                                                => '&#x1F1FD;&#x1F1F0;',
						'flag-ye'                                                => '&#x1F1FE;&#x1F1EA;',
						'flag-yt'                                                => '&#x1F1FE;&#x1F1F9;',
						'flag-za'                                                => '&#x1F1FF;&#x1F1E6;',
						'flag-zm'                                                => '&#x1F1FF;&#x1F1F2;',
						'flag-zw'                                                => '&#x1F1FF;&#x1F1FC;',
						'man-man-boy'                                            => '&#x1F468;&#x200D;&#x1F468;&#x200D;&#x1F466;',
						'man-man-boy-boy'                                        => '&#x1F468;&#x200D;&#x1F468;&#x200D;&#x1F466;&#x200D;&#x1F466;',
						'man-man-girl'                                           => '&#x1F468;&#x200D;&#x1F468;&#x200D;&#x1F467;',
						'man-man-girl-boy'                                       => '&#x1F468;&#x200D;&#x1F468;&#x200D;&#x1F467;&#x200D;&#x1F466;',
						'man-man-girl-girl'                                      => '&#x1F468;&#x200D;&#x1F468;&#x200D;&#x1F467;&#x200D;&#x1F467;',
						'man-woman-boy-boy'                                      => '&#x1F468;&#x200D;&#x1F469;&#x200D;&#x1F466;&#x200D;&#x1F466;',
						'man-woman-girl'                                         => '&#x1F468;&#x200D;&#x1F469;&#x200D;&#x1F467;',
						'man-woman-girl-boy'                                     => '&#x1F468;&#x200D;&#x1F469;&#x200D;&#x1F467;&#x200D;&#x1F466;',
						'man-woman-girl-girl'                                    => '&#x1F468;&#x200D;&#x1F469;&#x200D;&#x1F467;&#x200D;&#x1F467;',
						'man-heart-man'                                          => '&#x1F468;&#x200D;&#x2764;&#xFE0F;&#x200D;&#x1F468;',
						'man-kiss-man'                                           => '&#x1F468;&#x200D;&#x2764;&#xFE0F;&#x200D;&#x1F48B;&#x200D;&#x1F468;',
						'woman-woman-boy'                                        => '&#x1F469;&#x200D;&#x1F469;&#x200D;&#x1F466;',
						'woman-woman-boy-boy'                                    => '&#x1F469;&#x200D;&#x1F469;&#x200D;&#x1F466;&#x200D;&#x1F466;',
						'woman-woman-girl'                                       => '&#x1F469;&#x200D;&#x1F469;&#x200D;&#x1F467;',
						'woman-woman-girl-boy'                                   => '&#x1F469;&#x200D;&#x1F469;&#x200D;&#x1F467;&#x200D;&#x1F466;',
						'woman-woman-girl-girl'                                  => '&#x1F469;&#x200D;&#x1F469;&#x200D;&#x1F467;&#x200D;&#x1F467;',
						'woman-heart-woman'                                      => '&#x1F469;&#x200D;&#x2764;&#xFE0F;&#x200D;&#x1F469;',
						'woman-kiss-woman'                                       => '&#x1F469;&#x200D;&#x2764;&#xFE0F;&#x200D;&#x1F48B;&#x200D;&#x1F469',
					];

					/**
					 * Insert emojis.
					 */
					preg_match_all( "/:([a-zA-Z0-9'_+-]+):/", $message_text, $emojis );
					foreach ( $emojis[1] as $emojiname ) {
						/**
						 * Check if it is a valid Emoji.
						 */
						if ( isset( $emoji_unicode[ $emojiname ] ) ) {
							$message_text = str_replace( ':' . $emojiname . ':', $emoji_unicode[ $emojiname ], $message_text );
						}
					}

					$reactions_markup = '';
					/**
					 * Check for reactions.
					 */
					if ( array_key_exists( 'reactions', $message ) ) {
						/**
						 * Open ul element.
						 */
						$reactions_markup .= '<ul class="reactions">';

						$reactions_array = [];
						/**
						 * Loop through the reactions.
						 */
						foreach ( $message['reactions'] as $reaction ) {
							/**
							 * Check if we have a matching emoji.
							 */
							if ( isset( $emoji_unicode[ $reaction['name'] ] ) ) {
								/**
								 * Get emoji HTML hex code.
								 */
								$emoji_hex = $emoji_unicode[ $reaction['name'] ];

								/**
								 * Get users count
								 */
								$users_count = count( $reaction['users'] );

								/**
								 * Check if we have only one user.
								 */
								if ( 1 === $users_count ) {
									$users_string = $users_by_id[ $reaction['users'][0] ]['name'];
								} else {
									$users_string = '';

									/**
									 * Loop through the users and build users string.
									 */
									for ( $i = 1; $i < $users_count + 1; $i ++ ) {
										/**
										 * Get the current user ID.
										 */
										$user_id = $reaction['users'][ $i - 1 ];

										/**
										 * Get user name.
										 */
										$user = $users_by_id[ $user_id ]['name'];

										/**
										 * Check for first loop run.
										 */
										if ( 1 === $i ) {
											$users_string .= $user;
										} else {
											if ( $i < $users_count ) {
												$users_string .= sprintf( ', %s', $user );
											} else {
												$users_string .= sprintf( ' und %s', $user );
											} // End if().
										} // End if().
									} // End for().
								} // End if().

								/**
								 * Build the list element.
								 */
								$reactions_markup .= sprintf(
									'<li><span class="emoji">%s</span> <span class="count"><span class="screen-reader-text">Zahl der Nutzer, die diese Emoji-Reaktion genutzt haben: </span> %s</span> <span class="users">%s <span>%s</span></span></li>',
									$emoji_hex,
									$reaction['count'],
									$users_string,
									( $users_count > 1 ? sprintf( 'haben mit :%s: reagiert.', $reaction['name'] ) : sprintf( 'hat mit :%s: reagiert.', $reaction['name'] ) )
								);
							} // End if().
						} // End foreach().

						/**
						 * Add closing ul tag.
						 */
						$reactions_markup .= '</ul>';
					} // End if().

					/**
					 * Check if this is a message with a thread or a file upload with comments.
					 */
					if (
						array_key_exists( 'replies', $message )
						|| ( 'file_share' === $message_subtype
						     && (
							     ( $message['file']['comments_count'] !== 0 && ! isset( $message['file']['initial_comment'] ) )
							     || ( $message['file']['comments_count'] > 1 && isset( $message['file']['initial_comment'] ) )
						     )
						)
					) {
						/**
						 * Check if this is a file share.
						 */
						if ( 'file_share' === $message_subtype ) {
							/**
							 * Get file ID.
							 */
							$file_id = $message['file']['id'];

							/**
							 * Set bool true.
							 */
							$xml_export_array[ $channel ][ $message_date_english_format ]["$message_timestamp-$file_id"]['is_thread'] = true;
						} else {
							/**
							 * Set bool true that this is a thread message.
							 */
							$xml_export_array[ $channel ][ $message_date_english_format ][ $message_timestamp ]['is_thread'] = true;
						}

						/**
						 * Create the message markup. Does not close the wrapper div — we will do that
						 * later after the last thread reply markup was printed.
						 */
						$html_message = "<div id='message-$message_timestamp' class='thread'>$gravatar<div class='message'><div class='username'>$username</div><div class='time'><a href='#message-$message_timestamp'>$message_date</a></div><div class='msg'>$message_text</div></div>$reactions_markup<div class='replies'>";
					} else {
						/**
						 * Create the message markup.
						 */
						$html_message = "<div id='message-$message_timestamp'>$gravatar<div class='message'><div class='username'>$username</div><div class='time'><a href='#message-$message_timestamp'>$message_date</a></div><div class='msg'>$message_text</div></div>$reactions_markup</div>";
					}

					/**
					 * Check if we have a reply.
					 */
					if ( array_key_exists( 'parent_user_id', $message ) || 'file_comment' === $message_subtype ) {
						/**
						 * Check for thread reply.
						 */
						if ( array_key_exists( 'parent_user_id', $message ) ) {
							/**
							 * Get the start post timestamp.
							 */
							$thread_timestamp = $message['thread_ts'];

							/**
							 * Get date from thread start post.
							 */
							$thread_date_english_format = date( 'Y-m-d', $thread_timestamp );

							/**
							 * Save it in the array of the thread start post.
							 */
							$xml_export_array[ $channel ][ $thread_date_english_format ][ $thread_timestamp ]['replies'][ $message_timestamp ]['message'] = $html_message;
						} else {
							/**
							 * It is a file comment!
							 */

							/**
							 * Get file ID.
							 */
							$file_id = $message['file']['id'];

							/**
							 * Loop through the dates of the channel and search for message
							 * timestamps with the file id.
							 */
							foreach ( $xml_export_array[ $channel ] as $this_message_date => $date_messages ) {
								foreach ( $date_messages as $message_timestamp => $message_array ) {
									if ( preg_match( "/-$file_id$/", $message_timestamp ) ) {
										/**
										 * Insert the file comment into the array of the file share message.
										 */
										$xml_export_array[ $channel ][ $this_message_date ][ $message_timestamp ]['replies'][ $message['comment']['timestamp'] ]['message'] = $html_message;
									}
								}
							}
						} // End if().
					} else {
						/**
						 * Check if we have a file share post with comments other than the initial comment.
						 */
						if ( 'file_share' === $message_subtype
						     && (
							     ( $message['file']['comments_count'] !== 0 && ! isset( $message['file']['initial_comment'] ) )
							     || ( $message['file']['comments_count'] > 1 && isset( $message['file']['initial_comment'] ) )
						     )
						) {
							/**
							 * Get file ID. This is the identifier we need later
							 * to get the file comments to the right place.
							 */
							$file_id = $message['file']['id'];

							/**
							 * Save it in the xml export array.
							 */
							$xml_export_array[ $channel ][ $message_date_english_format ]["$message_timestamp-$file_id"]['message'] = $html_message;
						} else {
							/**
							 * Save it in the xml export array.
							 */
							$xml_export_array[ $channel ][ $message_date_english_format ][ $message_timestamp ]['message'] = $html_message;
						}
					} // End if().
				} // End foreach().
			} // End if().
		} // End foreach().
	} // End if().
} // End foreach().

krsort( $xml_export_array );
$xml_file_markup               = new DOMDocument( "1.0", "UTF-8" );
$xml_file_markup->formatOutput = true;
$rss_element                   = $xml_file_markup->createElement( 'rss' );
$xml_file_markup->appendChild( $rss_element );
$xml_file_markup->createAttributeNS( 'http://wordpress.org/export/1.2/excerpt/', 'excerpt:attr' );
$xml_file_markup->createAttributeNS( 'http://purl.org/rss/1.0/modules/content/', 'content:attr' );
$xml_file_markup->createAttributeNS( 'http://wellformedweb.org/CommentAPI/', 'wfw:attr' );
$xml_file_markup->createAttributeNS( 'http://purl.org/dc/elements/1.1/', 'dc:attr' );
$xml_file_markup->createAttributeNS( 'http://wordpress.org/export/1.2/', 'wp:attr' );
$channel_element = $xml_file_markup->createElement( 'channel' );
$rss_element->appendChild( $channel_element );
$title_element = $xml_file_markup->createElement( 'title', 'Slack-Archiv' );
$channel_element->appendChild( $title_element );
$description_element = $xml_file_markup->createElement( 'description', 'Slack-Archiv des DEWP-Teams' );
$channel_element->appendChild( $description_element );
$pubDate_element = $xml_file_markup->createElement( 'pubDate', 'Thu, 28 Jul 2016 07:23:02 +0000' );
$channel_element->appendChild( $pubDate_element );
$language_element = $xml_file_markup->createElement( 'language', 'de-DE' );
$channel_element->appendChild( $language_element );
$wxr_version_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:wxr_version', '1.2' );
$channel_element->appendChild( $wxr_version_element );
$base_site_url_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:base_site_url', 'https://example.com/' );
$channel_element->appendChild( $base_site_url_element );
$base_blog_url_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:base_blog_url', 'https://example.com/' );
$channel_element->appendChild( $base_blog_url_element );
$author_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:author' );
$channel_element->appendChild( $author_element );
$author_id_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:author_id', 1 );
$author_element->appendChild( $author_id_element );
$author_login_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:author_login' );
$author_element->appendChild( $author_login_element );
$author_login_element->appendChild( $xml_file_markup->createCDATASection( 'author' ) );
$author_email_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:author_email' );
$author_element->appendChild( $author_email_element );
$author_email_element->appendChild( $xml_file_markup->createCDATASection( 'author@example.com' ) );
$author_display_name_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:author_display_name' );
$author_element->appendChild( $author_display_name_element );
$author_display_name_element->appendChild( $xml_file_markup->createCDATASection( 'Author' ) );
$author_first_name_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:author_first_name' );
$author_element->appendChild( $author_first_name_element );
$author_last_name_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:author_last_name' );
$author_element->appendChild( $author_last_name_element );
foreach ( $xml_export_array as $key => $value ) {
	$channel = $key;
	foreach ( $value as $date => $message_array ) {
		$timestamp         = strtotime( $date );
		$date              = date( 'd.m.Y', $timestamp );
		$title             = "#$channel am $date";
		$pub_date          = date( 'D, d M Y 10:00:00 +0000', $timestamp );
		$post_date         = date( 'Y-m-d 10:00:00', $timestamp );
		$post_date_gmt     = gmdate( 'Y-m-d H:i:s', strtotime( $post_date ) );
		$category_nicename = str_replace( '_', '-', $channel );
		$post_markup       = '<div class="messages" style="clear:both;">';
		foreach ( $message_array as $message ) {
			/**
			 * Check if we have a message with replies (thread).
			 */
			if ( isset( $message['is_thread'] ) && true === $message['is_thread'] ) {
				/**
				 * Add the markup of the thread start post.
				 */
				if ( isset( $message['message'] ) ) {
					$post_markup .= $message['message'];
				}

				/**
				 * Check if we actually have replies.
				 */
				if ( isset( $message['replies'] ) || isset( $message['file_comments'] ) ) {
					/**
					 * Loop the replies.
					 */
					foreach ( $message['replies'] as $reply ) {
						$post_markup .= $reply['message'];
					}
				}

				/**
				 * Add closing divs.
				 */
				$post_markup .= '</div></div>';
			} else {
				/**
				 * Just insert the markup from the message.
				 */
				if ( isset( $message['message'] ) ) {
					$post_markup .= $message['message'];
				}
			}
		}
		$post_markup  .= '</div>';
		$item_element = $xml_file_markup->createElement( 'item' );
		$channel_element->appendChild( $item_element );
		$title_element = $xml_file_markup->createElement( 'title', $title );
		$item_element->appendChild( $title_element );
		$pubDate_element = $xml_file_markup->createElement( 'pubDate', $pub_date );
		$item_element->appendChild( $pubDate_element );
		$creator_element = $xml_file_markup->createElementNS( 'http://purl.org/dc/elements/1.1/', 'dc:creator' );
		$item_element->appendChild( $creator_element );
		$creator_element->appendChild( $xml_file_markup->createCDATASection( 'florian' ) );
		$content_element = $xml_file_markup->createElementNS( 'http://purl.org/rss/1.0/modules/content/', 'content:encoded' );
		$item_element->appendChild( $content_element );
		$content_element->appendChild( $xml_file_markup->createCDATASection( $post_markup ) );
		$excerpt_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/excerpt/', 'excerpt:encoded' );
		$item_element->appendChild( $excerpt_element );
		$excerpt_element->appendChild( $xml_file_markup->createCDATASection( '' ) );
		$post_date_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:post_date' );
		$item_element->appendChild( $post_date_element );
		$post_date_element->appendChild( $xml_file_markup->createCDATASection( $post_date ) );
		$post_date_gmt_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:post_date_gmt' );
		$item_element->appendChild( $post_date_gmt_element );
		$post_date_gmt_element->appendChild( $xml_file_markup->createCDATASection( $post_date_gmt ) );
		$comment_status_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:comment_status' );
		$item_element->appendChild( $comment_status_element );
		$comment_status_element->appendChild( $xml_file_markup->createCDATASection( 'closed' ) );
		$ping_status_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:ping_status' );
		$item_element->appendChild( $ping_status_element );
		$ping_status_element->appendChild( $xml_file_markup->createCDATASection( 'closed' ) );
		$status_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:status' );
		$item_element->appendChild( $status_element );
		$status_element->appendChild( $xml_file_markup->createCDATASection( 'publish' ) );
		$post_parent_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:post_parent', '0' );
		$item_element->appendChild( $post_parent_element );
		$menu_order_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:menu_order', '0' );
		$item_element->appendChild( $menu_order_element );
		$post_type_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:post_type' );
		$item_element->appendChild( $post_type_element );
		$post_type_element->appendChild( $xml_file_markup->createCDATASection( 'post' ) );
		$post_password_element = $xml_file_markup->createElementNS( 'http://wordpress.org/export/1.2/', 'wp:post_password' );
		$item_element->appendChild( $post_password_element );
		$post_password_element->appendChild( $xml_file_markup->createCDATASection( '' ) );
		$category_element = $xml_file_markup->createElement( 'category' );
		$item_element->appendChild( $category_element );
		$category_element->setAttribute( 'domain', 'category' );
		$category_element->setAttribute( 'nicename', $category_nicename );
		$category_element->appendChild( $xml_file_markup->createCDATASection( "#$channel" ) );
	}
}

/**
 * Save the file with proper naming.
 */
if ( '' !== $start_date_timestamp && '' !== $end_date_timestamp ) {
	$xml_file_markup->save( "slack-export-wxr-$start_date-$end_date.xml" );
} elseif ( '' !== $start_date_timestamp ) {
	$xml_file_markup->save( "slack-export-wxr-start_date-$start_date.xml" );
} elseif ( '' !== $end_date_timestamp ) {
	$xml_file_markup->save( "slack-export-wxr-end_date-$end_date.xml" );
} else {
	$xml_file_markup->save( "slack-export-wxr.xml" );
}
