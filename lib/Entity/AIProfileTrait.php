<?php 
/**
Alex Huang
*/


namespace Xibo\Entity;

trait AIProfileTrait
{
	// here, we still need AITag traits
	use AITagsTrait;
	// this trait lists shared properties and method for a class that accept profile and 
	// then create tags from iterator_apply

	// every profile has some Attributes
	// "source": text, url, 
	// "content": text-content, url-address, 
	// 
	private $ai_profiles = [];

	function add_text_profile($profile, $priority = 0)
	{

	}
	function add_url_profile($profile, $priority = 0)
	{
		// here, we need to get content from url, the convert it to tags
	}
	function profile_to_aitags($profile)
	{
		// we need to resolve profile and get aitags via our own web service
	}


}
?>