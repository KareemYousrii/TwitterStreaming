<?php
	require_once('twitteroauth.php');
	require_once('../config.php');

	class stream {

		public $favs = 0;
		public $retweets = 0;
		public $init;
		public $con;
		public $accID;
		public $debug;
		public $access_token;
		public $access_secret;

		public function __construct($access_token, $access_secret) {
			$this->init = time(); # Generate the Unix Time stamp, to which times will be later compared to.

			/* Store the access_token and the access_secret */
			$this->access_token = $access_token;
			$this->access_secret = $access_secret;
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
			$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $this->access_token, $this->access_secret);
			$url = $connection->getURL("https://userstream.twitter.com/2/user.json?with=user");

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

				 	curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this, 'writeToFile'));
					
					curl_setopt($ch, CURLOPT_TIMEOUT, 99999999);
					curl_exec($ch);
					curl_close($ch);
				}
			}
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

	$obj = new Analytics();
	$obj->initStream();









