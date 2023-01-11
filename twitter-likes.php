<?php
	
error_reporting( E_ERROR | E_WARNING );
	
require __DIR__ . '/vendor/autoload.php';

use Abraham\TwitterOAuth\TwitterOAuth;

$dotenv = Dotenv\Dotenv::createImmutable( __DIR__ );
$dotenv->load();

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
	'tweet.fields' => 'id,text,created_at',
];

do {
		
	if ( $deleted && !empty( $params['pagination_token'] ) ) {
		
		echo "Sleeping for 15 minutes...\n";
		sleep( 960 ); //Rate limited to 50 likes every 15 minutes, so sleep for 16 minutes
		
	}
	
	$twitter = new TwitterOAuth( $_ENV['TWITTER_CONSUMER_KEY'], $_ENV['TWITTER_CONSUMER_KEY_SECRET'], $_ENV['TWITTER_ACCESS_TOKEN'], $_ENV['TWITTER_ACCESS_TOKEN_SECRET'] );
	$twitter->setApiVersion( '2' );
	
	echo "Getting " . ( empty( $params['pagination_token'] ) ? 'first' : $params['pagination_token'] ) . " likes\n";
	
	$likes = get_likes( $params );
	
	if ( !empty( $likes->errors ) ) {
		
		error_log( 'Error encountered while trying to retrieve likes' );
		die( "Error encountered while trying to retrieve likes\n" );
		
	}
	
	if ( $likes->meta->result_count ) {
			
		$deleted = false;
		
		foreach( $likes->data as $like ) {
					
			if ( ! in_array( $like->id, $keep ) && strtotime( $like->created_at ) < strtotime( $endtime ) ) {
				
				echo 'Deleting ' . $like->id  . "...\n";
				
				$delete = delete_like( $like->id );
				
				if ( !empty( $delete->errors ) ) {
					
					error_log( 'Error encountered while trying to delete like' );
					die( "Error encountered while trying to delete like\n" );
					
				}
				
				$deleted = true;
				
			}
			
		}
		
		$params['pagination_token'] = !empty( $likes->meta->next_token ) && $max_results == $likes->meta->result_count ? $likes->meta->next_token : false;
			
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

function get_likes( $params ) {
	
	global $twitter;
	return $twitter->get( 'users/' . $_ENV['TWITTER_USER_ID'] . '/liked_tweets', $params );
	
}

function delete_like( $id ) {
	
	global $twitter;
	return $twitter->delete( 'users/' . $_ENV['TWITTER_USER_ID'] . '/likes/' . $id );
	
}