<?php

if (IN_serendipity !== true) {
    die ("Don't hack!");
}

// Probe for a language include with constants. Still include defines later on, if some constants were missing
$probelang = dirname(__FILE__) . '/' . $serendipity['charset'] . 'lang_' . $serendipity['lang'] . '.inc.php';
if (file_exists($probelang)) {
    include $probelang;
}

include dirname(__FILE__) . '/lang_en.inc.php';

class serendipity_plugin_customarchive extends serendipity_plugin
{

    var $title = PLUGIN_SIDEBAR_CUSTOMARCHIVE_NAME;

    function introspect(&$propbag)
    {
        global $serendipity;

        $propbag->add('name',          PLUGIN_SIDEBAR_CUSTOMARCHIVE_NAME);
        $propbag->add('description',   PLUGIN_SIDEBAR_CUSTOMARCHIVE_DESC);
        $propbag->add('stackable',     false);
        $propbag->add('author',        'Stephan Brunker');
        $propbag->add('version', '1.0');
        $propbag->add('requirements',  array(
            'serendipity' => '0.8',
            'smarty'      => '2.6.7',
            'php'         => '4.1.0'
        ));
        $propbag->add('configuration', array(
                'title',
                'linktext',
                'show_archives',
                'archivtext'
                ));
        $propbag->add('groups',        array('FRONTEND_VIEWS'));
        $this->dependencies = array('serendipity_event_customarchive' => 'keep');
        
    }

    function introspect_config_item($name, &$propbag)
    {
        global $serendipity;
        
        switch($name) {
            case 'title':
                $propbag->add('type',        'string');
                $propbag->add('name',        PLUGIN_SIDEBAR_CUSTOMARCHIVE_TITLE);
                $propbag->add('description', PLUGIN_SIDEBAR_CUSTOMARCHIVE_TITLE_DESC);
                $propbag->add('default',     PLUGIN_SIDEBAR_CUSTOMARCHIVE_TITLE_DEFAULT);
                break;
                
            case 'linktext':
                $propbag->add('type',        'string');
                $propbag->add('name',        PLUGIN_SIDEBAR_CUSTOMARCHIVE_LINK);
                $propbag->add('description', PLUGIN_SIDEBAR_CUSTOMARCHIVE_LINK_DESC);
                $propbag->add('default',     PLUGIN_SIDEBAR_CUSTOMARCHIVE_LINK_DEFAULT);
                break;
                
            case 'show_archives':
                $propbag->add('type',        'boolean');
                $propbag->add('name',        PLUGIN_SIDEBAR_CUSTOMARCHIVE_SHOWARCHIVES);
                $propbag->add('description', PLUGIN_SIDEBAR_CUSTOMARCHIVE_SHOWARCHIVES_DESC);
                $propbag->add('default',     'false');
                break;
                
            case 'archivtext':
                $propbag->add('type',        'string');
                $propbag->add('name',        PLUGIN_SIDEBAR_CUSTOMARCHIVE_LINKARCHIVES);
                $propbag->add('description', PLUGIN_SIDEBAR_CUSTOMARCHIVE_LINKARCHIVES_DESC);
                $propbag->add('default',     PLUGIN_SIDEBAR_CUSTOMARCHIVE_LINKARCHIVES_DEFAULT);
                break;
                
        }
        return true;
    }

    function generate_content(&$title)
    {
        global $serendipity;

        $title = $this->get_config('title');
        $link = $this->get_config('linktext');

        $rs = serendipity_plugin_api::find_plugin_id('serendipity_event_customarchive',0);
        if (!empty($rs) && is_array($rs)) {
            $customarchivepath = serendipity_get_config_var('serendipity_event_customarchive:' . $rs[0] . '/' . 'permalink', '');
        } else { $customarchivepath = $serendipity['httpPath']; }
        echo '<a title="' . $link . '" href="' . $customarchivepath . '"><b>' . $link . '</b></a>';
        if ($this->get_config('show_archives')) {
            $archivlink = $this->get_config('archivtext');
            echo '<br /><a title="' . $archivlink . '" href="' . $serendipity['httpPath'] . $serendipity['indexFile'] . '?/' . $serendipity['permalinkArchivesPath'] . '"><b>' . $archivlink . '</b></a>';
        }
    }

}

/* vim: set sts=4 ts=4 expandtab : */
?>
