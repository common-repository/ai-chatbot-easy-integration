<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * OPEN AI api search
 *
 * @param  mixed $response the empty response to return.
 * @param  mixed $body object that contains the searchterm.
 */
function ai_chatbot_easy_integration_open_ai_request( $response, $body, $type = '' ) {

	$settings = ai_chatbot_easy_integration_get_settings();

	if ( ! isset( $settings['openai_api_key'] ) || '' == $settings['openai_api_key'] ) {
		return $response;
	} else {
		$openai_api_key = $settings['openai_api_key'];
	}

	if ( is_string( $body ) ) {
		$searchterm = sanitize_text_field( $body );
	} elseif ( isset( $body->searchterm ) ) {
		$searchterm = sanitize_text_field( $body->searchterm );
	}

	$response = __( "I'm afraid that I don't know how to answer that? Try asking it a different way.", 'ai-chatbot-easy-integration' );

	$url = 'https://api.openai.com/v1/chat/completions';

	$ch = curl_init();

	if ( 'vision' == $type && filter_var( $body, FILTER_VALIDATE_URL ) ) {

		$content = "What\'s in this image?";

		$data = '{
			"model": "gpt-4-turbo",
			"messages": [
			  {
				"role": "user",
				"content": [
				  {
					"type": "text",
					"text": "' . esc_attr( $content ) . '"
				  },
				  {
					"type": "image_url",
					"image_url": {
					  "url": "' . esc_url( $body ) . '"
					}
				  }
				]
			  }
			],
			"max_tokens": 300
		  }';

	} else {
		$data = '{
		"model": "gpt-3.5-turbo",
		"messages": [{"role": "user", "content": "' . esc_attr( $searchterm ) . '"}],
		"temperature": 0.7
	  }';
	}

	curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_ENCODING, '' );
	curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 0 );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
	curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
	curl_setopt(
		$ch,
		CURLOPT_HTTPHEADER,
		array(
			'Content-Type: application/json',
			'apikey: ' . esc_attr( $openai_api_key ),
			'Authorization: Bearer ' . esc_attr( $openai_api_key ),
		)
	);

	$result = curl_exec( $ch );

	$code  = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$error = curl_error( $ch );

	if ( 200 == $code || 201 == $code ) {
		$json = json_decode( $result, true );

		if ( isset( $json['choices'] ) ) {
			if ( count( $json['choices'] ) > 0 && isset( $json['choices'][0]['message']['content'] ) && 11 != $json['choices'][0]['message']['content'] ) {
				$response = esc_attr( $json['choices'][0]['message']['content'] );
			}
		}
			$message = __( 'OpenAI Call Successful: ', 'ai-chatbot-easy-integration' ) . esc_attr( $response );
		if ( function_exists( 'SimpleLogger' ) ) {
			SimpleLogger()->info( $message );
		}
	} else {
		$message = __( 'OpenAI Call Failed', 'ai-chatbot-easy-integration' ) . esc_attr( $error );
		if ( function_exists( 'SimpleLogger' ) ) {
			SimpleLogger()->info( $message );
		}
	}
	return $response;
}


/**
 * OPEN AI api search
 */
function ai_chatbot_easy_integration_process_open_ai_complex_commands( $content, $type ) {

	$settings = ai_chatbot_easy_integration_get_settings();

	if ( ! isset( $settings['openai_api_key'] ) || '' == $settings['openai_api_key'] || '' == $content ) {
		return $content;
	} else {
		$openai_api_key = $settings['openai_api_key'];
	}

	// generate an image.
	if ( 'generations' == $type ) {
		$url = 'https://api.openai.com/v1/images/generations';

		$ch = curl_init();

		$data = '{
		"prompt": "' . trim( $content ) . '",
		"model": "dall-e-3",
    	"n": 1,
   		 "size": "1024x1024"
	  }';

		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_ENCODING, '' );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 0 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Content-Type: application/json',
				'apikey: ' . esc_attr( $openai_api_key ),
				'Authorization: Bearer ' . esc_attr( $openai_api_key ),
			)
		);

		$result = curl_exec( $ch );

		$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		if ( 200 == $code || 201 == $code ) {
			$json = json_decode( $result, true );

			if ( isset( $json['data'][0]['url'] ) ) {
			$url = $json['data'][0]['url'];
			echo '<img src="' . esc_url( $url ) . '">';
			die();
			}
		}
	}
	// text to speech.
	if ( 'texttospeech' == $type ) {
		$url = 'https://api.openai.com/v1/audio/speech';

		$ch = curl_init();

		$data = '{
		"model": "tts-1",
		"input": "' . trim( $content ) . '",
		"voice": "alloy"
	  }';

		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_ENCODING, '' );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 0 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Content-Type: application/json',
				'apikey: ' . esc_attr( $openai_api_key ),
				'Authorization: Bearer ' . esc_attr( $openai_api_key ),
			)
		);

		$result = curl_exec( $ch );

		$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		if ( 200 == $code || 201 == $code ) {

			echo '<source src="data:audio/mp3;base64,' . base64_encode( $result ) . '">';
			die();
		}
	}

	// moderation.
	if ( 'moderation' == $type ) {
		$url = 'https://api.openai.com/v1/moderations ';

		$ch = curl_init();

		$data = '{"input": "' . trim( $content ) . '"}';

		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_ENCODING, '' );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 0 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Content-Type: application/json',
				'apikey: ' . esc_attr( $openai_api_key ),
				'Authorization: Bearer ' . esc_attr( $openai_api_key ),
			)
		);

		$result   = curl_exec( $ch );
		$response = '';
		$code     = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		if ( 200 == $code || 201 == $code ) {
			$json = json_decode( $result, true );

			if ( isset( $json['results'][0]['categories'] ) ) {
				if ( 'true' == $json['results'][0]['categories']['harassment'] ) {
					$response .= __( 'This message may contain content that expresses, incites, or promotes harassing language towards any target.', 'ai-chatbot-easy-integration' ) . '<br>';
				}
				if ( 'true' == $json['results'][0]['categories']['hate/threatening'] ) {
					$response .= __( 'This message may contain hateful content that also includes violence or serious harm towards the targeted group based on race, gender, ethnicity, religion, nationality, sexual orientation, disability status, or caste.', 'ai-chatbot-easy-integration' ) . '<br>';
				}
				if ( 'true' == $json['results'][0]['categories']['hate'] ) {
					$response .= __( 'This message may contain content that expresses, incites, or promotes hate based on race, gender, ethnicity, religion, nationality, sexual orientation, disability status, or caste. Hateful content aimed at non-protected groups (e.g., chess players) is harassment.', 'ai-chatbot-easy-integration' ) . '<br>';
				}
				if ( 'true' == $json['results'][0]['categories']['self-harm'] ) {
					$response .= __( 'This message may contain content that promotes, encourages, or depicts acts of self-harm, such as suicide, cutting, and eating disorders.', 'ai-chatbot-easy-integration' ) . '<br>';
				}
				if ( 'true' == $json['results'][0]['categories']['self-harm/intent'] ) {
					$response .= __( 'This message may contain content that expresses, incites, or promotes hate based on race, gender, ethnicity, religion, nationality, sexual orientation, disability status, or caste. Hateful content aimed at non-protected groups (e.g., chess players) is harassment.', 'ai-chatbot-easy-integration' ) . '<br>';
				}
				if ( 'true' == $json['results'][0]['categories']['self-harm/instructions'] ) {
					$response .= __( 'This message may contain content that encourages performing acts of self-harm, such as suicide, cutting, and eating disorders, or that gives instructions or advice on how to commit such acts.', 'ai-chatbot-easy-integration' ) . '<br>';
				}
				if ( 'true' == $json['results'][0]['categories']['sexual'] ) {
					$response .= __( 'This message may contain content meant to arouse sexual excitement, such as the description of sexual activity, or that promotes sexual services (excluding sex education and wellness).', 'ai-chatbot-easy-integration' ) . '<br>';
				}

				if ( 'true' == $json['results'][0]['categories']['sexual/minors'] ) {
					$response .= __( 'This message may contain sexual content that includes an individual who is under 18 years old.', 'ai-chatbot-easy-integration' ) . '<br>';
				}

				if ( 'true' == $json['results'][0]['categories']['violence'] ) {
					$response .= __( 'This message may contain content that depicts death, violence, or physical injury.', 'ai-chatbot-easy-integration' ) . '<br>';
				}

				if ( 'true' == $json['results'][0]['categories']['violence/graphic'] ) {
					$response .= __(
						'This message may contain content that depicts death, violence, or physical injury in graphic detail.
					',
						'ai-chatbot-easy-integration'
					) . '<br>';
				}
			}
		}
		if ( '' == $response ) {
			$response = 'No potentially harmfull text was identified.';
		}
		return $response;
	}
}
