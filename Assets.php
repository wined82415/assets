<?php
namespace Common;

class Assets
{
    protected $_css = array();
    protected $_js = array();

    protected $_cssDir = '';
    protected $_jsDir = '';
    protected $_minDir = '';

    protected $_doc = '';

    protected $_host = '';
    protected $_cssUrl = '';
    protected $_jsUrl = '';

    function __construct()
    {
        $this->_host = defined('WEB_ROOT') ? WEB_ROOT : '';
        $this->_doc = defined('DOC_ROOT') ? DOC_ROOT : '';

        $this->_cssDir = defined('ASSET_DIR') && defined('CSS_DIR') ? ASSET_DIR . CSS_DIR : '';
        $this->_jsDir = defined('ASSET_DIR') && defined('JS_DIR') ? ASSET_DIR . JS_DIR : '';

        $this->_cssUrl = $this->_host . $this->_cssDir;
        $this->_jsUrl = $this->_host . $this->_jsDir;

        $this->_minDir = defined('MINIFY_ROOT') ? MINIFY_ROOT : '';
    }

    function __destruct()
    {

    }

    public function addCss($css)
    {
        if (!is_array($css)) {
            $this->_error('Input is not an Array.', __FUNCTION__, __LINE__);
        }

        foreach ($css as $key => $value) {
            if (empty($key)) {
                continue;
            }
            list($key, $value) = array_map('trim', [$key, $value]);
            $this->_css[$key] = $value;
        }

        return $this;
    }

    public function addJs(Array $js)
    {
        if (!is_array($js)) {
            $this->_error('Input is not an Array.', __FUNCTION__, __LINE__);
        }

        foreach ($js as $value) {
            $this->_js[] = trim($value);
        }

        return $this;
    }

    public function removeCss($name)
    {
        if (isset($this->_css[$name])) {
            unset($this->_css[$name]);
        }

        return $this;
    }

    public function removeJs($name)
    {
        if ($index = array_search($name, $this->_js)) {
            unset($this->_js[$index]);
        }
        $this->_js = array_values($this->_js);

        return $this;
    }

    public function load()
    {
        $rtn = null;
        $stack = array_merge($this->getCssRows(), $this->getJsRows());

        foreach ($stack as $row) {
            $rtn .= $row . "\n";
        }

        return $rtn;
    }

    public function getCssRows()
    {
        $rtn = [];
        foreach ($this->_css as $key => $value) {
            if (!$this->isOutsideLink($key)) {
                $key = $this->_cssUrl . '/' . $key;
            }
            $rtn[] = $this->fillCss([$key => $value]);
        }

        return $rtn;
    }

    public function getJsRows()
    {
        $rtn = [];
        foreach ($this->_js as $value) {
            if (!$this->isOutsideLink($value)) {
                $value = $this->_jsUrl . '/' . $value;
            }
            $rtn[] = $this->fillJs($value);
        }

        return $rtn;
    }

    public function min()
    {
        $css = $js = [];
        $minPath = $this->_minDir;
        $str = '/min/?b=%s&amp;f=%s&amp;%s';
        $media = 'screen, projection, print';

        foreach ($this->_css as $key => $value) {
            if (!$this->isOutsideLink($key)) {
                $css[$key] = $value;
            }
        }

        foreach ($this->_js as $value) {
            if (!$this->isOutsideLink($value)) {
                $js[] = $value;
            }
        }

        if (isset($css['print.css']) || isset($css['page/print.css'])) {
            $media = 'all';
        }
        $url = $minPath . sprintf($str, substr($this->_doc . $this->_cssDir, 1), implode(',', array_keys($css)), date('ymdHis'));
        $minCss = $this->fillCss([$url => $media]);

        $url = $minPath . sprintf($str, substr($this->_doc . $this->_jsDir, 1), implode(',', array_filter($js)), date('ymdHis'));
        $minJs = $this->fillJs($url);

        return $minCss . PHP_EOL . $minJs;
    }

    public function setOption($key, $value)
    {
        $fields = ['cssDir', 'jsDir', 'minDir', 'host', 'doc'];
        if (in_array($key, $fields)) {
            $this->{'_' . $key} = $value;
            if (in_array($key, ['cssDir', 'jsDir'])) {
                $this->{'_' . substr($key, 0, -3)} = $this->_host . $this->{'_' . $key};
            }
        }

        return $this;
    }

    public function setOptions($options)
    {
        if (is_array($options) && !empty($options)) {
            foreach ($options as $key => $value) {
                $this->setOption($key, $value);
            }
        }

        return $this;
    }

    protected function fillCss($input)
    {
        $str = '<link href="%s" media="%s" rel="stylesheet" />';

        if (!is_array($input)) {
            $this->_error('Error Input.', __FUNCTION__, __LINE__);
        }

        return sprintf($str, key($input), empty(current($input)) ? 'screen, projection, print' : current($input));
    }

    protected function fillJs($input)
    {
        $str = '<script src="%s"></script>';

        return sprintf($str, $input);
    }

    protected function isOutsideLink($link)
    {
        preg_match("/(?:http | https):\/\/.*/x", $link, $output);

        return !empty($output) ? TRUE : FALSE;
    }

    protected function dump($type = 'all')
    {
        $rtn = [];
        if (in_array($type, ['all', 'css', 'js'])) {
            if ('all' == $type) {
                $rtn['css'] = $this->_css;
                $rtn['js'] = $this->_js;
            } else {
                $rtn[$type] = $this->{'_' . $type};
            }
        }

        return $rtn;
    }

    protected function _error($message, $method, $line)
    {
        $errormsg = sprintf("%s() on line %d: %s", $method, $line, $message);

        ob_start();
        ob_implicit_flush(0);
        debug_print_backtrace();
        $errormsg .= PHP_EOL . ob_get_contents();
        ob_end_clean();

        trigger_error($errormsg, E_USER_ERROR);
    }
}
