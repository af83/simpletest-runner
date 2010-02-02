<?php
/**
 *  Copyright (c) 2010, AF83
 *  All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1° Redistributions of source code must retain the above copyright notice,
 *  this list of conditions and the following disclaimer.
 *
 *  2° Redistributions in binary form must reproduce the above copyright notice,
 *  this list of conditions and the following disclaimer in the documentation
 *  and/or other materials provided with the distribution.
 *
 *  3° Neither the name of AF83 nor the names of its contributors may be used
 *  to endorse or promote products derived from this software without specific
 *  prior written permission.
 *
 *  THIS SOFTWARE IS PROVIDED BY THE COMPANY AF83 AND CONTRIBUTORS "AS IS"
 *  AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
 *  THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 *  CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 *  EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 *  PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 *  PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 *  OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 *  NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
 *  EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

class SimpleTestRunner_TestSuite extends TestSuite
{
    public function getAllTests()
    {
        $tests = array();
        foreach ($this->_test_cases as $suite)
        {
            foreach ($suite->_test_cases as $test_case)
            {
                $instance = new $test_case();
                $tests[$test_case] = $instance->getTests();
            }
        }
        return $tests;
    } 
}

class SimpleTestRunner_Form extends SimpleTestRunner_CustomDisplay
{

    public function __construct()
    {
        parent::__construct();
        if (isset($_GET['tests_to_run']))
        {
            self::$runOnlyTests = $_GET['tests_to_run'];
        }
    }

    public function paintHeader($test_name)
    {
        parent::paintHeader($test_name);
        $form = $this->displayForm();
        echo <<<EOS
        <div class="tests">$form</div>
EOS;
        flush();
    }

    public function displayForm()
    {
        if(!isset($_GET['submit']))
        {
            $this->makeDry();
        }
        else
        {

        }
        $test_cases = self::$allTests;
        $form = '<form action="" method="get">';
        foreach($test_cases as $suite => $tests)
        {
            $form .= "<p><strong>$suite</strong></p>";
            foreach($tests as $test)
            {
                $checked = isset($_GET['tests_to_run'][$suite]) && in_array($test, $_GET['tests_to_run'][$suite]) ? 'checked="checked"': '';
                $form .= "<label><input type=\"checkbox\" name=\"tests_to_run[$suite][]\" value=\"$test\" $checked />$test</label><br/>";
            }
            $form .= '<hr/>';
        }
        $form .= '<p><input type="submit" value="Launch tests &rarr;" name="submit"/></p>';
        $form .= '</form>';
        return $form;
    }

}

class SimpleTestRunner_CustomDisplay extends HtmlReporter
{
    public static $allTests = null;

    protected static $observer = null;
    protected static $runOnlyTests = null;

    protected $test_case_name = null;
    protected $nb_errors = 0;

    public function __construct()
    {
        parent::HtmlReporter();
    }

    public function shouldInvoke($test_case_name, $method)
    {
        $this->test_case_name = $test_case_name;
        if (is_null(self::$runOnlyTests))
        {
            return parent::shouldInvoke($test_case_name, $method);
        }
        else
        {
            return (isset(self::$runOnlyTests[$test_case_name]) ? in_array($method, self::$runOnlyTests[$test_case_name]) : false);
        }
        
    }

    public function paintHeader($test_name)
    {
        echo <<<EOS
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
        <head>
            <title>Tests</title>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <style type="text/css">
            .fail { background-color: inherit; color: red; }.pass { background-color: inherit; color: green; } pre { background-color: lightgray; color: inherit; }
        .tests { width: 400px; margin-right: 5px; padding: 3px;font-size: 0.9em; float: left; background-color: #BFBFBF;}
            </style>
            <script type="text/javascript" src="/js/prototype.js"></script>
            <script type="text/javascript" src="/js/scriptaculous.js"></script>
            <script type="text/javascript" src="/js/effects.js"></script>
            <script type="text/javascript" src="/js/tests.js"></script>
        </head>
        <body>
EOS;
        flush();
    }

    public function paintFooter($test_name)
    {
        $colour = ($this->getFailCount() + $this->getExceptionCount() > 0 ? "red" : "green");
        echo "<div style=\"padding: 8px; margin-top: 1em; background-color: $colour; color: white;\">";
        echo $this->getTestCaseProgress() . "/" . $this->getTestCaseCount();
        echo " test cases complete:\n";
        echo "<strong>" . $this->getPassCount() . "</strong> passes, ";
        echo "<strong>" . $this->getFailCount() . "</strong> fails and ";
        echo "<strong>" . $this->getExceptionCount() . "</strong> exceptions.";
        echo <<<EOS
        </div>
        </body>
        </html>
EOS;
        flush();
    }

    public function paintCaseStart($test_name)
    {
        echo '<div><h1>Class: ' . $this->test_case_name . '</h1>';
        parent::paintCaseStart($test_name);
        flush();
    }

    public function paintCaseEnd($test_name)
    {
        parent::paintCaseEnd($test_name);
        echo '<hr/><br/><br/></div>';
        flush();
    }

    public function paintMethodStart($test_name)
    {
        $this->nb_errors = $this->_exceptions + $this->_fails;
        echo "<h2>Test: $test_name</h2>";
        $this->_test_stack[] = $test_name;
        flush();
    }

    public function paintMethodEnd($test_name)
    {
        $observer_output = is_null(self::$observer) ? '' : self::$observer->render();
        echo <<<EOS
            <div>
                $observer_output
            </div>
EOS;
        if($this->methodDidNotFailYet())
        {
            echo "<div style=\"padding: 8px; margin-top: 1em; background-color: green; color: white;\">Test passed \o/</div>";
        }
        else
        {
            echo "<div style=\"padding: 8px; margin-top: 1em; background-color: red; color: white;\"><blink>Test failed :'(</blink></div>";
        }
        array_pop($this->_test_stack);
        flush();
    }

    public function methodDidNotFailYet()
    {
        if($this->nb_errors == $this->_exceptions + $this->_fails)
        {
            $this->nb_errors = $this->_exceptions + $this->_fails;
            return true;
        }
        return false;
    }

    public static function setObserver(HtmlRequestObserver $observer)
    {
        self::$observer = $observer;
    }
}
