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
require_once dirname(__FILE__) . '/json/json.php4.include.php';

@define('PLUGIN_EVENT_SPAMBLOCK_BEE_DEBUG', FALSE);

@define('PLUGIN_EVENT_SPAMBLOCK_SWTCH_OFF', 'OFF');
@define('PLUGIN_EVENT_SPAMBLOCK_SWTCH_MODERATE', 'MODERATE');
@define('PLUGIN_EVENT_SPAMBLOCK_SWTCH_REJECT', 'REJECT');


class serendipity_event_spamblock_bee extends serendipity_event
{
    var $title                 = PLUGIN_EVENT_SPAMBLOCK_BEE_TITLE;
    var $useHoneyPot           = true;
    var $hiddenCaptchaHandle   = null;
    var $answerRetrievalMethod = null;
    var $captchaAnswer         = array();
    var $captchaQuestionType   = null;
    var $useRegularExpressions = false;
    
    function serendipity_event_spamblock_bee() {
        $this->answerRetrievalMethod = $this->get_config('answer_retrieval_method', 'default');
        $this->captchaQuestionType   = $this->get_config('question_type', 'math');
        $this->useHoneyPot           = $this->get_config('do_honeypot', true);
        $this->hiddenCaptchaHandle   = $this->get_config('do_hiddencaptcha', PLUGIN_EVENT_SPAMBLOCK_SWTCH_MODERATE);
        $this->useRegularExpressions = $this->get_config('use_regexp', false);
        
    }
    
    function introspect(&$propbag)
    {
        global $serendipity;

        $propbag->add('name',          PLUGIN_EVENT_SPAMBLOCK_BEE_TITLE);
        $propbag->add('description',   PLUGIN_EVENT_SPAMBLOCK_BEE_DESC);
        $propbag->add('stackable',     false);
        $propbag->add('author',        'Grischa Brockhaus');
        $propbag->add('requirements',  array(
            'serendipity' => '0.8',
            'smarty'      => '2.6.7',
            'php'         => '4.1.0'
        ));
        
        $propbag->add('version',       '1.01');
        
        $propbag->add('event_hooks',    array(
            'frontend_comment' => true,
            'frontend_saveComment' => true,
            'frontend_footer'     => true,
            'css'               => true,
            'external_plugin'  => true,
        ));
        $propbag->add('groups', array('ANTISPAM'));
        
        $configuration = array('header_desc','do_honeypot', 'do_hiddencaptcha' );
        if (!class_exists('serendipity_event_spamblock')) { // Only do that, if spamblock is not installed.
            $configuration =array_merge($configuration, array('entrytitle', 'samebody', 'required_fields'));
        }
        $configuration =array_merge($configuration, array('spamlogtype', 'spamlogfile', 'plugin_path'));
        $configuration =array_merge($configuration, array('advanced_cc_desc', 'answer_retrieval_method', 'question_type', 'questions', 'answers', 'use_regexp'));
        
        $propbag->add('configuration', $configuration );
        $propbag->add('config_groups', array(
                PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SECTION_LOGGING => array(
                    'spamlogtype', 'spamlogfile', 'plugin_path'
                ),
                PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SECTION_ADVANCED => array(
                    'advanced_cc_desc', 'answer_retrieval_method', 'question_type', 'questions', 'answers', 'use_regexp'
                )
            )
        );
    }

    function generate_content(&$title) {
        $title = PLUGIN_EVENT_SPAMBLOCK_BEE_TITLE;
    }

    function introspect_config_item($name, &$propbag)
    {
        global $serendipity;
        
        $rejectType = array(
            PLUGIN_EVENT_SPAMBLOCK_SWTCH_OFF => PLUGIN_EVENT_SPAMBLOCK_BEE_RESULT_OFF,
            PLUGIN_EVENT_SPAMBLOCK_SWTCH_MODERATE => PLUGIN_EVENT_SPAMBLOCK_BEE_RESULT_MODERATE,
            PLUGIN_EVENT_SPAMBLOCK_SWTCH_REJECT => PLUGIN_EVENT_SPAMBLOCK_BEE_RESULT_REJECT,
        );
        
        $retrievalMethod = array(
            'default' => PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_RM_DEFAULT,
            'json' => PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_RM_JSON,
            'smarty' => PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_RM_SMARTY
        );
        
        $questionType = array(
            'math' => PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_QT_MATH,
            'custom' => PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_QT_CUSTOM
        );
        
        switch($name) {
            case 'header_desc': 
                $propbag->add('type',    'content');
                $propbag->add('default', PLUGIN_EVENT_SPAMBLOCK_BEE_EXTRA_DESC .
                    '<img src="' . $serendipity['baseURL'] . 'index.php?/plugin/spamblockbee.png" alt="" title="' . PLUGIN_EVENT_SPAMBLOCK_BEE_TITLE . '" style="float:right">'                );
                break;

            case 'do_honeypot':
                $propbag->add('type',        'boolean');
                $propbag->add('name',        PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SPAM_HONEYPOT);
                $propbag->add('description', PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SPAM_HONEYPOT_DESC);
                $propbag->add('default',     true);
                break;
            
            case 'do_hiddencaptcha':
                $propbag->add('type',        'select');
                $propbag->add('name',        PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SPAM_HCAPTCHA);
                $propbag->add('description', PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SPAM_HCAPTCHA_DESC);
                $propbag->add('select_values', $rejectType);
                $propbag->add('default',     PLUGIN_EVENT_SPAMBLOCK_SWTCH_MODERATE);
                break;

            case 'required_fields':
                $propbag->add('type',        'string');
                $propbag->add('name',        PLUGIN_EVENT_SPAMBLOCK_BEE_REQUIRED_FIELDS);
                $propbag->add('description', PLUGIN_EVENT_SPAMBLOCK_BEE_REQUIRED_FIELDS_DESC);
                $propbag->add('default',     '');
                break;
            
            case 'entrytitle':
                $propbag->add('type',           'select');
                $propbag->add('name',           PLUGIN_EVENT_SPAMBLOCK_BEE_FILTER_TITLE);
                $propbag->add('description',    PLUGIN_EVENT_SPAMBLOCK_BEE_FILTER_TITLE_DESC);
                $propbag->add('select_values',  $rejectType);
                $propbag->add('default',        PLUGIN_EVENT_SPAMBLOCK_SWTCH_REJECT);
                break;
            
            case 'samebody':
                $propbag->add('type',          'select');
                $propbag->add('name',          PLUGIN_EVENT_SPAMBLOCK_BEE_FILTER_SAMEBODY);
                $propbag->add('description',   PLUGIN_EVENT_SPAMBLOCK_BEE_FILTER_SAMEBODY_DESC);
                $propbag->add('select_values', $rejectType);
                $propbag->add('default',       PLUGIN_EVENT_SPAMBLOCK_SWTCH_REJECT);
                break;
                
            case 'spamlogtype':
                $logtypevalues = array (
                    'none' => PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SPAM_LOGTYPE_NONE,
                    'file' => PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SPAM_LOGTYPE_FILE,
                    'db' => PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SPAM_LOGTYPE_DATABASE,
                );
                $propbag->add('type',          'select');
                $propbag->add('name',          PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SPAM_LOGTYPE);
                $propbag->add('description',   PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SPAM_LOGTYPE_DESC);
                $propbag->add('select_values', $logtypevalues);
                $propbag->add('default',       'none');
                break;
            
            case 'spamlogfile':
                $propbag->add('type',        'string');
                $propbag->add('name',        PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SPAM_LOGFILE);
                $propbag->add('description', PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_SPAM_LOGFILE_DESC);
                $propbag->add('default',     $serendipity['serendipityPath'] . 'spamblock.log');
                break;

            case 'plugin_path':
                $propbag->add('type',        'string');
                $propbag->add('name',        PLUGIN_EVENT_SPAMBLOCK_BEE_PATH);
                $propbag->add('description', PLUGIN_EVENT_SPAMBLOCK_BEE_PATH_DESC);
                $propbag->add('default',     $serendipity['serendipityHTTPPath'] . 'plugins/serendipity_event_spamblock_bee/');
                break;
            
            case 'advanced_cc_desc':
                $propbag->add('type',          'content');
                $propbag->add('default',       PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_DESC);
                break;
            
            case 'answer_retrieval_method':
                $propbag->add('type',          'select');
                $propbag->add('name',          PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_ANSWER_RETRIEVAL);
                $propbag->add('description',   PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_ANSWER_RETRIEVAL_DESC);
                $propbag->add('select_values', $retrievalMethod);
                $propbag->add('default',       'default');
                break;
            
            case 'question_type':
                $propbag->add('type',          'select');
                $propbag->add('name',          PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_QUESTION_TYPE);
                $propbag->add('description',   PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_QUESTION_TYPE_DESC);
                $propbag->add('select_values', $questionType);
                $propbag->add('default',       'math');
                break;
            
            case 'questions':
                $propbag->add('type',          'text');
                $propbag->add('name',          PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_QUESTIONS);
                $propbag->add('description',   PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_QUESTIONS_DESC);
                $propbag->add('default',       PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_DEFAULT_QUESTIONS);
                break;
            
            case 'answers':
                $propbag->add('type',          'text');
                $propbag->add('name',          PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_ANSWERS);
                $propbag->add('description',   PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_ANSWERS_DESC);
                $propbag->add('default',       PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_DEFAULT_ANSWERS);
                break;
            
            case 'use_regexp':
                $propbag->add('type',          'boolean');
                $propbag->add('name',          PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_USE_REGEXP);
                $propbag->add('description',   PLUGIN_EVENT_SPAMBLOCK_BEE_CONFIG_ADV_USE_REGEXP_DESC);
                $propbag->add('default',       false);
                break;
            
            default:
                return false;
        }
        return true;
    }
    
    function event_hook($event, &$bag, &$eventData, $addData = null) {
        global $serendipity;

        $hooks = &$bag->get('event_hooks');
        if (isset($hooks[$event])) {
            switch($event) {
                case 'external_plugin':
                    switch($eventData) {
                        case 'spamblockbee.png':
                            header('Content-Type: image/png');
                            echo file_get_contents(dirname(__FILE__). '/spamblockbee.png');
                            break;
                        case 'spamblockbeecaptcha':
                            echo $this->produceCaptchaAnswerJson();
                            break;
                    }
                    break;
                
                case 'frontend_saveComment':
                    // Check only, if no one else denied it before
                    if (!is_array ( $eventData ) || serendipity_db_bool ( $eventData ['allow_comments'] )) {
                        return $this->checkComment($eventData, $addData);
                    }
                    return true;
                    break;
                case 'frontend_comment':
                    $this->printCommentEditExtras($eventData, $addData);
                    break;
                case 'frontend_footer':
                    // Comment header code only if in single article mode
                    if (!empty($eventData['GET']['id'])) {
                        $this->printJsExtras();
                    }
                    break;
                    case 'css':
                    $this->printCss($eventData, $addData);
                    break;
                default:
                    return false;
                    break;
            }
            return true;
        } else {
            return false;
        }
    }
    
    function install() {
    }
    
    function cleanup() {
    }
    
    function checkComment(&$eventData, &$addData) {
        global $serendipity;
        
        if ("NORMAL" == $addData['type']) { // only supported for normal comments
            
            // Check for honey pot:
            if ($this->useHoneyPot && (!empty($serendipity['POST']['phone']) || $serendipity['POST']['phone']=='0') ) {
                $this->spamlog($eventData['id'], 'REJECTED', "BEE Honeypot [" . $serendipity['POST']['phone'] . "]", $addData);
                $eventData = array('allow_comments' => false);
                return false;
            }
            
            // Check hidden Captcha
            if (PLUGIN_EVENT_SPAMBLOCK_SWTCH_OFF != $this->hiddenCaptchaHandle) {
                $answer                  = trim(strtolower($serendipity['POST']['beecaptcha']));
                $correctAnswer           = $this->getCaptchaAnswer();
                $correctAnswer['answer'] = strtolower($correctAnswer['answer']);
                $isCorrect               = false;
                
                // If provided answer is longer than 1000 characters and RegExp matching is on,
                // reject comment for security reasons (minimize risk of ReDoS)
                if ($this->useRegularExpressions && strlen($answer) > 1000) {
                    return false;
                }
                
                if ($this->captchaQuestionType == 'custom' && $this->useRegularExpressions) {
                    // Sanitize regular expression and remove answer part
                    $pattern = preg_replace('/^\s*\/(.*)\/\s*[imsxeADSUXJu]*\s*$/s', '$1', $correctAnswer['pattern']);
                    $pattern = addcslashes($pattern, '\\');
                    
                    // Try to match pattern with given answer
                    $match = @preg_match('/' . $pattern . '/si', $answer);
                    
                    // If pattern contains errors, fall back to basic string comparison
                    if ($match === false) {
                        $this->useRegularExpressions = false;
                    } else {
                        $isCorrect = ($match === 1);
                    }
                }
                
                if ($this->captchaQuestionType != 'custom' || !$this->useRegularExpressions) {
                    $isCorrect = ($answer == $correctAnswer['answer']);
                }
                
                // Also allow numbers as words
                if (!$isCorrect && $this->captchaQuestionType == 'math') {
                    $number    = $this->generateNumberString($correctAnswer['answer']);
                    $isCorrect = ($answer == $number && $number != 'ERROR');
                }
                
                if (!$isCorrect) {
                    $this->processComment($this->hiddenCaptchaHandle, $eventData, $addData, PLUGIN_EVENT_SPAMBLOCK_BEE_ERROR_HCAPTCHA, "BEE HiddenCaptcha [ $correct != $answer ]");
                }
                
                return $isCorrect;
            }
        }
        
        // AntiSpam check, the general spamblock supports, too: Only if spamblock is not installed.
        if (!class_exists('serendipity_event_spamblock')) {
            
            // Check for required fields. Don't log but tell the user about the fields.
            $spamdetected = false; 
            $required_fields = $this->get_config('required_fields', '');
            if (!empty($required_fields)) {
                $required_field_list = explode(',', $required_fields);
                foreach($required_field_list as $required_field) {
                    $required_field = trim($required_field);
                    if (empty($addData[$required_field])) {
                        $this->reject($eventData, $addData, sprintf(PLUGIN_EVENT_SPAMBLOCK_BEE_REASON_REQUIRED_FIELD, $required_field));
                        $spamdetected = true;
                    }
                }
            }
            if ($spamdetected) return false;
            
            // Check if entry title is the same as comment body
            $spamHandle = $this->get_config('entrytitle', PLUGIN_EVENT_SPAMBLOCK_SWTCH_REJECT);
            if (PLUGIN_EVENT_SPAMBLOCK_SWTCH_OFF!=$spamHandle && trim($eventData['title']) == trim($addData['comment'])) {
                $this->processComment($spamHandle, $eventData, $addData, PLUGIN_EVENT_SPAMBLOCK_BEE_ERROR_BODY, "BEE Body the same as title");
                return false;
            }
            
            // This check loads from DB, so do it last!
            // Check if we already have a comment with the same body. (it's a reload normaly)
            $spamHandle = $this->get_config('samebody', PLUGIN_EVENT_SPAMBLOCK_SWTCH_REJECT);
            if (PLUGIN_EVENT_SPAMBLOCK_SWTCH_OFF!=$spamHandle) {
                $query = "SELECT count(id) AS counter FROM {$serendipity['dbPrefix']}comments WHERE type = '" . $addData['type'] . "' AND body = '" . serendipity_db_escape_string($addData['comment']) . "'";
                // This is a little different to the normal Spam Plugin: 
                // We allow the same comment, if it is a trackback, but never on the same article
                // (One article sending trackbacks to more than one local article)
                if ($addData['type'] == 'PINGBACK' ||  $addData['type'] == 'TRACKBACK') {
                    $query .= ' AND entry_id=' . $eventData['id'];                        
                }
                $row   = serendipity_db_query($query, true);
                if (is_array($row) && $row['counter'] > 0) {
                    $this->processComment($spamHandle, $eventData, $addData, PLUGIN_EVENT_SPAMBLOCK_BEE_ERROR_BODY, "BEE Body already saved");
                    return false;
                }
                
            }
            if ($spamdetected) return false;
        }
        
        return true;
    }

    /**
     * Rejects or moderate a comment. Convenience function.
     */
    function processComment($spamHandle, &$eventData, &$addData, $remoteResponse, $logResponse = NULL) {
        if ($spamHandle == PLUGIN_EVENT_SPAMBLOCK_SWTCH_MODERATE) {
            $this->moderate($eventData, $addData, $remoteResponse, $logResponse);
        }
        else {
            $this->reject($eventData, $addData, $remoteResponse, $logResponse);
        }
    }
    
    /**
     * Rejects a comment with optional log entry
     */
    function reject(&$eventData, &$addData, $remoteResponse, $logResponse = NULL) {
        global $serendipity;

        if (!empty($logResponse)) {
            $this->spamlog($eventData['id'], 'REJECTED', $logResponse, $addData);
        }
        $eventData = array('allow_comments' => false);
        $serendipity['csuccess']        = 'false';
        $serendipity['messagestack']['comments'][] = $remoteResponse;

        $this->log(print_r($serendipity['messagestack'], true));
    }
    /**
     * Moderate a comment with optional log entry
     */
    function moderate(&$eventData, &$addData, $remoteResponse, $logResponse = NULL) {
        global $serendipity;
                
        if (!empty($logResponse)) {
            $this->spamlog($eventData['id'], 'MODERATE', $logResponse, $addData);
        }
        $eventData['moderate_comments'] = true;
        $serendipity['csuccess']        = 'moderate';
        $serendipity['moderate_reason'] = $remoteResponse;
        $serendipity['messagestack']['comments'][] = $remoteResponse;

        $this->log(print_r($serendipity['messagestack'], true));
    }
    
    function produceCaptchaAnswerJson() {
        $answer = $this->getCaptchaAnswer();
        if (null === $answer['answer']) {
            $answer='ERROR';
        }
        return json_encode(array('answer' => $answer['answer']));
    }
    
    function printJsExtras() {
        if ($this->answerRetrievalMethod == 'smarty') {
            return;
        }
        
        global $serendipity;
        
        if (PLUGIN_EVENT_SPAMBLOCK_SWTCH_OFF != $this->hiddenCaptchaHandle) {
            $path   = $this->path = $this->get_config('plugin_path', $serendipity['serendipityHTTPPath'] . 'plugins/serendipity_event_spamblock_bee/');
            $answer = $this->getCaptchaAnswer();
            $answer = $answer['answer'];
            
            echo '<script> var spamBeeData = {';
            
            if ($this->answerRetrievalMethod == 'json') {
                echo "'url': '" . $serendipity['baseURL'] . "index.php/plugin/spamblockbeecaptcha', " .
                     "'method': 'json'";
            } else {
                echo "'answer': " . (is_numeric($answer) ? $answer : "'" . trim($answer) . "'") . ', ' .
                     "'method': 'default'";
            }
            
            echo '};</script>' . "\n" .
                 '<script type="text/javascript" src="' . $path . 'serendipity_event_spamblock_bee.js"></script>';
        }
    }
    
    function printCommentEditExtras(&$eventData, &$addData) {
        global $serendipity;

        // Don't put extras on admin menu. They are not working there:
        if (isset($eventData['GET']['action']) && $eventData['GET']['action']=='admin') return;
             
        // Honeypot
        if (serendipity_db_bool($this->useHoneyPot)) {
            echo '<div id="serendipity_comment_phone" class="serendipity_commentDirection comment_phone_input" >' . "\n";
            echo '<label for="serendipity_commentform_phone">Phone*</label>' . "\n";
            echo '<input class="comment_phone_input" type="text" id="serendipity_commentform_phone" name="serendipity[phone]" value="" placeholder="' . PLUGIN_EVENT_SPAMBLOCK_BEE_WARN_HONEPOT . '"/>' . "\n";
            echo "</div>\n";
        }

        // Captcha
        if (PLUGIN_EVENT_SPAMBLOCK_SWTCH_OFF != $this->hiddenCaptchaHandle) {
            $question = $this->generateCaptchaQuestion();
            
            echo '<div id="serendipity_comment_beecaptcha" class="form_field">' . "\n";
            echo '<label for="bee_captcha">'. $question. '</label>' . "\n";
            echo '<input class="" type="text" id="bee_captcha" name="serendipity[beecaptcha]" value="" placeholder=""/>' . "\n";
            echo "</div>\n";
        }
    }
    
    function printCss(&$eventData, &$addData) {
        global $serendipity;

        // Hide and reveal classes by @yellowled used be the RSS chooser:
        if (PLUGIN_EVENT_SPAMBLOCK_SWTCH_OFF != $this->hiddenCaptchaHandle) {
?>
.spambeehidden {
    border: 0;
    clip: rect(0 0 0 0);
    height: 1px;
    margin: -1px;
    overflow: hidden;
    padding: 0;
    position: absolute;
    width: 1px;
}
<?php 
        }
        
        if (!(strpos($eventData, '.comment_phone_input'))) {
?>
.comment_phone_input {
    max-width: 100%;
    display:none;
    visibility:hidden;
}
<?php
        }
    }
    
    function hashString( $what ) {
        $installation_secret = $this->get_config('installation_secret');
        if (empty($installation_secret)) {
            $installation_secret = md5(date('l jS \of F Y h:i:s A'));
            $this->set_config('installation_secret', $installation_secret);
        }
        return md5($installation_secret . ':' . $what);
    }
    
    function generateCaptchaQuestion() {
        if ($this->captchaQuestionType == 'custom') {
            $question = $this->selectRandomCustomCaptchaQuestion();
            if (null === $question) {
                // no valid question could be selected, fall back to math questions
                $this->captchaQuestionType = 'math';
                $this->set_config('question_type', 'math');
            } else {
                $this->setCaptchaAnswer($question['answer']);
                return $question['question'];
            }
        }
        
        if ($this->captchaQuestionType == 'math') {
            $captchaData = $this->generateCaptchaMathProblem();
            $this->setCaptchaAnswer($captchaData['answer']);
            
            $method = PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_PLUS;
            if ($captchaData['operator'] == '-') {
                $method = PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_MINUS;
            }
            
            return PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_QUEST . ' ' .
                   $this->generateNumberString($captchaData['n1']) . " " .
                   $method . " " . $this->generateNumberString($captchaData['n2']) . '?'; 
        }
    }
    
    function getCaptchaAnswer() {
        if (!isset($this->captchaAnswer['answer']) && isset($_SESSION['spamblockbee']['captcha'])) {
            $this->captchaAnswer = $_SESSION['spamblockbee']['captcha'];
        }
        
        // If for some reason RegExp matching is on, but no pattern is present,
        // turn of RegExp matching
        if ($this->useRegularExpressions && !isset($this->captchaAnswer['pattern'])) {
            $this->useRegularExpressions = false;
        }
        
        return $this->captchaAnswer;
    }
    
    function setCaptchaAnswer($answer) {
        $answer = array('answer' => $answer);
        
        // Split answer into array if RegExp matching is on
        if ($this->captchaQuestionType == 'custom' && $this->useRegularExpressions) {
            $delimiterIndex = strrpos($answer['answer'], ':');
            
            if ($delimiterIndex !== false) {
                $answer = array(
                    'pattern' => substr($answer['answer'], 0, $delimiterIndex),
                    'answer'  => substr($answer['answer'], $delimiterIndex + 1)
                );
            } else {
                // Answer contains either no pattern or no answer part, fall back to string matching
                $this->useRegularExpressions = false;
            }
        }
        
        $this->captchaAnswer                 = $answer;
        $_SESSION['spamblockbee']['captcha'] = $this->captchaAnswer;
    }
    
    function generateCaptchaMathProblem() {
        $result = array();
        
        $number1 = rand(0,9);
        $number2 = rand(0,9);
        if (($number1 + $number2) > 10 ) {
            // Substract them
            $result['operator'] = '-';
            if ($number1>$number2) {
                $result['n1'] = $number1;
                $result['n2'] = $number2;
                $result['answer'] =  $number1 - $number2;
            }
            else {
                $result['n2'] = $number1;
                $result['n1'] = $number2;
                $result['answer'] =  $number2 - $number1;
            }                
        } else {
                // Add them
                $result['operator'] = '+';
                $result['n1'] = $number1;
                $result['n2'] = $number2;
                $result['answer'] =  $number1 + $number2;
        }
        
        return $result;
    }
    
    function generateNumberString($number) {
        //$number = (int)$number;
        switch ($number) {
            case 0: return PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_0;
            case 1: return PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_1;
            case 2: return PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_2;
            case 3: return PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_3;
            case 4: return PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_4;
            case 5: return PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_5;
            case 6: return PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_6;
            case 7: return PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_7;
            case 8: return PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_8;
            case 9: return PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_9;
            case 10: return PLUGIN_EVENT_SPAMBLOCK_BEE_CAPTCHA_10;
            default: return "ERROR";
        }
    }
    
    function selectRandomCustomCaptchaQuestion() {
        $questions = trim($this->get_config('questions', ''));
        $answers   = trim($this->get_config('answers', ''));
        if (empty($questions) || empty($answers)) {
            return null;
        }
        
        $questions = preg_split('/(?:\r?\n|\r)/', $questions);
        $answers   = preg_split('/(?:\r?\n|\r)/', $answers);
        
        // ignore questions without answer
        if (count($questions) > count($answers)) {
            array_splice($questions, count($answers));
        }
        
        // if no questions left
        if (!count($questions)) {
            return null;
        }
        
        $questionIndex = rand(0, count($questions) - 1);
        return array(
            'question' => trim($questions[$questionIndex]),
            'answer'   => trim($answers[$questionIndex])
        );
    }
    
    function log($message){
        if (!PLUGIN_EVENT_SPAMBLOCK_BEE_DEBUG) return;
        $fp = fopen(dirname(__FILE__) . '/spambee.log','a');
        fwrite($fp, date('Y.m.d H:i:s') . " - " . $message . "\n");
        fflush($fp);
        fclose($fp);
    }
    
    function spamlog($id, $switch, $reason, $addData) {
        global $serendipity;
        
        $method = $this->get_config('spamlogtype', 'none');
        $logfile = $this->get_config('spamlogfile', $serendipity['serendipityPath'] . 'spamblock.log');
        
        switch($method) {
            case 'file':
                if (empty($logfile)) {
                    return;
                }
                if (strpos($logfile, '%') !== false) {
                    $logfile = strftime($logfile);
                }

                $fp = @fopen($logfile, 'a+');
                if (!is_resource($fp)) {
                    return;
                }
                fwrite($fp, sprintf(
                    '[%s] - [%s: %s] - [#%s, Name "%s", E-Mail "%s", URL "%s", User-Agent "%s", IP %s] - [%s]' . "\n",
                    date('Y-m-d H:i:s', serendipity_serverOffsetHour()),
                    $switch,
                    $reason,
                    $id,
                    str_replace("\n", ' ', $addData['name']),
                    str_replace("\n", ' ', $addData['email']),
                    str_replace("\n", ' ', $addData['url']),
                    str_replace("\n", ' ', $_SERVER['HTTP_USER_AGENT']),
                    $_SERVER['REMOTE_ADDR'],
                    str_replace("\n", ' ', $addData['comment'])
                ));

                fclose($fp);
                break;

            case 'none':
                return;
                break;

            case 'db':
            default:
                $reason = serendipity_db_escape_string($reason);
                $q = sprintf("INSERT INTO {$serendipity['dbPrefix']}spamblocklog
                                          (timestamp, type, reason, entry_id, author, email, url,  useragent, ip,   referer, body)
                                   VALUES (%d,        '%s',  '%s',  '%s',     '%s',   '%s',  '%s', '%s',      '%s', '%s',    '%s')",

                           serendipity_serverOffsetHour(),
                           serendipity_db_escape_string($switch),
                           serendipity_db_escape_string($reason),
                           serendipity_db_escape_string($id),
                           serendipity_db_escape_string($addData['name']),
                           serendipity_db_escape_string($addData['email']),
                           serendipity_db_escape_string($addData['url']),
                           substr(serendipity_db_escape_string($_SERVER['HTTP_USER_AGENT']), 0, 255),
                           serendipity_db_escape_string($_SERVER['REMOTE_ADDR']),
                           substr(serendipity_db_escape_string(isset($_SESSION['HTTP_REFERER']) ? $_SESSION['HTTP_REFERER'] : $_SERVER['HTTP_REFERER']), 0, 255),
                           serendipity_db_escape_string($addData['comment'])
                );

                serendipity_db_schema_import($q);
                break;
        }
    }    
}