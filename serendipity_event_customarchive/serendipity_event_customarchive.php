<?php
/*
SMARTY Function 'customArchive':

This function allows you to display customized overviews of Serendipity
Blog entries that have specific entryproperty plugin values. Those can
be filtered, and you can use this smarty function to print out their
sortings.

{customArchive 
    entryprops='Name,Lage,Groesse:m²,Personen,Preis:€,Bild,Teaser' 
    sortfields='Name,Lage,Groesse:Größe,Personen:max. Personen,Preis:Preis ab' 
    filter='Name' 
    teaser='Teaser' 
    picture='Bild' 
    template='customarchive.tpl' 
    limit='20' 
    searchfields='Name+Lage+Teaser:Text:Suchwort,Groesse:Int:Größe von,Personen:Int:Personen von,Preis:Int:Preis von' 
    valuelimit='Preis:0-100'}

* entryprops

    Commaseparated list of all entryproperty key names (NO special characters!) as to be 
    used in the readout of your entryproperty plugin configuration. Any key can use a ":" 
    and then a unit that should be appended to each value, like "Price:$".

* sortfields

    Commaseparated list of all entrypropertiey key names and how they should be printed 
    out in the table. This is where you can alias key names with human readable fields
    by appending a ":" and then the real field name, like "custprice:Custom Price as seen on TV".
    They keys before the ":" need to be the same as in the "entryprops" configuration above.

* filter

    The default entryproperty key name on which the table output will be sorted.

* teaser

    The default entryproperty key name that is to be used as a fulltext teaser output from the
    related entries.

* picture

    The default entryproperty key name that holds a picture of a blog entry
    
* template

    The name of the template file that will render the table. See example customarchive.tpl file.    

* limit

    The number of entries to fetch.

* searchfields

    Here you can specify a search form. For each search form input field you enter a block
    of variables: "fieldlist:type:display name".
    
    The 'fieldlist' contains a list of entryproperty key names of which fields will be searched
    in, separated with a "+".
    
    The 'type' can contain either "Text" (Fulltextsearch) or "Int" (Range from-to).
    
    The 'display name' tells what is displayed next to the input field of the resulting form.
    
* valuelimit

    Can contain a default filtering in the format "Keyname:from-to". The keyname needs
    to be any of the 'entryprops' available field, "from" and "to" corresponds to any
    integer number.

*/

if (IN_serendipity !== true) {
    die ("Don't hack!");
}

// Probe for a language include with constants. Still include defines later on, if some constants were missing
$probelang = dirname(__FILE__) . '/' . $serendipity['charset'] . 'lang_' . $serendipity['lang'] . '.inc.php';
if (file_exists($probelang)) {
    include $probelang;
}

include dirname(__FILE__) . '/lang_en.inc.php';

class serendipity_event_customarchive extends serendipity_event {
    var $title = PLUGIN_CUSTOMARCHIVE_TITLE;
    function introspect(&$propbag) {
        global $serendipity;

        $propbag->add('name', PLUGIN_CUSTOMARCHIVE_TITLE);
        $propbag->add('description', PLUGIN_CUSTOMARCHIVE_TITLE_BLAHBLAH);
        $propbag->add('event_hooks',  array('entries_header' => true, 'entry_display' => true, 'genpage' => true, 'frontend_fetchentries' => true));
        $propbag->add('configuration', array('permalink', 'pagetitle', 'articleformat'));
        $propbag->add('author', 'Garvin Hicking');
        $propbag->add('version', '1.13');
        $propbag->add('requirements',  array(
            'serendipity' => '0.8',
            'smarty'      => '2.6.7',
            'php'         => '4.1.0'
        ));
        $propbag->add('stackable', false);
        $propbag->add('groups', array('FRONTEND_ENTRY_RELATED'));
        $this->dependencies = array('serendipity_plugin_customarchive' => 'remove');
    }

    function introspect_config_item($name, &$propbag)
    {
        global $serendipity;

        switch($name) {
            case 'permalink':
                $propbag->add('type',        'string');
                $propbag->add('name',        PLUGIN_CUSTOMARCHIVE_PERMALINK);
                $propbag->add('description', PLUGIN_CUSTOMARCHIVE_PERMALINK_BLAHBLAH);
                $propbag->add('default',     $serendipity['rewrite'] != 'none'
                                             ? $serendipity['serendipityHTTPPath'] . 'sortarchiv'
                                             : $serendipity['serendipityHTTPPath'] . $serendipity['indexFile'] . '?/sortarchiv');
                break;

            case 'pagetitle':
                $propbag->add('type',        'string');
                $propbag->add('name',        PLUGIN_CUSTOMARCHIVE_PAGETITLE);
                $propbag->add('description', '');
                $propbag->add('default',     'archives');
                break;

            case 'articleformat':
                $propbag->add('type',        'boolean');
                $propbag->add('name',        PLUGIN_CUSTOMARCHIVE_ARTICLEFORMAT);
                $propbag->add('description', PLUGIN_CUSTOMARCHIVE_ARTICLEFORMAT_BLAHBLAH);
                $propbag->add('default',     'true');
                break;

            default:
                return false;
        }
        return true;
    }

    function dropdown($name, $values) {
        global $serendipity;

        echo '<select name="serendipity[' . $name . ']">' . "\n";
        foreach ($values AS $value) {
            echo '<option value="' . $value['value'] . '" ' . ($serendipity['GET'][$name] == $value['value'] ? 'selected="selected"' : '') . '>' . (function_exists('serendipity_specialchars') ? serendipity_specialchars($value['desc']) : htmlspecialchars($value['desc'], ENT_COMPAT, LANG_CHARSET)) . '</option>' . "\n";
        }
        echo '</select>' . "\n";
    }

    function setDefaultValue($field, &$values, $def) {
        global $serendipity;

        if (isset($serendipity['GET'][$field])) {
            return $serendipity['GET'][$field];
        }

        $val = null;
        foreach($values AS $value) {
            if ( (!empty($serendipity['GET'][$field]) && $value['value'] == $serendipity['GET'][$field]) ||
                 (empty($serendipity['GET'][$field])  && $value['value'] == $def) ) {
                $val = $def;
            }
        }

        $serendipity['GET'][$field] = $val;
    }

    function showEntries() {
        global $serendipity;

        if ($serendipity['GET']['custom_sortyears'] == 'all') {
            $range = null;
        } else {
            $range = array(
                mktime(0, 0, 0, 1, 1, $serendipity['GET']['custom_sortyears']),
                mktime(0, 0, 0, 1, 1, $serendipity['GET']['custom_sortyears'] + 1)
            );
        }

        if ($serendipity['GET']['custom_sortauthors'] != 'all') {
            $serendipity['GET']['viewAuthor'] = (int)$serendipity['GET']['custom_sortauthors'];
        }

        switch($serendipity['GET']['custom_sortfield']) {
            case 'category':
                 $sql_order = 'c.category_name';
            break;;
            case 'timestamp':
                 $sql_order = 'e.timestamp';
            break;

            case 'title':
                 $sql_order = 'e.title';
            break;
        }

        if ($serendipity['GET']['custom_sortorder'] == 'desc') {
            $sql_order =   $sql_order.' DESC';
        }

        $entries = serendipity_fetchEntries($range, false, '', false, false, $sql_order);
        $pool = array();
        $lang = '';
        $urlparam = '';
        
        // set multilingual url parameters
        $sortlangs = false;
        $urlparam = false;
        if (isset($serendipity['GET']['custom_sortlangs'])) {
            $sortlangs =  $serendipity['GET']['custom_sortlangs'];
            if ($sortlangs != $serendipity['lang']) {
            // requested language is different from display language, force language selection by url param
            $urlparam = 'serendipity[lang_selected]=' 
                . serendipity_db_escape_string($sortlangs);  
        }        
        }
        
        if (is_array($entries)) {
        foreach($entries AS $entry) {
            
            $entryTitle = '';
            $entryLink = '';
            $entryHTML = '';
            
            // multilingual plugin active: if GET custom_sortlangs !all then get multilingual_title_xx for every entry
            if ($sortlangs && $sortlangs != $serendipity['default_lang']) {
                
                $entryTitle = $entry['properties']['multilingual_title_' . $sortlangs];
                if (empty($entryTitle)) continue; // skip untranslated article
                
                $entryLink = $serendipity['serendipityHTTPPath'] . $serendipity['indexFile'] . '?/' 
                            . serendipity_archiveURL(
                                $entry['id'], 
                                $entryTitle, 
                                'serendipityHTTPPath', 
                                false)
                            . '&amp;' . $urlparam;
                $entryHTML = '<a href="' . $entryLink . '" title="' . (function_exists('serendipity_specialchars') ? serendipity_specialchars($entryTitle) : htmlspecialchars($entryTitle, ENT_COMPAT, LANG_CHARSET)) . '">' . $entryTitle . '</a><br />';
                
            } else {
                // without multilingual plugin or blog entries in default language
                $entryTitle = $entry['title'];
            $entryLink = serendipity_archiveURL(
                           $entry['id'],
                           $entry['title'],
                           'serendipityHTTPPath',
                           true,
                           array('timestamp' => $entry['timestamp'])
                        );
                if ($urlparam) $entryLink .= '&amp;' . $urlparam; // force default language
            $entryHTML = '<a href="' . $entryLink . '" title="' . (function_exists('serendipity_specialchars') ? serendipity_specialchars($entry['title']) : htmlspecialchars($entry['title'], ENT_COMPAT, LANG_CHARSET)) . '">' . $entry['title'] . '</a><br />';
            }
            
            $key       = '';

            switch($serendipity['GET']['custom_sortfield']) {
                case 'category':
                    if (isset($entry['categories'][0])) {
                        $key = $entry['categories'][0]['category_name'];
                    } else {
                        $key = $entry['category_name'];
                    }
                    serendipity_plugin_api::hook_event('multilingual_strip_langs',$key);
                    break;

                case 'timestamp':
                    $key = serendipity_strftime('%B %Y', $entry['timestamp']);
                    break;

                case 'title':
                    $key = strtoupper(substr($entryTitle, 0, 1));
                    break;
            }

            $pool[$key][] = $entryHTML;
        }
        }

        if (count($pool) == 0) {
            echo PLUGIN_CUSTOMARCHIVE_EMPTY;
        } else {

        foreach($pool AS $key => $content) {
            echo '<dl>';
            echo '<dt>' . $key . '</dt>' . "\n";
            foreach($content AS $HTML) {
                echo '<dd>' . $HTML . '</dd>' . "\n";
            }
            echo '</dl>';
        }
    }
    }

    function getUsedLanguages() {
        global $serendipity;
        $sql = "SELECT property FROM {$serendipity['dbPrefix']}entryproperties WHERE property LIKE 'multilingual_title%'";
        $entries = serendipity_db_query($sql,false,'assoc');
        $langs = array();
        foreach ($entries AS $entry) {
            $lang = substr($entry['property'],19);
            if (!in_array($lang,$langs)) {
                $langs[] = $lang;
            }
        }
        
        return $langs;
    }

    function show() {
        global $serendipity;

        if ($this->selected()) {
            if (!headers_sent()) {
                header('HTTP/1.0 200');
                header('Status: 200 OK');
            }

            if (!is_object($serendipity['smarty'])) {
                serendipity_smarty_init();
            }
            $pt = preg_replace('@[^a-z0-9]@i', '_',$this->get_config('pagetitle'));
            $_ENV['staticpage_pagetitle'] = $pt;
            $serendipity['smarty']->assign('staticpage_pagetitle', $pt);


            if ($this->get_config('articleformat') == TRUE) {
                echo '<article class="clearfix serendipity_entry" id="customarchive" role="article">
                         <header class="clearfix">
                         <h2><a href="'. $serendipity['serendipityHTTPPath'] . $serendipity['indexFile'] . '?/pages/archives.html">' . PLUGIN_CUSTOMARCHIVE_TITLE .  '</a></h2></header>';
            }

            echo '<h4 class="serendipity_title"><a href="#">' . $this->get_config('headline') . '</a></h4>';

            if ($this->get_config('articleformat') == TRUE) {
                echo '<div class="clearfix content serendipity_entry_body">';
            }

            $first_entry = serendipity_db_query("SELECT min(timestamp) AS first FROM {$serendipity['dbPrefix']}entries WHERE isdraft = 'false' LIMIT 1", true);
            if (is_array($first_entry) && $first_entry['first'] > 0) {
                $first_year = date('Y', $first_entry['first']);
            } else {
                $first_year = date('Y') - 1;
            }

            $page = $this->get_config('pagetitle');
            $custom_sortfield = array(
                array('value' => 'timestamp',
                      'desc'  => DATE),

                array('value' => 'title',
                      'desc'  => TITLE),

                array('value' => 'category',
                      'desc'  => CATEGORY),
            );

            $custom_sortorder = array(
                array('value' => 'asc',
                      'desc'  => SORT_ORDER_ASC),

                array('value' => 'desc',
                      'desc'  => SORT_ORDER_DESC)
            );

            $custom_sortyears = array();
            $custom_sortyears[] = array('value' => 'all', 'desc' => COMMENTS_FILTER_ALL);
            for ($i = $first_year; $i <= date('Y'); $i++) {
                $custom_sortyears[] = array('value' => $i, 'desc' => $i);
            }

            $custom_sortauthors = array();
            $custom_sortauthors[] = array('value' => 'all', 'desc' => COMMENTS_FILTER_ALL);
            $users = serendipity_fetchUsers();
            if (is_array($users)) {
                foreach($users AS $user) {
                    $custom_sortauthors[] = array('value' => $user['authorid'], 'desc' => $user['realname']);
                }
            }

            $custom_sortlangs = array();
            $custom_sortlangs[] = array('value' => $serendipity['default_lang'], 'desc' => $serendipity['languages'][$serendipity['default_lang']]);
            $langs = $this->getUsedLanguages();
            if (is_array($langs)) {
                foreach($langs AS $lang) {
                    $custom_sortlangs[] = array('value' => $lang, 'desc' => $serendipity['languages'][$lang]);
                }
            }
            

            $this->setDefaultValue('custom_sortfield', $custom_sortfield, 'timestamp');
            $this->setDefaultValue('custom_sortorder', $custom_sortorder, 'asc');
            $this->setDefaultValue('custom_sortyears', $custom_sortyears, date('Y'));
            $this->setDefaultValue('custom_sortauthors', $custom_sortauthors, 'all');
            if ($serendipity['lang'] != $serendipity['default_lang']) {
                $this->setDefaultValue('custom_sortlangs', $custom_sortlangs, $serendipity['lang']);
            } else {
                $this->setDefaultValue('custom_sortlangs', $custom_sortlangs, $serendipity['default_lang']);
            }

?>
<form action="<?php echo $serendipity['baseURL']; ?>index.php?" method="get">
    <input type="hidden" name="serendipity[subpage]" value="<?php echo $page; ?>" />
<h6 style='margin-bottom:0.5em;'><?php echo PLUGIN_CUSTOMARCHIVE_SELECT . '<br />'; ?></h6>
<div style='display:table-row'>
<span style='display:table-cell; padding-right:1em;padding-top:0.2em'><?php echo PLUGIN_CUSTOMARCHIVE_YEAR; ?></span>
<span style='display:table-cell'><?php echo $this->dropdown('custom_sortyears', $custom_sortyears); ?></span>
</div>
<div style='display:table-row'>
<span style='display:table-cell; padding-right:1em;padding-top:0.2em'><?php echo PLUGIN_CUSTOMARCHIVE_AUTHOR; ?></span>
<span style='display:table-cell'><?php echo $this->dropdown('custom_sortauthors', $custom_sortauthors); ?></span>
</div>
<?php if (count($custom_sortlangs) > 1) { ?>
<div style='display:table-row'>
<span style='display:table-cell; padding-right:1em;padding-top:0.2em'><?php echo PLUGIN_CUSTOMARCHIVE_LANGUAGE; ?></span>
<span style='display:table-cell'><?php echo $this->dropdown('custom_sortlangs', $custom_sortlangs); ?></span>
</div><?php } ?>
<h6 style='margin-bottom:0.5em;margin-top:1em;'><?php echo SORT_BY; ?></h6>
<p>
<?php echo $this->dropdown('custom_sortfield', $custom_sortfield); ?>
<?php echo '&nbsp;&nbsp;' . $this->dropdown('custom_sortorder', $custom_sortorder); ?>
</p>
<p>
    <input type="submit" name="submit" value="<?php echo GO; ?>" />
</p>
</form>
<hr />
<?php

            $this->showEntries();

            if ($this->get_config('articleformat') == TRUE) {
                echo '</div></article>';
            }
        }
    }

    function selected() {
        global $serendipity;

        if ($serendipity['GET']['subpage'] == $this->get_config('pagetitle') ||
            preg_match('@^' . preg_quote($this->get_config('permalink')) . '@i', $serendipity['GET']['subpage'])) {
            return true;
        }

        return false;
    }

    function generate_content(&$title) {
        $title = PLUGIN_CUSTOMARCHIVE_TITLE;
    }


    function smarty_customArchive($params, &$smarty) {
        global $serendipity;

        if (isset($serendipity['GET']['filter'])) {
            $params['filter'] = $serendipity['GET']['filter'];
        }

        if (isset($serendipity['POST']['filter'])) {
            $params['filter'] = $serendipity['POST']['filter'];
        }

        if (isset($serendipity['GET']['mode'])) {
            $params['mode'] = $serendipity['GET']['mode'];
        }
        
        if (isset($serendipity['POST']['mode'])) {
            $params['mode'] = $serendipity['POST']['mode'];
        }

        if (strtolower($params['mode']) != 'asc' && strtolower($params['mode'] != 'desc')) {
            $params['mode'] = 'asc';
        }

        if (empty($params['template'])) {
            $params['template'] = 'customarchive.tpl';
        }

        $_key_props = explode(',', $params['entryprops']);
        $key_props  = array();
        $add_props  = array();
        $valid_filter = false;
        foreach($_key_props AS $prop) {
            $propparts = explode(':', trim($prop));

            $prop = $propparts[0];

            if (isset($propparts[1])) {
                $add_props['ep_' . $prop] = $propparts[1];
            }

            $key_props[] = 'ep_' . $prop;
            if (empty($params['filter'])) {
                $params['filter'] = $prop;
            }

            if ($prop == $params['filter']) {
                $valid_filter = true;
            }
        }

        $props = explode(',', $params['sortfields']);
        $name_props = array();
        foreach($props AS $prop) {
            $propparts = explode(':', trim($prop));
            if (isset($propparts[1])) {
                $name_props['ep_' . $propparts[0]] = $propparts[1];
            } else {
                $name_props['ep_' . $propparts[0]] = $propparts[0];
            }
        }

        $props = explode(',', $params['searchfields']);
        $search_props = array();
        foreach($props AS $prop) {
            $propparts = explode(':', trim($prop));
            
            $sfields = explode('+', $propparts[0]);
            
            foreach($sfields AS $sfield) {
                $search_props[$propparts[2]]['fields']['ep_' . $sfield] = true;
            }

            $search_props[$propparts[2]]['type'] = strtolower($propparts[1]);
            $search_props[$propparts[2]]['key']  = md5($propparts[0]);
        }
        
        $searchdata = $serendipity['POST']['search'];
        if (!is_array($searchdata)) {
            $searchdata = array();
        }

        $props = explode(',', $params['valuelimit']);
        $values_props = array();
        foreach($props AS $prop) {
            $propparts = explode(':', trim($prop));
            
            $sfields = explode('-', $propparts[1]);
            $values_props['ep_' . $propparts[0]]['from'] = $sfields[0];
            $values_props['ep_' . $propparts[0]]['to']   = $sfields[1];
        }

        $serendipity['smarty']->assign('customarchive_props', $name_props);
        $serendipity['smarty']->assign('customarchive_keyprops', $key_props);
        $serendipity['smarty']->assign('customarchive_infoprops', $add_props);
        $serendipity['smarty']->assign('customarchive_filter', $params['filter']);
        $serendipity['smarty']->assign('customarchive_teaser', 'ep_' . $params['teaser']);
        $serendipity['smarty']->assign('customarchive_subpage', $serendipity['GET']['subpage']);
        $serendipity['smarty']->assign('customarchive_picture', 'ep_' . $params['picture']);
        $serendipity['smarty']->assign('customarchive_nextmode', strtolower($params['mode']) == 'desc' ? 'asc' : 'desc');
        $serendipity['smarty']->assign('customarchive_mode', strtolower($params['mode']));
        $serendipity['smarty']->assign('customarchive_search', $search_props);
        $serendipity['smarty']->assign('customarchive_searchdata', $searchdata);

        $lookup = array();
        foreach($key_props AS $idx => $prop) {
            $and = '';
            
            $lookup[$prop] = 'ep' . $idx;
            if ($prop == 'ep_' . $params['filter'] && $valid_filter) {
                $filteridx = $idx;
                $params['joinown'] .= "\n JOIN {$serendipity['dbPrefix']}entryproperties
                                          AS ep" . $idx . "
                                          ON (ep" . $idx . ".entryid = e.id AND
                                              ep" . $idx . ".property = '" . serendipity_db_escape_string($prop) . "' $and) \n";
            } else {
                $params['joinown'] .= "\n LEFT OUTER JOIN {$serendipity['dbPrefix']}entryproperties
                                          AS ep" . $idx . "
                                          ON (ep" . $idx . ".entryid = e.id AND
                                              ep" . $idx . ".property = '" . serendipity_db_escape_string($prop) . "' $and) \n";
            }
        }

        $sql_where = '';
        $sql_where_parts = array();
        $searchdata_from = $searchdata_to = array();
        if (is_array($serendipity['POST']['search'])) {
            foreach($serendipity['POST']['search'] AS $skey => $sdata) {
                if (!is_array($sdata)) {
                    $sdata = trim($sdata);
                }
                if (empty($sdata)) continue;
                
                foreach($search_props AS $sdesc => $sdata2) {
                    if ($sdata2['key'] == $skey) {
                        if ($sdata2['type'] == 'text') {
                            $parts = array();
                            foreach($sdata2['fields'] AS $fieldkey => $fieldval) {
                                $parts[] = $lookup[$fieldkey] . '.value LIKE "%' . serendipity_db_escape_string($sdata) . '%"';
                            }
                            $sql_where_parts[] .= "\n(" . implode(' OR ', $parts) . ")\n";
                        } elseif ($sdata2['type'] == 'int') {
                            $parts = array();
                            foreach($sdata2['fields'] AS $fieldkey => $fieldval) {
                                if (!empty($sdata['from'])) {
                                    $parts[] = 'CAST(' . $lookup[$fieldkey] . '.value AS unsigned) >= ' . serendipity_db_escape_string($sdata['from']);
                                }
                                if (!empty($sdata['to'])) {
                                    $parts[] = 'CAST(' . $lookup[$fieldkey] . '.value AS unsigned) <= ' . serendipity_db_escape_string($sdata['to']);
                                }

                                $searchdata_from[$sdata2['key']] = $sdata['from'];
                                $searchdata_to[$sdata2['key']] = $sdata['to'];
                            }
                            if (count($parts) > 0) {
                                $sql_where_parts[] .= "\n(" . implode(' AND ', $parts) . ")\n";
                            }
                        }
                    }
                }
            }
            $sql_where = implode(" AND ", $sql_where_parts);
        } elseif (count($values_props) > 0) {
            $sql_where_parts = array();
            foreach($values_props AS $vfieldname => $vfieldvals) {
                $sql_where_parts[] = '(CAST(' . $lookup[$vfieldname] . '.value AS unsigned) >= ' . $vfieldvals['from'] . ' 
                                       AND CAST(' . $lookup[$vfieldname] . '.value AS unsigned) <= ' . $vfieldvals['to'] . ')' . "\n";
            }
            $sql_where .= implode(" AND ", $sql_where_parts);
        }

        $serendipity['smarty']->assign('customarchive_searchdata_from', $searchdata_from);
        $serendipity['smarty']->assign('customarchive_searchdata_to', $searchdata_to);


        if (empty($filteridx)) {
            $filteridx = 0;
        }

        $cat = $serendipity['GET']['category'];
        unset($serendipity['GET']['category']);


        #if ($sql_where != '') $GLOBALS['Dbdie'] = true;
        $entries = serendipity_fetchEntries(
            null,
            true,
            $params['limit'],
            false,
            false,
            'CAST(ep' . $filteridx . '.value AS UNSIGNED) ' . $params['mode'],
            $sql_where,
            false,
            false,
            null,
            null,
            'array',
            true,
            true,
            $params['joinown']
        );

        $dategroup = array(0 => array('entries' => $entries, 'timestamp' => time()));

        $GLOBALS['DBTEST'] = true;
        $entries = serendipity_printEntries($dategroup, true, false, 'CUSTOMARCHIVE_ENTRIES', 'return', false, false, true);

        if (!empty($cat)) {
            $serendipity['GET']['category'] = $cat;
        }
        $serendipity['smarty']->assign('customarchive_entries', $entries);

        $filename = basename($params['template']);
        $tfile = serendipity_getTemplateFile($filename, 'serendipityPath');
        if (!$tfile || $tfile == $filename) {
            $tfile = dirname(__FILE__) . '/' . $filename;
        }
        $inclusion = $serendipity['smarty']->security_settings['INCLUDE_ANY'];
        $serendipity['smarty']->security_settings['INCLUDE_ANY'] = true;
        $content = $serendipity['smarty']->fetch('file:'. $tfile);
        $serendipity['smarty']->security_settings['INCLUDE_ANY'] = $inclusion;

        $serendipity['smarty']->assign('ENTRIES', 'xxx');

        return $content;
    }

    function event_hook($event, &$bag, &$eventData, &$addData = null) {
        global $serendipity;

        $hooks = &$bag->get('event_hooks');

        if (isset($hooks[$event])) {
            switch($event) {
                case 'genpage':
                    $args = implode('/', serendipity_getUriArguments($eventData, true));
                    serendipity_rewriteURL($args, 'baseURL');

                    if ($this->selected()) {
                        $serendipity['head_title']    = $this->get_config('pagetitle');
                        $serendipity['head_subtitle'] = $serendipity['blogTitle'];
                    }

                    if (empty($serendipity['GET']['subpage'])) {
                        $serendipity['GET']['subpage'] = $nice_url;
                    }

                    if (!is_object($serendipity['smarty'])) {
                        serendipity_smarty_init();
                    }    
                    $serendipity['smarty']->register_function('customArchive', array($this, 'smarty_customArchive'));

                    break;

                case 'entry_display':
                    if ($this->selected()) {
                        if (is_array($eventData)) {
                            $eventData['clean_page'] = true; // This is important to not display an entry list!
                        } else {
                            $eventData = array('clean_page' => true);
                        }
                    }

                    if (version_compare($serendipity['version'], '0.7.1', '<=')) {
                        $this->show();
                    }

                    return true;
                    break;

                case 'entries_header':
                    $this->show();

                    return true;
                    break;

                case 'frontend_fetchentries':
                    // show all entries even if multilingual plugin is active and untranslated articles hidden
                    if ($this->selected()) $addData['showAllLangs'] = true;
                    break;

                default:
                    return false;
                    break;
            }
        } else {
            return false;
        }
    }
}
/* vim: set sts=4 ts=4 expandtab : */
