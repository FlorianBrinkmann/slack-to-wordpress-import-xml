<?php

/**
 * Converting Slack export to XML file, ready for WordPress import.
 * Needs php_mbstring extension.
 *
 * With $_GET['start'] and $_GET['end'] you can set a range of days for the xml
 * or, on the command line: php slack-to-xml.php start="01.01.2016" end="05.01.2016".
 * You can also set either start or end.
 *
 * @link https://gist.github.com/levelsio/122907e95956602e5c09
 */
class Slack_To_WordPress_Converter {
	/**
	 * @var array|false Options from the command line.
	 */
	private $cli_options;

	/**
	 * @var string|false Start date timestamp or false if no start date
	 * was set.
	 */
	private $start_date_timestamp;

	/**
	 * @var string|false End date timestamp or false if no end date
	 * was set.
	 */
	private $end_date_timestamp;

	/**
	 * @var array Array with all users from the slack team.
	 */
	private $users;

	/**
	 * @var array Array with all channels from the slack team.
	 */
	private $channels;

	/**
	 * Slack_To_WordPress_Converter constructor.
	 */
	function __construct() {
		/**
		 * Get command line options.
		 */
		$this->set_cli_arguments();

		/**
		 * Set the start date.
		 */
		$this->set_start_date();

		/**
		 * Set the end date.
		 */
		$this->set_end_date();

		/**
		 * Set users.
		 */
		$this->set_users_array();

		/**
		 * Set channels.
		 */
		$this->set_channels_array();
	}

	/**
	 * Fetches options from CLI run and saves them as property.
	 */
	private function set_cli_arguments() {
		/**
		 * Get the options.
		 */
		$options = getopt(
			'',
			[
				'start::',
				'end::',
			]
		);

		/**
		 * Save them.
		 */
		$this->cli_options = $options;
	}

	/**
	 * Gets start date and sets it to property.
	 */
	private function set_start_date() {
		$start_date = '';
		/**
		 * Check if we have options from the CLI and a start date.
		 */
		if ( false !== $this->cli_options && false !== $this->cli_options['start'] ) {
			$start_date = $this->cli_options['start'];
		} elseif ( isset( $_GET['start'] ) ) {
			$start_date = $_GET['start'];
		}

		/**
		 * Set start date or false to $start_date_timestamp property.
		 */
		$this->start_date_timestamp = strtotime( $start_date );
	}

	/**
	 * Gets end date and sets it to property.
	 */
	private function set_end_date() {
		$end_date = '';
		/**
		 * Check if we have options from the CLI and a start date.
		 */
		if ( false !== $this->cli_options && false !== $this->cli_options['end'] ) {
			$end_date = $this->cli_options['end'];
		} elseif ( isset( $_GET['end'] ) ) {
			$end_date = $_GET['end'];
		}

		/**
		 * Set end date timestamp or false to $end_date_timestamp property.
		 */
		$this->end_date_timestamp = strtotime( $end_date );
	}

	/**
	 * Creates an array with all users from the slack team and saves it to property.
	 */
	private function set_users_array() {
		/**
		 * Fetch the data from users.json from the parent directory.
		 */
		$users = json_decode( file_get_contents( __DIR__ . '/../' . 'users.json' ), true );

		/**
		 * Save it.
		 */
		$this->users = $users;
	}

	/**
	 * Creates an array with all channels from the slack team and saves it to property.
	 */
	private function set_channels_array() {
		/**
		 * Fetch the data from channels.json from the parent directory.
		 */
		$channels = json_decode( file_get_contents( __DIR__ . '/../' . 'channels.json' ), true );

		/**
		 * Save it.
		 */
		$this->channels = $channels;
	}
}
