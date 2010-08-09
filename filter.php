<?php
/**
 * Filter component
 * Benefits:
 * 1. Keep the filter criteria in the Session
 * 2. Give ability to customize the search wrapper of the field types

 **
 * @author  Nik Chankov
 * @website http://nik.chankov.net
 * @version 1.0.0
 *
 */

class FilterComponent extends Object {
    /**
     * fields which will replace the regular syntax in where i.e. field = 'value'
     */
    var $fieldFormatting    = array(
                    "string"=>array("%1\$s LIKE", "%2\$s%%"),
                    "text"=>array("%1\$s LIKE", "%2\$s%%"),
                    "date"=>array("DATE_FORMAT(%1\$s, '%%d-%%m-%%Y')", "%2\$s"),
                    "datetime"=>array("DATE_FORMAT(%1\$s, '%%d-%%m-%%Y')", "%2\$s")
                    );
    /**
     * extra identifier (if needed to specify extra location (like requestAction))
     */
    var $identifier = '';
    
    /**
     * Function which will change controller->data array
     *
     * @param object $controller the class of the controller which call this component
     * @access public
     */
    function process(&$controller){
        $this->_prepareFilter($controller);
        $ret = $this->generateCondition($controller, $controller->data);
        return $ret;
    }
    
    /**
     * Function which loop the provided data and generate the proper where clause
     * @param object Controller or The model in the controller which has been provided in the post
     * @param array $data data which is posted from the filter
     */
    function generateCondition($object, $data=false){
        $ret = array();
        if(isset($data) && is_array($data)){
            //Loop for models
            foreach($data as $model=>$filter){
                if($model == 'OR'){
                    $ret = am($ret, array('OR'=>$this->generateCondition($object, $filter)));
                    unset($data[$model]);
                }
                if(isset($object->{$model})){ //This is object under current object.
                    $columns = $object->{$model}->getColumnTypes();
                    foreach($filter as $field=>$value){
                        if(is_array($value)){ //Possible that this node is another model
                            if(in_array($field, array_keys($columns))){ //The field is from the model, but it has special formatting
                                if(isset($value['BETWEEN'])){ //BETWEEN case
                                    if($value['BETWEEN'][0] != '' && $value['BETWEEN'][1] != ''){
                                        $ret[$model.'.'.$field.' BETWEEN ? AND ?']=$value['BETWEEN'];
                                    }
                                }
                            } else {
                                $ret = am($ret, $this->generateCondition($object->{$model}, array($field=>$value)));
                            }
                            unset($value);
                        } else {
                            if($value != ''){
                                //Trim the value
                                $value=trim($value);
                                //Check if there are some fieldFormatting set
                                if(isset($this->fieldFormatting[$columns[$field]])){
                                    if(isset($this->fieldFormatting[$columns[$field]][1])){
                                        $ret[sprintf($this->fieldFormatting[$columns[$field]][0], $model.'.'.$field, $value)] = sprintf($this->fieldFormatting[$columns[$field]][1], $model.'.'.$field, $value);
                                    } else {
                                        $ret[] = sprintf($this->fieldFormatting[$columns[$field]][0], $model.'.'.$field, $value);
                                    }
                                } else {
                                    $ret[$model.'.'.$field] = $value;
                                }
                            }
                        }
                    }
                    //unsetting the empty forms
                    if(count($filter) == 0){
                        unset($object->data[$model]);
                    }
                }
            }
        }
        return $ret;
    }
   
    /**
     * function which will take care of the storing the filter data and loading after this from the Session
     * @param object $controller
     * @return void
     */
    function _prepareFilter(&$controller){
        if(isset($controller->data)){
            foreach($controller->data as $model=>$fields){
                foreach($fields as $key=>$field){
                    if($field == ''){
                        unset($controller->data[$model][$key]);
                    }
                }
            }
            $controller->Session->write($controller->name.'.'.$controller->params['action'].$this->identifier, $controller->data);
        }
        $filter = $controller->Session->read($controller->name.'.'.$controller->params['action'].$this->identifier);
        $controller->data = $filter;
    }
}