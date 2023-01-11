<?php
	
error_reporting( E_ERROR | E_WARNING );
	
require __DIR__ . '/vendor/autoload.php';

use Abraham\TwitterOAuth\TwitterOAuth;

$dotenv = Dotenv\Dotenv::createImmutable( __DIR__ );
$dotenv->load();

$db_dir = __DIR__ . '/db';

global $tweets_db, $twitter;
$tweets_db = new \SleekDB\Store( 'tweets', $db_dir );

$endtime = date( 'Y-m-d\TH:i:s\Z', strtotime( $_ENV['TWITTER_END_TIME'] ) );
$pagination_token = false;

$keep = [
	'1196052586169847808',
	'1200558498561560576',
	'1213253502027358209',
	'1498706750593196035',
];

$max_results = 50;

$params = [
	'max_results' => $max_results,
	'end_time'    => $endtime,
];

$twitter = new TwitterOAuth( $_ENV['TWITTER_CONSUMER_KEY'], $_ENV['TWITTER_CONSUMER_KEY_SECRET'], $_ENV['TWITTER_ACCESS_TOKEN'], $_ENV['TWITTER_ACCESS_TOKEN_SECRET'] );
$twitter->setApiVersion( '2' );

$tweets = file_get_contents( 'tweets.json' );

if (false === $tweets) {
	
	error_log( 'Error reading tweets.json file' );
	die( "Error reading tweets.json file\n" );

}

$tweets = json_decode( $tweets );

if ( null === $tweets ) {

	error_log( 'Error decoding tweets json' );
	die( "Error decoding tweets json\n" );

}

$delete_count = 0;
$get_count = 0;
$process = false;
$start_with_id = '278544917691764736';

foreach( $tweets as $tweet ) {
	
	if ( $tweet->tweet->id == $start_with_id ) {
		
		$process = true;
		
	} else {
		
		echo 'Skipping ' . $tweet->tweet->id  . "...\n";
		
	}
			
	if ( $process ) {
		
		if (  strtotime( $tweet->tweet->created_at ) < strtotime( $endtime ) ) {
			
			if ( ! in_array( $tweet->tweet->id, $keep ) ) {
					
				echo 'Getting ' . $tweet->tweet->id  . "...\n";
				
				$get_count++;
				
				if ( get_tweet( $tweet->tweet->id ) ) {
					// If the tweet exists, delete it
					
					echo 'Deleting ' . $tweet->tweet->id  . "...\n";
					
					$delete = delete_tweet( $tweet->tweet->id );
					
					if ( !empty( $delete->errors ) ) {
						
						error_log( 'Error encountered while trying to delete tweet' );
						die( "Error encountered while trying to delete tweet\n" );
						
					}
				
					$delete_count++;
					
				}
				
			}
			
			if ( $get_count >= 900 ) {
				
				echo "Sleeping for 15 minutes...\n";
				sleep( 960 ); //Rate limited to 50 tweets every 15 minutes, so sleep for 16 minutes
				$get_count = 0;
				
			}
			
			if ( $delete_count >= 50 ) {
				
				echo "Sleeping for 15 minutes...\n";
				sleep( 960 ); //Rate limited to 50 tweets every 15 minutes, so sleep for 16 minutes
				$delete_count = 0;
				$get_count = 0;
				
			}
			
		}
		
	}
		
}

echo "Finished\n";

exit;

function get_tweet( $id ) {
	
	global $twitter;
	$tweet = $twitter->get( 'tweets/' . $id );
	
	if ( !empty( $tweet->errors ) ) {
		
		return false;
		
	}
	
	return true;
	
}

function delete_tweet( $id ) {
	
	global $twitter;
	return $twitter->delete( 'tweets/' . $id );
	
}
