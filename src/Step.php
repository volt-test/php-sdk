<?php

namespace VoltTest;

use VoltTest\Exceptions\InvalidJsonPathException;
use VoltTest\Exceptions\InvalidRegexException;
use VoltTest\Exceptions\InvalidRequestValidationException;
use VoltTest\Exceptions\InvalidStepException;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\Extractors\CookieExtractor;
use VoltTest\Extractors\Extractor;
use VoltTest\Extractors\HeaderExtractor;
use VoltTest\Extractors\HtmlExtractor;
use VoltTest\Extractors\JsonExtractor;
use VoltTest\Extractors\RegexExtractor;
use VoltTest\Validators\StatusValidator;
use VoltTest\Validators\Validator;

class Step
{
    private string $name;

    private Request $request;

    private array $extracts = [];

    private array $validations = [];

    private string $thinkTime = ''; // Default think time which is the think time after the step is executed

    public function __construct(string $name)
    {
        if (empty($name)) {
            throw new InvalidStepException('Step name cannot be empty');
        }
        $this->name = $name;
        $this->request = new Request();
    }

    /**
     * @throws InvalidRequestValidationException
     */
    public function get(string $url): self
    {
        $this->request->setMethod('GET')->setUrl($url);

        return $this;
    }

    /**
     * @throws InvalidRequestValidationException
     */
    public function post(string $url, string $body = ''): self
    {
        $this->request->setMethod('POST')->setUrl($url)->setBody($body);

        return $this;
    }

    /**
     * @throws InvalidRequestValidationException
     */
    public function delete(string $url): self
    {
        $this->request->setMethod('DELETE')->setUrl($url);

        return $this;
    }

    /**
     * @throws InvalidRequestValidationException
     */
    public function patch(string $url, string $body = ''): self
    {
        $this->request->setMethod('PATCH')->setUrl($url)->setBody($body);

        return $this;
    }

    /**
     * @throws InvalidRequestValidationException
     */
    public function put(string $url, string $body = ''): self
    {
        $this->request->setMethod('PUT')->setUrl($url)->setBody($body);

        return $this;
    }

    /**
     * @throws InvalidRequestValidationException
     */
    public function head(string $url): self
    {
        $this->request->setMethod('HEAD')->setUrl($url);

        return $this;
    }

    /**
     * @throws InvalidRequestValidationException
     */
    public function options(string $url): self
    {
        $this->request->setMethod('OPTIONS')->setUrl($url);

        return $this;
    }

    /**
     * @throws InvalidRequestValidationException
     */
    public function header(string $name, string $value): self
    {
        $this->request->addHeader($name, $value);

        return $this;
    }

    public function extractFromCookie(string $variableName, string $selector): self
    {
        $cookieExtractor = new CookieExtractor($variableName, $selector);
        if (! $cookieExtractor->validate()) {
            throw new VoltTestException(
                sprintf(
                    'Invalid regex pattern provided: "%s". Step: "%s". Variable: "%s".',
                    $selector,
                    $this->name,
                    $variableName
                )
            );
        }
        $this->extracts[] = $cookieExtractor;

        return $this;
    }

    /*
     * Extracts a value from the response header
     * @param string $variableName The name of the variable to store the extracted value
     * @param string $selector The regex pattern to use to extract the value
     * @return self
     * @throws VoltTestException
     * */
    public function extractFromHeader(string $variableName, string $selector): self
    {
        $headerExtractor = new HeaderExtractor($variableName, $selector);
        if (! $headerExtractor->validate()) {
            throw new VoltTestException(
                sprintf(
                    'Invalid regex pattern provided: "%s". Step: "%s". Variable: "%s".',
                    $selector,
                    $this->name,
                    $variableName
                )
            );
        }
        $this->extracts[] = $headerExtractor;

        return $this;
    }

    /**
     * Extracts a value from the response json body
     * @param string $variableName The name of the variable to store the extracted value
     * @param string $jsonPath The json path to use to extract the value
     * @return self
     * @throws InvalidJsonPathException
     */
    public function extractFromJson(string $variableName, string $jsonPath): self
    {
        $jsonExtractor = new JsonExtractor($variableName, $jsonPath);
        $jsonExtractor->validate();
        $this->extracts[] = $jsonExtractor;

        return $this;
    }

    /**
     * Extracts a value using a regex pattern
     * @param string $variableName The name of the variable to store the extracted value
     * @param string $selector The regex pattern to use to extract the value
     * @return self
     * @throws InvalidRegexException
     */
    public function extractFromRegex(string $variableName, string $selector): self
    {
        $regexExtractor = new RegexExtractor($variableName, $selector);
        $regexExtractor->validate();
        $this->extracts[] = $regexExtractor;

        return $this;
    }


    public function extractFromHtml(string $variableName, string $selector, ?string $attribute = null): self
    {
        $htmlExtractor = new HtmlExtractor($variableName, $selector, $attribute);
        $this->extracts[] = $htmlExtractor;
        return $this;
    }

    public function validateStatus(string $name, int $expected): self
    {
        $this->validations[] = new StatusValidator($name, $expected);

        return $this;
    }

    public function setThinkTime(string $thinkTime): self
    {
        if (! preg_match('/^\d+[smh]$/', $thinkTime)) {
            throw new VoltTestException('Invalid think time format. Use <number>[s|m|h]');
        }
        $this->thinkTime = $thinkTime;

        return $this;
    }

    public function toArray(): array
    {
        $array = [
            'name' => $this->name,
            'request' => $this->request->toArray(),
            'extract' => array_map(function (Extractor $extract) {
                return $extract->toArray();
            }, $this->extracts),
            'validate' => array_map(function (Validator $validate) {
                return $validate->toArray();
            }, $this->validations),
        ];
        if (trim($this->thinkTime) !== '') {
            $array['think_time'] = $this->thinkTime;
        }

        return $array;
    }
}
