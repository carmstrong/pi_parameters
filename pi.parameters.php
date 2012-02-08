<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
=====================================================
 Authur: Brian Litzinger
-----------------------------------------------------
 http://www.brianlitzinger.com/ee/
=====================================================
 This program is freeware; 
 you may use this code for any purpose, commercial or
 private, without any further permission from the author.
=====================================================
 File: pi.parameters.php
=====================================================
*/

$plugin_info = array(
    'pi_name'           => 'Post/Get Parameters',
    'pi_version'        => '1.4',
    'pi_author'         => 'Brian Litzinger (updated for EE 2.x by Chris Armstrong)',
    'pi_description'    => 'Grab POST and GET parameters.',
    'pi_usage'          => Parameters::usage()
);


class Parameters {

    var $return_data;
    var $separator;

    public function __construct()
    {
        $this->EE =& get_instance();
    }

    function Parameters()
    {        
        $get = $this->EE->TMPL->fetch_param('get') ? $this->EE->TMPL->fetch_param('get') : false;
        $this->separator = $this->EE->TMPL->fetch_param('separator') ? $this->EE->TMPL->fetch_param('separator') : '|';
        $flatten_arrays = $this->EE->TMPL->fetch_param('flatten_arrays') == 'yes' ? true : false;
        
        $tagdata = $this->EE->TMPL->tagdata;
        
        // Prep our conditionals...
        $request_method = strtolower($_SERVER['REQUEST_METHOD']);

        foreach ($_REQUEST as $key => $value)
        {
            // If it's an array, we can write a conditional such as {if post_varname_keyname}
            // which would be the equivelant of doing if($_POST[varname][keyname])
            if(is_array($value) and $flatten_arrays)
            {
                foreach($value as $k => $v)
                {
                    $cond[$request_method.'_'.$key.'_'.$k] = $this->EE->input->clean_input_data($v);
                }
                //$cond[$request_method.'_'.$key.'_boolean'] = true;
            }
            // If it's an array, and we want to see if it's populated, and not checking for a specific value
            // then we just implode the array into a flat string, and can just do a boolean check, or
            // actually check against the string. e.g {if post_varname == 'value1|value2|value2'}
            elseif(is_array($value) and !$flatten_arrays)
            {
                $cond[$request_method.'_'.$key] = $this->EE->input->clean_input_data(implode($this->separator, $value));
                //$cond[$request_method.'_'.$key.'_boolean'] = $value ? true : false;
            }
            // If it's not an array, just clean the data and set the conditional
            else
            {
                $cond[$request_method.'_'.$key] = $this->EE->input->clean_input_data($value);
            }
        }
        
        // Would it be possible to modify this core function to support {if in_array:postarray == valuetocheckagainst} ?
        $tagdata = $this->EE->functions->prep_conditionals($tagdata, $cond);
        
        foreach ($this->EE->TMPL->var_pair as $var => $val)
        {
            // blow up the string
            $var_array = explode('_', $var);
            
            // get the actual GET or POST param, minus the get_ or post_ prefix
            $var_type = strtoupper($var_array[0]);
            $var_key = implode('_', array_slice($var_array, 1));
            // echo $var."<br />";
            $matches = array();
            // Se if we have a var pair, which should be a post/get array e.g. {post_somevar}
            if($this->EE->input->get_post($var_key, $var_type) and array_search($var_type, array('POST', 'GET', 'REQUEST')) !== FALSE)
            {
                preg_match_all("/".LD.$var.RD."(.*?)".LD.SLASH.$var.RD."/s", $this->EE->TMPL->tagdata, $matches);
                
                //                 if (preg_match_all("#".LD."repeet:start_time".RD."#", $row, $matches, PREG_SET_ORDER))
                // {
                //  foreach ($matches as $match)
                //  {
                //      echo 'foobar!';
                //      $str = str_replace($match[0], $this->output_date_header($match[1], $val['item_start']), $str);
                //  }
                // }

                // If we have a match, parse the contents, loop through the array and replace the tag contents.
                if(count($matches) > 0)
                {
                    foreach($matches[1] as $match => $mval)
                    {
                       $var_content = $mval;

                       // var_dump($var_content);

                       //Get the actual post value
                       $data = $this->EE->input->get_post($var_key, $var_type);

                       $loopdata = '';
                       if(is_array($data))
                       {
                           foreach($data as $k => $v)
                           {
                               $keys = array(LD.'key'.RD, LD.'value'.RD);
                               $values = array($k, $this->_parse_value($v));

                               $loopdata .= str_replace($keys, $values, $var_content);
                           }
                        }
                        else
                        {
                            $keys = array(LD.'key'.RD, LD.'value'.RD);
                            $values = array($data, $this->_parse_value($data));

                            $loopdata .= str_replace($keys, $values, $var_content);
                        }

                       // Put things back in their place...
                       $tagdata = preg_replace("/".LD.$var.RD."(.*?)".LD.SLASH.$var.RD."/s", $loopdata, $tagdata);
                    }
                }
                else
                {
                    // Remove our tag pair if no matches were found
                    $tagdata = preg_replace("/".LD.$var.RD."(.*?)".LD.SLASH.$var.RD."/s", '', $tagdata);
                }
            }
            else
            {
                // Remove our tag pair if no data has been posted
                if(array_search($var_type, array('POST', 'GET', 'REQUEST')) !== FALSE)
                {
                    $tagdata = preg_replace("/".LD.$var.RD."(.*?)".LD.SLASH.$var.RD."/s", '', $tagdata);
                }
            }
            
        }
        
        foreach ($this->EE->TMPL->var_single as $var)
        {
            // echo $var."<br>";
            // blow up the string
            $var_array = explode('_', $var);
            
            // get the actual GET or POST param, minus the get_ or post_ prefix
            $var_type = strtoupper($var_array[0]);
            $var_key = implode('_', array_slice($var_array, 1));
            
            // make sure the first token is either post, get, or request - otherwise
            // it isn't one of our variables so we'll leave it alone
            if(array_search($var_type, array('POST', 'GET', 'REQUEST')) !== FALSE) {
                if($var_type == 'REQUEST') {
                    $tagdata = $this->EE->TMPL->swap_var_single($var, $this->_parse_value($this->EE->input->get_post($var_key)), $tagdata);
                } else {
                    $tagdata = $this->EE->TMPL->swap_var_single($var, $this->_parse_value($this->EE->input->get_post($var_key, $var_type)), $tagdata);
                }
            }
        }

        $this->return_data .= $tagdata; 
        
        return $this->return_data;
    }
   
    function get()
    {
        $name = $this->EE->TMPL->fetch_param('name') ? $this->EE->TMPL->fetch_param('name') : false;

        if($name and $this->EE->input->get_post($name, 'GET'))
        {
            $this->return_data = $this->_parse_value($this->EE->input->get_post($name, 'GET'));
        }

        return $this->return_data;
    } 
    
    function post()
    {        
        $name = $this->EE->TMPL->fetch_param('name') ? $this->EE->TMPL->fetch_param('name') : false;

        if($name and $this->EE->input->get_post($name, 'POST'))
        {
            $this->return_data = $this->_parse_value($this->EE->input->get_post($name, 'POST'));
        }
        
        return $this->return_data;
    }
    
    function request()
    {        
        $name = $this->EE->TMPL->fetch_param('name') ? $this->EE->TMPL->fetch_param('name') : false;

        if($name and $this->EE->input->get_post($name))
        {
            $this->return_data = $this->_parse_value($this->EE->input->get_post($name));
        }
        
        return $this->return_data;
    }
    
    function in_array()
    {
        $name = $this->EE->TMPL->fetch_param('name') ? $this->EE->TMPL->fetch_param('name') : false;
        $value = $this->EE->TMPL->fetch_param('value') ? $this->EE->TMPL->fetch_param('value') : false;
        $return = $this->EE->TMPL->fetch_param('return') ? $this->EE->TMPL->fetch_param('return') : false;
        
        if($name and $value and $this->EE->input->get_post($name))
        {
            if(is_array($this->EE->input->get_post($name)))
            {
                if(in_array($value, $this->EE->input->get_post($name)))
                {
                    $this->return_data = $return ? $return : TRUE;
                }
                else
                {
                    $this->return_data = FALSE;
                }
            }
        }
        
        return $this->return_data;
    }
    
    function is_set()
    {
        
    }
    
    function _parse_value($value)
    {
        if(is_array($value))
        {
            $value = implode($this->separator, $value);
        }

        return $value;
    }
        
    function usage()
    {
        
ob_start(); 
?>
Print GET and POST parameters in your templates

Print a single parameter:
{exp:parameters:get name="job_id"} or {exp:parameters:post name="job_id"}

Print multiple parameters:
{exp:parameters}
    {get_job_id}
    {get_foobar}
{/exp:parameters}

Loop through a parameter that is an array:
{exp:parameters}
    <ul>
    {post_foobar}
        <li>{key} {value}</li>
    {/post_foobar}
    </ul>
{/exp:parameters}

Parameters
------------------
param = (required) Name of parameter to get if using a single tag.
separator = (optional, default = |)

Variables
------------------
If looping through a parameter that is an array the following two single variables are 
available {key} and {value}, which print the corresponding array key and value pair.

If a single variable is an array, it will be returned as a pipe separated list of values. 
This includes the {value} variable used in a loop.

There is currently no support for multi-dimensional arrays beyond printing the value
as a pipe separated list.


<?php
$buffer = ob_get_contents();

ob_end_clean(); 

return $buffer;
        
    }

}
