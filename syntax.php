<?php
/**
 * DokuWiki Plugin githubbadge (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'syntax.php';
require_once DOKU_INC.'inc/JSON.php';
require_once DOKU_INC.'inc/HTTPClient.php';

class syntax_plugin_githubbadge extends DokuWiki_Syntax_Plugin {
    function getType() {
        return 'substition';
    }

    function getPType() {
        return 'normal';
    }

    function getSort() {
        return 301;
    }


    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{githubbadge>.*?\}\}',$mode,'plugin_githubbadge');
    }

    function handle($match, $state, $pos, &$handler){
        $match = substr($match,14,-2);

        $align = 0;
        if(substr($match,0,1) == ' ') $align += 1;
        if(substr($match,-1) == ' ') $align += 2;
        if($align == 1){
            $align = 'plugin_githubbadge_right';
        }elseif($align == 2){
            $align = 'plugin_githubbadge_left';
        }elseif($align = 3){
            $align = 'plugin_githubbadge_center';
        }else{
            $align = '';
        }
        $match = trim($match);

        list($user,$project) = explode('/',$match);

        $data = array( 'user'    => $user,
                       'project' => $project,
                       'align'   => $align );

        return $data;
    }

    function render($mode, &$R, $data) {
        if($mode != 'xhtml') return false;

        $info = $this->_repoinfo($data['user'],$data['project']);
        if(!$info){
            $R->doc .= 'Failed to fetch data';
            return true;
        }

        $url = 'http://github.com/'.rawurlencode($data['user']).'/'.rawurlencode($data['project']);

        $R->doc .= '<div class="plugin_githubbadge '.$data['align'].'">';


        $R->doc .= '<p class="info">';
        $R->doc .= '<span class="watchers" title="Watchers"><span>Watchers: </span><a href="'.$url.'/watchers">'.hsc($info->watchers).'</a></span> ';
        $R->doc .= '<span class="forks" title="Forks"><span>Forks: </span><a href="'.$url.'/network">'.hsc($info->forks).'</a></span> ';
        if($info->has_issues){
            $R->doc .= '<span class="issues" title="Issues"><span>Issues: </span><a href="'.$url.'/issues">'.hsc($info->open_issues).'</a></span> ';
        }
        $R->doc .= '</p>';

        $R->doc .= '<p class="description">';
        $R->doc .= '<b><a href="'.$url.'">'.hsc($data['project']).'</a></b><br />';
        $R->doc .= hsc($info->description);
        $R->doc .= '</p>';

        $R->doc .= $this->_activityimage($data['user'],$data['project']);
        $R->doc .= '</div>';


        return true;
    }

    function _repoinfo($user,$project){
        $http = new DokuHTTPClient();
        $data = $http->get('http://github.com/api/v2/json/repos/show/'.
                           rawurlencode($user).'/'.rawurlencode($project));
        if(!$data) return false;
        $json = new JSON;
        $info = $json->decode($data);
        return $info->repository;
    }

    function _activityimage($user,$project){
        $http = new DokuHTTPClient();
        $data = $http->get('http://github.com/cache/participation_graph/'.
                           rawurlencode($user).'/'.rawurlencode($project));
        if(!$data) return '';

        $data = explode("\n",$data);
        $all  = $this->_text_to_data($data[0]);
        $me   = $this->_text_to_data($data[1]);
        $max1 = max($all);
        $max2 = max($me);

        $url =  'http://chart.apis.google.com/chart?cht=bvo&chs=225x25&chco=C6D9FD,4D89F9&chbh=4,0,0'.
                '&chds=0,'.$max1.',0,'.$max1.
                '&chd=t:'.join(',',$all).'|'.join(',',$me);

        return '<img src="'.$url.'" alt="" width="225" height="25" title="activity in the last 52 weeks" />';
    }

    /**
     * Convert a string containing only characters in the range
     * [A-Za-z0-9!-] to a list of numerical data points, as specified by
     * the Google Charts extended encoding format.
     */
    function _text_to_data($text){
        $data = array();
        $len = strlen($text);
        for($i=0; $i<$len; $i += 2){
            $char1 = ord($text[$i]);
            $char2 = ord($text[$i+1]);
            $data[] = $this->_char_to_int_data($char1) * 64 + $this->_char_to_int_data($char2);
        }
        return $data;
    }


    /**
     * Convert a character in the range [A-Za-z0-9!-] from plain text into
     * an integer based on the Google Charts extended encoding.
     *
     * @link http://code.google.com/apis/chart/docs/data_formats.html#extended
     */
    function _char_to_int_data($char){

        if ($char >= 65 && $char <= 90){
            return $char - 65;  # A = 0 up to Z = 25.
        }elseif ($char >= 97 && $char <= 122){
            return $char - 97 + 26;  # a = 26 up to z = 51.
        }elseif ($char >= 48 && $char <= 57){
            return $char - 48 + 52;  # 0 = 52 up to 9 = 61.
        }elseif ($char == 33){
            return 62;  # Exclamation mark.
        }elseif ($char == 45){
            return 63;  # Minus/hyphen sign.
        }else{
            return 0; # something went wrong
        }
    }


}

// vim:ts=4:sw=4:et:enc=utf-8:
