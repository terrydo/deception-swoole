<?php 
	/**
	* UTILITIES
	*/
	class Utils
	{
		public static function parseJson($string) {
		 	$data = json_decode($string);
		 	if (json_last_error() == JSON_ERROR_NONE) {
		 		return $data;
		 	}
		 	return false;
		}

		public static function randomHash($length = 15){
			return substr(md5(mt_rand()), 0, $length);
		}
	}
?>