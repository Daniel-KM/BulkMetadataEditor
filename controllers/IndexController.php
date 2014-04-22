<?php
/**
 * SedMeta
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The Sedmeta index controller class.
 *
 * @package SedMeta
 */
class SedMeta_IndexController extends Omeka_Controller_AbstractActionController
{    
    
    public function indexAction()
    {
      $this->view->form_element_options = $this->_getFormElementOptions();
      $this->view->form_compare_options = $this->_getFormCompareOptions();
      $this->view->form_collection_options = $this->_getFormCollectionOptions();
    }

    public function itemsAction()
    {
      $max = 15;
      $this->view->items = $this->_getItems($max);
    }

    public function countitemsAction()
    {
      $this->view->count = count($this->_getItems());
    }

    public function fieldsAction()
    {
      $max=7; //make this settable
      $this->view->fields = $this->_getFields($this->_getItems(),$max);
    }

    public function changesAction()
    {
      $max = 20;
      $items=$this->_getItems();
      $fields=$this->_getFields($items);
      $this->view->changes = $this->_getChanges($items,$fields,$max);
    }

    /**
     * Get an array to be used in formSelect() containing all elements.
     * 
     * @return array
     */
    private function _getFormElementOptions()
    {
        $db = $this->_helper->db->getDb();
        $sql = "
        SELECT es.name AS element_set_name, e.id AS element_id, 
        e.name AS element_name, it.name AS item_type_name
        FROM {$db->ElementSet} es 
        JOIN {$db->Element} e ON es.id = e.element_set_id 
        LEFT JOIN {$db->ItemTypesElements} ite ON e.id = ite.element_id 
        LEFT JOIN {$db->ItemType} it ON ite.item_type_id = it.id 
         WHERE es.record_type IS NULL OR es.record_type = 'Item' 
        ORDER BY es.name, it.name, e.name";
        $elements = $db->fetchAll($sql);
        $options = array();
	//        $options = array('' => __('Select Below'));
        foreach ($elements as $element) {
            $optGroup = $element['item_type_name'] 
                      ? __('Item Type') . ': ' . __($element['item_type_name']) 
                      : __($element['element_set_name']);
            $value = __($element['element_name']);
            
            $options[$optGroup][$element['element_id']] = $value;
        }
        return $options;
    }

    private function _getElementIds()
    {
      $db = $this->_helper->db->getDb();
        $sql = "
        SELECT  id 
        FROM {$db->Element} ";
        $ids = $db->fetchAll($sql);
	$rv=array();
	foreach($ids as $id)
	  $rv[]=$id['id'];
        return $rv;
    }

    /**
     * Get an array to be used in formSelect() containing all collections.
     * 
     * @return array
     */
    private function _getFormCollectionOptions()
    {
      $collections = get_records('Collection',array(),'0');
      $options = array('0'=>'All Collections');
      foreach ($collections as $collection)
	{
	  $titles = $collection->getElementTexts('Dublin Core','Title');
	  if(isset($titles[0]))
	    $title = $titles[0];
	  $options[$collection->id]=$title;
	}

      return $options;
    }

    /**
     * Get an array to be used in formSelect() containing all 
     *  comparison operators supported by this plugin.
     * 
     * @return array
     */
    private function _getFormCompareOptions()
    {
      $options = array(
		       'exact' => 'is exactly',
		       'contains' => 'contains',
		       '!exact' => 'is not exactly',
		       '!contains' => 'does not contain'
		       );
        return $options;
    }

    private function _getChanges($items,$fields,$max=0)
    {
      if(!isset($_REQUEST['changes-radio']))
	die("Please select an action to perform");//TODO:proper error handling
      $changes=array();

      $j=1;
      $i=0;

      foreach($items as $item)
	{
	  $i++;
	  if($item['id']==0)
	    break;
	  $made=array();
	  if(empty($item) || !isset($fields[$item['id']]) )
	    continue;
	  $itemObj=get_record_by_id('Item',$item['id']);

	  $fieldItem = $fields[$item['id']];
	  
	  unset($fieldItem['title']);
	  
	  if($max>0 and $j>$max) break;
	  foreach($fieldItem as $field)
	    {
	      $replaceType="normal";
	      switch($_REQUEST['changes-radio'])
		{
		case 'preg':
		  $replaceType="preg";

		case 'replace':

		  //expect a 'find' and 'replace' variable
		  if(!isset($_REQUEST['sedmeta-find'])||!isset($_REQUEST['sedmeta-replace']))
		    die("ERROR! variables not set or something!");//TODO:proper error handling

		  $element=$itemObj->getElementById($field['elementID']);
		  //$eText = $itemObj->getElementTextsByRecord($element);
		  $eText = get_record_by_id('ElementText',$field['id']);
		      
		  $count=0;

		  if($replaceType=="normal")
		    $new=str_replace($_REQUEST['sedmeta-find'],$_REQUEST['sedmeta-replace'],$eText->text,$count);
		  elseif($replaceType=="regexp")
		    $new=preg_replace($_REQUEST['sedmeta-find'],$_REQUEST['sedmeta-replace'],$eText->text,-1,$count);

		  //if str_replace matches anything,
		  //update the return array
		  if($count>0)
		    {
		      $changes[]=array(
				       'item'=>$item['title'],
				       'field'=>$element->name,
				       'old'=>$eText->text,
				       'new'=>$new
				       );
		      $j++;
		    }
		  break;


		case 'delete':
		  //update the return array
		  
		  $element=$itemObj->getElementById($field['elementID']);
		  $eText = get_record_by_id('ElementText',$field['id']);
		      
		  $changes[]=array(
				   'item'=>$item['title'],
				   'field'=>$element->name,
				   'old'=>$eText->text,
				   'new'=>'null'
				   );
		  $j++;
		  break;
	      
		case 'append':
		  if(!isset($_REQUEST['sedmeta-append']))
		    die('ERRORZ! WRONG SET VARS AND JUNK!');//todo error handling
		  if(!isset($_REQUEST['delimiter']))
		    $_REQUEST['delimiter']=' ';
		  $element=$itemObj->getElementById($field['elementID']);
		  $eText = get_record_by_id('ElementText',$field['id']);
		      
		  $changes[]=array(
				   'item'=>$item['title'],
				   'field'=>$element->name,
				   'old'=>$eText->text,
				   'new'=>$eText->text.$_REQUEST['delimiter'].$_REQUEST['sedmeta-append']
				   );
		  $j++;
		      
			
		  break;

		case 'add':
		  if(!isset($_REQUEST['sedmeta-add']))
		    die('ERRORZ! WRONG SET VARS AND JUNK!');//todo error handling
		  $element=$itemObj->getElementById($field['elementID']);
		  if(!in_array($field['elementID'],$made))
		    {
		      $changes[]=array(
				       'item'=>$item['title'],
				       'field'=>$element->name,
				       'old'=>'null',
				       'new'=>$_REQUEST['sedmeta-add']
				       );
		      $j++;
		    }
		      
		  $made[]=$field['elementID'];
		  break;
		} //end switch

	    } //end field item loop
	    
	  //delete all item metadata
	  //repopulate the item metadata with the new array

	}//end item loop

      if($max>0 && $j>$max)
	{
	  $leftover = count($items)-$i;
	  $j++;
	  if ($leftover>0)
	    $changes[]=array(
			     'item'=>'plus changes for '.$leftover.' more items',
			     'field'=>'',
			     'old'=>'',
			     'new'=>''
			     );
	}

      /*$changes = array (
			array(
			      'item'=>1,
			      'field'=>'Title',
			      'old'=>'The old Title',
			      'new'=>'The new Title'
			      ),
			array(
			      'item'=>1,
			      'field'=>'Description',
			      'old'=>'The old Description',
			      'new'=>'The new Description'
			      ),
			array(
			      'item'=>2,
			      'field'=>'Title',
			      'old'=>'The old Title of item 2',
			      'new'=>'The new Title of item 2'
			      )
			      );
      */
      return $changes;
      
    }

    private function _getItems($max = 0)
    {

      $rules=array();
      $i=1;
      while (isset($_REQUEST['field'.$i]) && isset($_REQUEST['srch'.$i]))
	{
	  $search=urldecode($_REQUEST['srch'.$i]);
	  $neg=false;
	  if(isset($_REQUEST['neg'.$i])&&$_REQUEST['neg'.$i]=="true")
	    $neg='true';
	  $rules[] = array(
			   'field'=> $_REQUEST['field'.$i],
			   'search'=>$_REQUEST['srch'.$i],
			   'neg'=>$neg
			   );
	  $i++;
	}

      $params=array();

      if( isset($_REQUEST['sedmeta-collection-id']) && $_REQUEST['sedmeta-collection-id'] != 0)
	$params['collection']=$_REQUEST['sedmeta-collection-id'];

      $items = get_records("Item",$params,0);

      if(count($rules)>0)
	{
	  $newitems=array();
	  $j=0;
	  foreach($items as $item)
	    {
	      if($max > 0 && ++$j>$max) break;
	     
	      //by default, select all items. 
	      //Each rule can eliminate items 
	      //by setting this variable to false
	      $matched=true; 

	      //loop through the rules
	      foreach($rules as $rule)  
		{
		  //get all elementTexts for this rule's Element 
		  $compareTexts = $item->getElementTextsByRecord($item->getElementById($rule['field'])); 

		  //this variable keeps track of whether any
		  //of the element texts matches the rule
		  $matched2=false;

		  //loop through the rules
		  foreach ($compareTexts as $compareText)
		    {
		      //perform the search
		      $match = preg_match($rule['search'],$compareText->text);

		      
		      if ($match===false)
			die('regular expression error!');//TODO proper error handling

		      //negate if necessary
		      if($rule['neg'])
			$match = !(boolean)$match;

		      //throw a flag if we found a match
		      if($match)
			$matched2=true;
		    }

		  //if none of the metadata entries for this field match the rule
		  if(!$matched2) 
		    {
		      //then this item will not be selected
		      $matched=false;

		      //so we decrement our item counter
		      $j--;
		      
		      //and we do not have to check the other rules
		      continue;
		    }
		} //end rule loop

	      //if none of the rules has excluded this item
	      //include it in the updated list of items
	      if($matched)
		$newitems[]=$this->_pullItemData($item);

	    } //end item loop
	} //endif (if there are any rules)
      else //if we had no rules to enforce, and skipped the above loops
	{
	  //generate the return array from all of the items
	  $newitems=array();
	  $j=0;
	  foreach($items as $item)
	    {
	      if($max > 0 && ++$j>$max) break;
	      $newitems[]=$this->_pullItemData($item);       
	    }
	}

      if($j>$max)
	{
	  $leftover = count($items)-$max;
	  if ($leftover>0)
	    $newitems[]=array(
			      'title'=>'plus '.$leftover.' more items',
			      'description'=>'',
			      'type'=>'',
			      'id'=>0
			    );
	}

      return $newitems;
    }

    private function _pullItemData($item)
    {
      $title = 'untitled';
      $description = 'no description given';
      $typename="undefined";
      $titles = $item->getElementTextsByRecord($item->getElementById(50));
      if(count($titles)>0)
	$title=$titles[0]->text;
      //print_r($titles);
      //die();
      $descriptions=$item->getElementTextsByRecord($item->getElementById(41));
      if(count($descriptions)>0)
	$description=$descriptions[0]->text;
      $type=$item->getItemType();
      if(is_object($type))
	$typename=$type->name;
	   
      $rv = array(
		  'title'=>$title,
		  'description'=>$description,
		  'type'=>$typename,
		  'id'=>$item->id
		  );
      return $rv;

    }
    
    private function _getFields($items,$max=0)
    {
      $fields = array();

      if(!isset($_REQUEST['field-selections']))
	$_REQUEST['field-selections']="all";

      switch ($_REQUEST['field-selections'])
	{
	case "select":
	  if(!isset($_REQUEST['selectfields']))
	    die('errorz');
	  $fields = $_REQUEST['selectfields'];
	  break;

	case "search":
	  //not yet implemented
	  break;

	case "all":
	  $fields = $this->_getElementIds();
	  break;

	default:
	  die("error");

	}

      $newfields = array();

      $j=1;
      $i=0;
      foreach ($items as $item)
	{
	  $i++;
	  if($item['id']==0)
	    break;
	  if($max>0 && $j>$max)
	    break;
	  $itemObj=get_record_by_id('Item',$item['id']);
	  $flag = false;
	  foreach($fields as $field)
	    {
	      $element = $itemObj->getElementById($field);
	      $fieldname = $element->name;
	      $elementTexts =  $itemObj->getElementTextsByRecord($itemObj->getElementById($field));
	      foreach($elementTexts as $elementText)
		{
		  $newfields[$item['id']][] = array(
						       'field'=>$fieldname,
						       'value'=>$elementText->text,
						       'elementID'=>$element->id,
						       'id'=>$elementText->id
						       );
		  $flag=true;
		}
	    }
	  if($flag)
	    {
	      $newfields[$item['id']]['title']=$item['title'];
	      $j++;
	    }
	  
	}

      if($max > 0 && $j > $max)
	{
	  $leftover = count($items)-$i;
	  if ($leftover>0)
	    $newfields[]=array('title'=>'...and corresponding fields from '.$leftover.' more items');
	}

       return $newfields;

    }

    private function _execute($items,$changes)
    {

    }
    
}
