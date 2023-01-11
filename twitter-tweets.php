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

do {
		
	$twitter = new TwitterOAuth( $_ENV['TWITTER_CONSUMER_KEY'], $_ENV['TWITTER_CONSUMER_KEY_SECRET'], $_ENV['TWITTER_ACCESS_TOKEN'], $_ENV['TWITTER_ACCESS_TOKEN_SECRET'] );
	$twitter->setApiVersion( '2' );
	
	echo "Getting " . ( empty( $params['pagination_token'] ) ? 'first' : $params['pagination_token'] ) . " tweets\n";
	
	$tweets = get_tweets( $params );
	
	if ( !empty( $tweets->errors ) ) {
		
		error_log( 'Error encountered while trying to retrieve tweets' );
		die( "Error encountered while trying to retrieve tweets\n" );
		
	}
		
	if ( $tweets->meta->result_count ) {
		
		foreach( $tweets->data as $tweet ) {
			
			if ( empty( $tweets_db->findBy( [ 'id', '=', $tweet->id ] ) ) ) {
				
				$tweets_db->insert(
					[
						'id'   => $tweet->id,
						'text' => $tweet->text,
					]
				);
				
			}
			
			if ( ! in_array( $tweet->id, $keep ) ) {
				
				echo 'Deleting ' . $tweet->id  . "...\n";
				
				$delete = delete_tweet( $tweet->id );
				
				if ( !empty( $delete->errors ) ) {
					
					error_log( 'Error encountered while trying to delete tweet' );
					die( "Error encountered while trying to delete tweet\n" );
					
				}
				
			}
			
		}
		
		$params['pagination_token'] = !empty( $tweets->meta->next_token ) && $max_results == $tweets->meta->result_count ? $tweets->meta->next_token : false;
			
	} else {
		
		$params['pagination_token'] = false;
		
	}
	
	if ( !empty( $params['pagination_token'] ) ) {
		
		echo "Sleeping for 15 minutes...\n";
		sleep( 960 ); //Rate limited to 50 tweets every 15 minutes, so sleep for 16 minutes
		
	}
	
		
} while( !empty( $params['pagination_token'] ) );

echo "Finished\n";

exit;

function get_tweets( $params ) {
	
	global $twitter;
	return $twitter->get( 'users/' . $_ENV['TWITTER_USER_ID'] . '/tweets', $params );
	
}

function delete_tweet( $id ) {
	
	global $twitter;
	return $twitter->delete( 'tweets/' . $id );
	
}
