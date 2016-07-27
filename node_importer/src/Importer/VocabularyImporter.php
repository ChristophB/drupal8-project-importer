<?php

/**
 * @file
 * Contains \Drupal\node_importer\Importer\VocabularyImporter.
 */

namespace Drupal\node_importer\Importer;

use Exception;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

/**
 * Importer for vocabularies
 * 
 * @author Christoph Beger
 */
class VocabularyImporter extends AbstractImporter {
    
    function __construct($overwrite = false) {
        $this->entities['taxonomy_vocabulary'] = [];
        $this->entities['taxonomy_term'] = [];
        
        if (isset($overwrite)) $this->overwrite = true;
    }
    
    public function import($data) {
        if (empty($data)) return;
        
        foreach ($data as $vocabulary) {
            $this->createVocabulary($vocabulary['vid'], $vocabulary['name']);
            $this->createTags($vocabulary['vid'], $vocabulary['tags']);
		    $this->setTagParents($vocabulary['vid'], $vocabulary['tags']);
        }
    }
    
    public function countCreatedVocabularies() {
        return sizeof($this->entities['taxonomy_vocabulary']);
    }
    
    public function countCreatedTags() {
        return sizeof($this->entities['taxonomy_term']);
    }
    
    /**
     * Creates a vocabulary.
     * 
     * @param $vid vid of the vocabulary
     * @param $name name of the vocabulary
     */
    public function createVocabulary($vid, $name) {
		if (is_null($vid)) throw new Exception('Error: parameter $vid missing.');
		if (is_null($name)) throw new Exception('Error: parameter $name missing.');
		
		$exists = $this->clearVocabularyIfExists($vid);
    	
    	if (!$exists) {
			$vocabulary = Vocabulary::create([
				'name'   => $name,
				'weight' => 0,
				'vid'    => $vid
			]);
			$vocabulary->save();
			$this->entities['taxonomy_vocabulary'][] = $vocabulary->id();
		}
	}
	
	/**
	 * Creates a set of Drupal tags for given vocabulary.
	 * Does not add parents to the tags, because they may not exit yet.
	 * 
	 * @param $vid vid of the vocabulary
	 * @param $tags array of tags
	 */
	public function createTags($vid, $tags) {
	    if (is_null($vid)) throw new Exception('Error: parameter $vid missing.');
	    if (empty($tags)) return;
	    
	    foreach ($tags as $tag) {
			$term = Term::create([
				'name'   => $tag['name'],
				'vid'    => $vid,
			]);
			$term->save();
			
			$this->entities['taxonomy_term'][] = $term;
		}
	}
	
	/**
	 * Creates a single Drupal tag for given vocabulary.
	 * Does not add parents to the tags, because they may not exit yet.
	 * 
	 * @param $vid vid of the vocabulary
	 * @param $name name of the tag
	 */
	public function createTag($vid, $name) {
		if (is_null($vid)) throw new Exception('Error: parameter $vid missing.');
	    if (empty($name)) return;
	    
	    $term = Term::create([
			'name' => $name,
			'vid'  => $vid,
		]);
		$term->save();
			
		$this->entities['taxonomy_term'][] = $term->id();
	}
	
	/**
	 * Checks of a vocabulary with given vid already exists
	 * and deletes all its tags if overwrite is true.
	 * 
	 * @param $vid vid of the vocabulary
	 * 
	 * @return boolean
	 */
	private function clearVocabularyIfExists($vid) {
		if ($this->vocabularyExists($vid)) {
			if ($this->overwrite) {
				$tids = $this->searchEntityIds([
	        		'entity_type' => 'taxonomy_term',
	        		'vid'         => $vid
	    		]);
				foreach ($tids as $tid) {
					$term = Term::load($tid);
					if (isset($term)) $term->delete();
				}
			} else {
				throw new Exception(
					'Error: vocabulary with vid "'. $vid. '" already exists. '
					. 'Tick "overwrite" if you want to replace it and try again.'
				);
	    	}
	    	return true;
	    }
	    return false;
	}
	
	/**
	 * Checks if a vocabulary with vid exists.
	 * 
	 * @param $vid vid to search for
	 * 
	 * @return boolean
	 */
	private function vocabularyExists($vid) {
		if (is_null($vid)) throw new Exception('Error: parameter $vid missing');
		
		$array = array_values($this->searchEntityIds([
			'vid'         => $vid,
			'entity_type' => 'taxonomy_vocabulary',
		]));
		
		if (!empty($array) && $array[0]) return true;
		
		return false;
	}
	
	/**
	 * Adds parents to all created tags.
	 * 
	 * @param $vid vid of the vocabulary to process
	 * @param $tags all tags which were created previously
	 */
	public function setTagParents($vid, $tags) {
		if (is_null($vid)) throw new Exception('Error: parameter $vid missing.');
		if (empty($tags)) return;
		
		foreach ($tags as $tag) {
			if (empty($tag['parents'])) continue;
			
			$tagEntity = Term::load($this->searchTagIdByName($vid, $tag['name']));
			
			$tagEntity->parent->setValue($this->searchTagIdsByNames(
			    array_map(
			        function($parent) use($vid) { 
			        	return [ 'vid' => $vid, 'name' => $parent ];
			        }, 
			        $tag['parents']
			    )
			));
			$tagEntity->save();
		}
	}
	
}
 
?>