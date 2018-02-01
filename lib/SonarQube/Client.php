<?php
/**
 * Copyright (c) 2016. Spirit-Dev
 *    _             _
 *   /_`_  ._._/___/ | _
 * . _//_//// /   /_.'/_'|/
 *    /
 *
 * By Jean Bordat ( Twitter @Ji_Bay_ )
 * Since 2K10 until today
 * @mail <bordat.jean@gmail.com>
 *
 * hex: 53 70 69 72 69 74 2d 44 65 76
 */

namespace SonarQube;

use Buzz\Client\ClientInterface;
use Buzz\Client\Curl;
use SonarQube\Exception\InvalidArgumentException;
use SonarQube\HttpClient\HttpClient;
use SonarQube\HttpClient\HttpClientInterface;
use SonarQube\HttpClient\Listener\AuthListener;

/**
 * @property object action_plans
 * @property \SonarQube\Api\Interfaces\AuthenticationInterface authentication
 * @property object coverage
 * @property object duplications
 * @property object events
 * @property object favorites
 * @property object issue_filters
 * @property object issues
 * @property object languages
 * @property object manual_measures
 * @property \SonarQube\Api\Interfaces\MeasuresInterface measures
 * @property object metrics
 * @property object permissions
 * @property object profiles
 * @property \SonarQube\Api\Interfaces\ProjectsInterface projects
 * @property object properties
 * @property object qualitygates
 * @property object qualityprofiles
 * @property object resources
 * @property object rules
 * @property object server
 * @property object sources
 * @property object system
 * @property object tests
 * @property object timemachine
 * @property object updatecenter
 * @property object user_properties
 * @property object users
 * @property object webservices
 */
class Client {

    // TODO Comment

    const AUTH_BASIC_TOKEN = 'basic_token';

    private $options = array(
        'user-agent' => 'php-sonarqube-api (http://github.com/anup-khanal-reisys/sonar-query)',
        'timeout' => 60
    );

    private $baseUrl;
    private $username;
    private $password;

    private $httpClient;

    public function __construct($baseUrl, $username, $password, ClientInterface $httpClient = null) {

        $httpClient = $httpClient ?: new Curl();
        $httpClient->setTimeout($this->options['timeout']);
        $httpClient->setVerifyPeer(false);

        $this->baseUrl = $baseUrl;
        $this->username = $username;
        $this->password = $password;
        $this->httpClient = new HttpClient($this->baseUrl, $this->options, $httpClient);
        $this->authenticate();
    }

    private function authenticate($authMethod = self::AUTH_BASIC_TOKEN) {
        $this->httpClient->addListener(new AuthListener($authMethod, $this->username, $this->password));
        return $this;
    }

    public function getHttpClient() {
        return $this->httpClient;
    }

    public function setHttpClient(HttpClientInterface $httpClient) {
        $this->httpClient = $httpClient;

        return $this;
    }

    public function getBaseUrl() {
        return $this->baseUrl;
    }

    public function setBaseUrl($url) {
        $this->baseUrl = $url;

        return $this;
    }

    public function clearHeaders() {
        $this->httpClient->clearHeaders();

        return $this;
    }

    public function setHeaders(array $headers) {
        $this->httpClient->setHeaders($headers);

        return $this;
    }

    public function getOption($name) {
        if (!array_key_exists($name, $this->options)) {
            throw new InvalidArgumentException(sprintf('Undefined option called: "%s"', $name));
        }

        return $this->options[$name];
    }

    public function setOption($name, $value) {
        if (!array_key_exists($name, $this->options)) {
            throw new InvalidArgumentException(sprintf('Undefined option called: "%s"', $name));
        }

        $this->options[$name] = $value;

        return $this;
    }

    public function __get($api) {
        return $this->api($api);
    }

    public function api($name) {

        switch ($name) {
            case 'action_plans':
                break;
            case 'authentication':
                $api = new Api\Authentication($this);
                break;
            case 'coverage':
                break;
            case 'duplications':
                break;
            case 'events':
                break;
            case 'favorites':
                break;
            case 'issue_filters':
                break;
            case 'issues':
                $api = new Api\Issues($this);
                break;
            case 'languages':
                break;
            case 'manual_measures':
                break;
            case 'measures':
                $api = new Api\Measures($this);
                break;
            case 'metrics':
                break;
            case 'permissions':
                $api = new Api\Permissions($this);
                break;
            case 'profiles':
                break;
            case 'projects':
                $api = new Api\Projects($this);
                break;
            case 'properties':
                break;
            case 'qualitygates':
                break;
            case 'qualityprofiles':
                break;
            case 'resources':
                break;
            case 'rules':
                break;
            case 'server':
                $api = new Api\Server($this);
                break;
            case 'sources':
                break;
            case 'system':
                break;
            case 'tests':
                break;
            case 'timemachine':
                break;
            case 'updatecenter':
                break;
            case 'user_properties':
                break;
            case 'users':
                $api = new Api\Users($this);
                break;
            case 'webservices':
                break;

            default:
                throw new \InvalidArgumentException('Invalid endpoint: "' . $name . '".');
        }

        if (!isset($api)) {
            throw new \InvalidArgumentException('Endpoint not yet supported: "' . $name . '".');
        }

        return $api;

    }
}
