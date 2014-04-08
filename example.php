<?php

function api_request( $url, $auth_token = null, $post_data = null ) {
  $curl = curl_init( $url );

  curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
  curl_setopt( $curl, CURLOPT_FAILONERROR, false );

  if ( $auth_token ) {
    curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Authorization: Bearer '.$auth_token ) );
  }

  if ( !empty( $post_data ) ) {
    curl_setopt( $curl, CURLOPT_POST, 1 );
    curl_setopt( $curl, CURLOPT_POSTFIELDS, $post_data );
  }

  $response = curl_exec( $curl );

  curl_close( $curl );

  return $response;
}

$auth_header       = 'auth token';
$target_blog       = 'yourblog.wordpress.com';
$new_post_endpoint = 'https://developer.wordpress.com/docs/api/1/post/sites/'.$target_blog.'/posts/new/';

$future_time = mktime( 11, 0, 0, 5, 3, 2014 );

$post = array(
	'date'    => date( 'Y-m-d\TH:i:s', $future_time ),
	'media[]' => '@photo.jpg',
	'title'   => 'My schedule blog post',
	'content' => 'This photo is going to be scheduled',
);

$response = api_request( $new_post_endpoint, $auth_header, $post );

print_r( json_decode( $response ) );
