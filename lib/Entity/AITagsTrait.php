<?php
/**
Alex Huang
*/


namespace Xibo\Entity;


use Xibo\Helper\ObjectVars;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Helper\GUIDv4;
use Xibo\Exception\EntityGuidNotFoundException;

/**
 * Class MetadataTrait
 * used by some entities which owns tag and metadata
 An entity owns metadata which can be from its parent or user input.

 * @package Xibo\Entity
 */
trait AITagsTrait
{
// I'll need 
/*
MongoDB storage DateService
GUID for every entity needs metadata (change original database)
function to get meta-tags from mongodb
function to update meta-tags to mongodb
function to add meta-tags to mongodb
function to delete meta-tag to mongodb
function to convert tags (json) to Xibo tags list
function to search tags
function to compare tags
function to calculate tags similarities


for an ai-tag, it includes tag-name and score
*/
	// tags created for ai comparison purpose
	// tags has score keyname => [score=>scorevalue]
	private $ai_tags = [];

	// 
	private $ai_jsonexclude = [];
	// the profile (raw data) for ai_tags
	##private $ai_profiles = [];

	// do I need guid of every object?
	// guid is a string here.
	private $entityguid;


	function self_create_entity_guid()
	{
		if ($entityguid == null)
		{
			$entityguid = GUIDv4();
		}
	}

	function get_entity_guid()
	{
		return $entityguid;
	}
	// 
	// find ai_tags
	function has_aitag($key)
	{
		if (array_key_exists($key, $ai_tags))
		{
			return true;
		}
		return false;
	}

	// notice, every tags is a tuple like [keyname -> keyscore]
	function add_aitags($key, $score)
	{
	    if ($key == null || $key == '')
            throw new \InvalidArgumentException(__('tag information incorrect'));	
		if ($key == null || $key == '' || $score < 0 || $score > 1)
            throw new \InvalidArgumentException(__('tag score incorrect'));

		if (has_aitags($key) == false)
		{
			$ai_tags[] = array('tagname' => array('tagscore' => $score));

		}
        
		return $ai_tags.count();
	}

	function delete_aitag($key)
	{
	    if ($key == null || $key == '')
            throw new \InvalidArgumentException(__('tag information incorrect'));
		unset($ai_tags[$key]);		
	}

	function clear_aitags()
	{
		unset($ai_tags);
	}

    /**
     * Json Serialize
     * @return array
     */
	function aitags_to_json()
	{
        $exclude = $this->ai_jsonexclude;

        $properties = ObjectVars::getObjectVars($this);
        $json = [];
        foreach ($ai_tags as $key => $score) 
		{
            if (!in_array($key, $exclude))
			{
                $json[$key] = $value;
            }
        }
        return $json;
	}
}
?>