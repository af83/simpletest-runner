<?php
/**
 * Custom runner for simpletest
 * Copyright (C) 2010 AF83
 * 
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 */

/**
 *
 */
interface SimpleTestRunner_Observer 
{
    public function render();
}

/**
 * Simple Wrapper around TestSuite
 */
class SimpleTestRunner_TestSuite extends TestSuite
{
    /**
     * Get all available tests from this suite
     * @return array
     */
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

/**
 * Custom reporter with form to select testcase
 */
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
        $test_cases = $this->sortTests(self::$allTests);
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

    protected function sortTests(array $allTests)
    {
        ksort($allTests);
        return $allTests;
    }

}

/**
 * 
 */
class SimpleTestRunner_CustomDisplay extends HtmlReporter
{
    public static $allTests = null;

    protected static $observer = null;
    protected static $runOnlyTests = null;

    protected $test_case_name = null;
    protected $nb_errors = 0;

    /**
     * Invoke only test selected
     * @return Boolean
     */
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
        <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
        <head>
            <title>Tests</title>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <style type="text/css">
            .result { padding: 8px; margin-top: 1em;  color: white;}
            .fail { background-color: red;}
            .pass { background-color: green;} pre { background-color: lightgray; color: inherit; }
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
        $result = ($this->getFailCount() + $this->getExceptionCount() > 0 ? 'fail' : 'pass');
        echo '<div class="result '. $result  .'">';
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
            echo "<div class=\"result pass\">Test passed \o/</div>";
        }
        else
        {
            echo "<div class=\"result fail\"><blink>Test failed :'(</blink></div>"; // yes we love blink ;)
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

    /**
     * Add observer called after each test
     * Useful with an http request observer
     */
    public static function setObserver(SimpleTestRunner_Observer $observer)
    {
        self::$observer = $observer;
    }
}
