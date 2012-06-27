<?php

namespace Sanpi\Behatch\Context;

use Behat\Behat\Context\Step;
use Behat\Gherkin\Node\TableNode;
use PHPUnit_Framework_ExpectationFailedException as AssertException;

class BrowserContext extends BaseContext
{
    private $timeout = 10;
    private $dateFormat = 'dmYHi';

    /**
     * @AfterScenario
     */
    public function closeBrowser()
    {
        $this->getSession()->stop();
    }

    /**
     * @When /^I set basic authentication with "([^"]*)" and "([^"]*)"$/
     */
    public function iSetBasicAuthenticationWithAnd($user, $password)
    {
        $this->getSession()->setBasicAuth($user, $password);
    }

    /**
     * @Given /^(?:|I )am on url composed by$/
     */
    public function iAmOnUrlComposedBy(TableNode $tableNode)
    {
        $url = '';
        foreach ($tableNode->getHash() as $hash) {
            $param = $hash['parameters'];

            //this parameter is actually a context parameter
            if ($this->getMainContext()->hasParameter($param)) {
                $url .= $this->getMainContext()->getParameter($param);
            }
            else {
                $url .= $param;
            }
        }

        return new Step\Given(sprintf('I am on "%s"', $url));
    }

    /**
     * @When /^(?:|I )click on the ([0-9]+)(?:st|nd|rd|th) "([^"]*)" element$/
     */
    public function iClickOnTheNthElement($index, $element)
    {
        $nodes = $this->getSession()->getPage()->findAll('css', $element);

        if (isset($nodes[$index-1])) {
            $nodes[$index-1]->click();
        }
        else {
            throw new \Exception(sprintf("The element %s number %s was not found anywhere in the page", $element, $index));
        }
    }

    /**
     * @When /^(?:|I )follow the ([0-9]+)(?:st|nd|rd|th) "([^"]*)" link$/
     */
    public function iFollowTheNthLink($number, $locator)
    {
        $page = $this->getSession()->getPage();

        $links = $page->findAll('named', array(
            'link', $this->getSession()->getSelectorsHandler()->xpathLiteral($locator)
        ));

        if (!isset($links[$number-1])) {
            throw new \Exception(sprintf("The %s element %s was not found anywhere in the page", $number, $locator));
        }

        $links[$number-1]->click();
    }

    /**
     * @When /^(?:|I )fill in "([^"]*)" with the current date$/
     */
    public function iFillInWithTheCurrentDate($field)
    {
        return new Step\When(sprintf('I fill in "%s" with "%s"', $field, date($this->dateFormat)));
    }

    /**
     * @When /^(?:|I )fill in "([^"]*)" with the current date and modifier "([^"]*)"$/
     */
    public function iFillInWithTheCurentDateAndModifier($field, $modifier)
    {
        return new Step\When(sprintf('I fill in "%s" with "%s"', $field, date($this->dateFormat, strtotime($modifier))));
    }

    /**
     * @When /^(?:|I )hover "([^"]*)"$/
     */
    public function iHoverIShouldSeeIn($element)
    {
        $node = $this->getSession()->getPage()->find('css', $element);
        if ($node === null) {
            throw new \Exception(sprintf('The hovered element "%s" was not found anywhere in the page', $element));
        }
        $node->mouseOver();
    }

    /**
     * @When /^(?:|I )save the value of "([^"]*)" in the "([^"]*)" parameter$/
     */
    public function iSaveTheValueOfInTheParameter($field, $parameterName)
    {
        $field = str_replace('\\"', '"', $field);
        $node  = $this->getSession()->getPage()->findField($field);
        if ($node === null) {
            throw new \Exception(sprintf('The field "%s" was not found anywhere in the page', $field));
        }

        $this->getMainContext()->setParameter($parameterName, $node->getValue());
    }

    /**
     * @Then /^(?:|I )wait "([^"]*)" seconds until I see "([^"]*)"$/
     */
    public function iWaitsSecondsUntilISee($timeOut, $text)
    {
        $this->iWaitSecondsUntilISeeInTheElement($timeOut, $text, $this()->getSession()->getPage());
    }

    /**
     * @Then /^(?:|I )wait until I see "([^"]*)"$/
     */
    public function iWaitUntilISee($text)
    {
        $this->iWaitsSecondsUntilISee($this->timeout, $text);
    }

    /**
     * @Then /^(?:|I )wait (\d+) seconds until I see "([^"]*)" in the "([^"]*)" element$/
     */
    public function iWaitSecondsUntilISeeInTheElement($seconds, $text, $element)
    {
        $expected = str_replace('\\"', '"', $text);

        $time = 0;

        if (is_string($element)) {
            $node = $this()->getSession()->getPage()->find('css', $element);
        }
        else {
            $node = $element;
        }

        while ($time < $seconds) {
            $actual   = $node->getText();
            $e = null;

            try {
                $time++;
                assertContains($expected, $actual);
            }
            catch (AssertException $e) {
                if ($time >= $seconds) {
                    $message = sprintf('The text "%s" was not found anywhere in the text of %s atfer a %s seconds timeout', $expected, $element, $seconds);
                    throw new ResponseTextException($message, $this()->getSession(), $e);
                }
            }

            if ($e == null) {
                break;
            }

            sleep(1);
        }
    }

    /**
     * @Then /^(?:|I )wait until I see "([^"]*)" in the "([^"]*)" element$/
     */
    public function iWaitUntilISeeInTheElement($text, $element)
    {
        $this->iWaitSecondsUntilISeeInTheElement($this->timeout, $text, $element);
    }

    /**
     * @Then /^(?:|I )Should see (\d+) "([^"]*)" in the (\d+)(?:st|nd|rd|th) "([^"]*)"$/
     */
    public function iShouldSeeNElementInTheNthParent($occurences, $element, $index, $parent)
    {
        $page = $this()->getSession()->getPage();

        $parents = $page->findAll('css', $parent);
        if (!isset($parents[$index-1])) {
            throw new \Exception(sprintf("The %s element %s was not found anywhere in the page", $index, $parent));
        }

        $elements = $parents[$index-1]->findAll('css', $element);
        if (count($elements) !== (int)$occurences) {
            throw new \Exception(sprintf("%d occurences of the %s element in %s found", count($elements), $element, $parent));
        }
    }

    /**
     * @Then /^(?:|I )should see ([0-9]+) "([^"]*)" elements?$/
     */
    public function iShouldSeeNElements($occurences, $element)
    {
        $nodes = $this()->getSession()->getPage()->findAll('css', $element);
        $actual = sizeof($nodes);
        if ($actual !== (int)$occurences) {
            throw new \Exception(sprintf('%s occurences of the "%s" element found', $actual, $element));
        }
    }

    /**
     * @Then /^the element "([^"]*)" should be disabled$/
     */
    public function theElementShouldBeDisabled($element)
    {
        $node = $this()->getSession()->getPage()->find('css', $element);
        if ($node == null) {
            throw new \Exception(sprintf('There is no "%s" element', $element));
        }

        if (!$node->hasAttribute('disabled')) {
            throw new \Exception(sprintf('The element "%s" is not disabled', $element));
        }
    }

    /**
     * @Then /^the element "([^"]*)" should be enabled$/
     */
    public function theElementShouldBeEnabled($element)
    {
        $node = $this()->getSession()->getPage()->find('css', $element);
        if ($node == null) {
            throw new \Exception(sprintf('There is no "%s" element', $element));
        }

        if ($node->hasAttribute('disabled')) {
            throw new \Exception(sprintf('The element "%s" is not enabled', $element));
        }
    }

    /**
     * @Then /^(?:|I )shoud see the "([^"]*)" parameter$/
     */
    public function iShouldSeeTheParameter($parameter)
    {
        return new Step\Then(sprintf('I should see "%s"', $this->getMainContext()->getParameter($parameter)));
    }

    /**
     * @Then /^the "([^"]*)" select box should contain "([^"]*)"$/
     */
    public function theSelectBoxShouldContain($select, $option)
    {
        $select = str_replace('\\"', '"', $select);
        $option = str_replace('\\"', '"', $option);

        $optionText = $this()->getSession()->getPage()->findField($select)->getText();

        try {
            assertContains($option, $optionText);
        }
        catch (AssertException $e) {
            throw new \Exception(sprintf('The "%s" select box does not contain the "%s" option', $select, $option));
        }
    }

    /**
     * @Then /^the "([^"]*)" select box should not contain "([^"]*)"$/
     */
    public function theSelectBoxShouldNotContain($select, $option)
    {
        $select = str_replace('\\"', '"', $select);
        $option = str_replace('\\"', '"', $option);

        $optionText = $this()->getSession()->getPage()->findField($select)->getText();

        try {
            assertNotContains($option, $optionText);
        }
        catch (AssertException $e) {
            throw new \Exception(sprintf('The "%s" select box does contain the "%s" option', $select, $option));
        }
    }

    /**
     * @Then /^the "([^"]*)" element should be visible$/
     */
    public function theElementShouldBeVisible($element)
    {
        $displayedNode = $this()->getSession()->getPage()->find('css', $element);
        if ($displayedNode === null) {
            throw new \Exception(sprintf('The element "%s" was not found anywhere in the page', $element));
        }

        assertTrue($displayedNode->isVisible(), sprintf('The element "%s" is not visible', $element));
    }

    /**
     * @Then /^the "([^"]*)" element should not be visible$/
     */
    public function theElementShouldNotBeVisible($element)
    {
        $displayedNode = $this()->getSession()->getPage()->find('css', $element);
        if ($displayedNode === null) {
            throw new \Exception(sprintf('The element "%s" was not found anywhere in the page', $element));
        }

        assertFalse($displayedNode->isVisible(), sprintf('The element "%s" is not visible', $element));
    }
}
