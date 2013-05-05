<?php
	require_once('twitteroauth.php');
	require_once('config.php');

	class Analytics {

		public $favs = 0;
		public $retweets = 0;
		public $init;
		public $con;
		public $accID;
		public $debug;
		public $access_token;
		public $access_secret;

		public function __construct($id, $dbug = FALSE) {
			$this->init = time(); # Generate the Unix Time stamp, to which times will be later compared to.
			$this->accID = $id;
			$this->debug = $dbug;

			/* Initialize db connection */
			$this->con = mysql_connect(DB_SOCKET,DB_USR,DB_PWD);
			mysql_select_db("tweetar", $this->con);

			/* Query the db for the access_token and the access_secret */
			$sql = "Select access_token, access_secret from accounts where id = " . $this->accID;
			$query = mysql_query($sql);
			$result = mysql_fetch_array($query);

			/* Store the access_token and the access_secret */
			$this->access_token = $result[0];
			$this->access_secret = $result[1];
		}
		
		/**
		 * Checks whether this account is still authorized or its access has been revoked. 
		 * @param void.
		 * @return boolean Whether or not the account is authorized.
		 * @author Omar Reudy
		 */
		public function accountIsAuthorized() {

			$connection = new TwitterOAuth('JpsL9pQYCrNIXTFqWizM3Q', 'NetGak3BkvZq4JTucIbzH7549NeClMfl7ZBEclt2cSs', $this->access_token, $this->access_secret);
			$content = $connection->get('account/verify_credentials');

            if ($connection->http_code == 200) {
                return true;
            } else {
                return false;
            }
		}

		/**
		 * Opens a twitter api stream for the specified user.
		 * @param int $accID The account for which the stream is to be opened.
		 * @param boolean[optional] $debug If set, the contents of the stream are written to a file.
		 * @return void
		 * @author KareemYousrii
		 */
		function initStream() {

			/* Retrieve the TwitterOAuth object and retrieve the authorized URL for the stream. */
			$connection = new TwitterOAuth('JpsL9pQYCrNIXTFqWizM3Q', 'NetGak3BkvZq4JTucIbzH7549NeClMfl7ZBEclt2cSs', $this->access_token, $this->access_secret);
			$url = $connection->getURL("https://userstream.twitter.com/2/user.json?with=user");
			$dbg = $this->debug;

			while(true) {

				/* Check if account is authorized */
				if(!$this->accountIsAuthorized()) {
					error_log("Access has been revoked", 0);
					break;
				}
			
				/* Initialize the CURL session. */
				$ch = curl_init();
				
				/* Set the options for the CURL session. */
				if($ch) {
					curl_setopt($ch, CURLOPT_URL, $url);
					curl_setopt($ch, CURLOPT_HEADER,0);

					 /* Check whether or not debug mode is enabled */

					 //if(!$dbg) {
					 	curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this, 'writeToFile'));
					 //} else {
						//curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this, 'writeCallback'));
					 //}
					
					curl_setopt($ch, CURLOPT_TIMEOUT, 99999999);
					curl_exec($ch);
					curl_close($ch);
				}
			}
		}

		/**
		 * Call back function, called by curl whenever any data is available in the stream.
		 * This function checks whether the incoming event is a favorite, unfavorite or 
		 * retweet and acts accordingly. It saves the number of favorites and retweets to
		 * the database every hour.
		 * @param Resource $handle The curl session handle (passed by the write back function).
		 * @param String $data The data that was read from the stream.
		 * @return int The number of bytes that were read from teh stream.
		 * @author KareemYousrii
		 */
		function writeCallback($handle, $data)
		{
			$decoded_arr = json_decode($data, true);

			if(isset($decoded_arr['event'])) {

				switch ($decoded_arr['event']) {
					case 'favorite':
						$this->favs++;
						break;
					
					case 'unfavorite':
						$this->favs--;
						break;

					default: 
						break;
				}
			} else if(isset($decoded_arr['retweeted_status'])) {
				$this->retweets++;
			}

			/* An hour has elapsed since the last time the count was updated. */
			if(($curr_time = time()) - $this->init_time >= 3600) {

				/* Create the data arrays */
				$fav_data = array(
					'account_id' => $this->accID,
					'count' => $this->favs,
					);
				
				$retweet_data = array(
					'account_id' => $this->accID,
					'count' => $this->retweets,
					);

				/* Create the sql queries */
				$fav_query = "insert into favourites_count (count, account_id) values ({$fav_data['count']}, {$fav_data['account_id']})";
				$retweet_query = "insert into retweets_count (count, account_id) values ({$retweet_data['count']}, {$retweet_data['account_id']})";

				/* Execute the sql queries */
				mysql_query($fav_query);
				mysql_query($retweet_query);

				/* Reset the data */
				$this->init_time = $curr_time;
				$this->favs = 0;
				$this->retweets = 0;
			}
			/* The number of bytes read from the stream must be specified as per php regulations */
		    return strlen($data);
		}

		/**
		 * Call back function which is called if debug mode is specified. This function
		 * writes any incoming stream data to a file.
		 * @param Resource $handle The curl session handle (passed by the write back function).
		 * @param String $data The data that was read from the stream.
		 * @return int The number of bytes that were read from teh stream.
		 * @author KareemYousrii
		 */
		function writeToFile($handle, $data)
		{
			$decoded_arr = json_decode($data, true);

	        /* Store the output in an inner buffer. */
	        ob_start();
	        print_r($decoded_arr);
	        $bufferedOut = ob_get_contents();
	        ob_end_clean();

            $fp = fopen(TEST_FILE, "a");
	        stream_set_write_buffer($fp, 0);
	        fwrite($fp,$bufferedOut);
	        fclose($fp);
	        
	        /* The number of bytes read from the stream must be specified as per php regulations */
	        return strlen($data);
		}
	}

	if(isset($argv[1]))
	{
		$accID = $argv[1];

	} else {

		die("kindly specify the account ID !\n");
	}
	

	if(isset($argv[2]))
	{
		$debug = $argv[2];

	} else {

		$debug = FALSE;
	}

	$obj = new Analytics($accID, $debug);
	$obj->initStream();









