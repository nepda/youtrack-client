<?php

namespace YouTrack;

use SimpleXMLElement;

/**
 * A class for connecting to a YouTrack instance.
 *
 * @author Jens Jahnke <jan0sch@gmx.net>
 * @author Nepomuk Fraedrich <info@nepda.eu>
 * Created at: 29.03.11 16:13
 *
 * @see https://www.jetbrains.com/help/youtrack/incloud/General-REST-API.html
 */
class Connection
{
    /**
     * @var null|resource cURL component
     */
    private $http = null;

    private $url = '';

    /**
     * @var string Base URL of the API (for example. https://youtrack.example.com)
     */
    private $base_url = '';

    /**
     * @var array Request headers
     */
    private $headers = [];

    /**
     * @var array Request Cookies
     */
    private $cookies = [];

    /**
     * Set to TRUE to enable verbose logging of curl messages.
     *
     * @var bool
     */
    private $debug_verbose = false;

    /**
     * @var string User agent
     */
    private $user_agent = 'youtrack-php-client/1.x'; // Use this as user agent string.

    /**
     * @var bool Enables/Disables SSL verification when sending requests using cURL
     */
    private $verify_ssl = true;

    /**
     * @var int Connection timeout
     */
    private $connectTimeout; // seconds

    private $timeout; // seconds

    private $bundle_paths = [
        'ownedField' => 'ownedFieldBundle',
        'enum' => 'bundle',
        'build' => 'buildBundle',
        'version' => 'versionBundle',
        /*
        'state' => 'stateBundle',
        'user' => 'userBundle'
        */
    ];

    /**
     * @var bool
     */
    protected $responseLogging = false;

    /**
     * @var string
     */
    protected $responseLoggingPath = './';

    /**
     * Connection constructor. Loads basic configuration and tries to login an user with provided credentials.
     *
     * If the `$password` is `null`, token based-authentication is used. The `$username` parameter is used as token.
     *
     * @param bool $verifySsl Flag to enable/disable SSL verification
     * @param int $connectTimeout Connection timeout in seconds
     * @param int $timeout seconds
     * @param string $url URL of the API
     * @param string $username Username to login with or the permanent token
     * @param string|null $password User's password. If null, the `$username` is used as permanent token
     * @see https://www.jetbrains.com/help/youtrack/incloud/Log-in-to-YouTrack.html
     */
    public function __construct($url, $username, $password, $connectTimeout = null, $timeout = null, $verifySsl = true)
    {
        $this->http = curl_init();
        $this->url = $url;
        $this->base_url = $url . '/rest';
        $this->headers[CURLOPT_HTTPHEADER] = [];
        $this->setConnectTimeout($connectTimeout);
        $this->setTimeout($timeout);
        $this->setVerifySsl($verifySsl);
        if (is_null($password)) {
            $this->tokenLogin($username);
        } else {
            $this->login($username, $password);
        }
    }

    /**
     * Checks if the HTTPS protocol is used
     *
     * @return bool
     */
    public function isHttps()
    {
        if (!empty($this->url)) {
            $url = strtolower($this->url);
            if (0 === strpos($url, 'https')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Loop through the given array and remove all entries
     * that have no value assigned.
     *
     * @param array &$params The array to inspect and clean up.
     */
    private function cleanUrlParameters(&$params)
    {
        if (!empty($params) && is_array($params)) {
            foreach ($params as $key => $value) {
                if (empty($value)) {
                    unset($params["$key"]);
                }
            }
        }
    }

    private function httpBuildQuery($params)
    {
        return http_build_query($params, null, ini_get('arg_separator.output'), PHP_QUERY_RFC3986);
    }

    private function encodeUrlPart($part)
    {
        return rawurlencode($part);
    }

    /**
     * @param string $token
     */
    protected function tokenLogin($token)
    {
        $this->headers[CURLOPT_HTTPHEADER] = [
            'Cache-Control: no-cache',
            sprintf('Authorization: Bearer %s', $token),
        ];
    }

    /**
     * Tries to log in with provided credentials (username, password). If login is successful, then cookies are saved
     * in $cookies attribute, if not, exception is thrown.
     *
     * @param string $password YouTrack password
     * @param string $username YouTrack username
     * @throws Exception
     * @see tokenLogin
     * @deprecated Use the tokenLogin method
     */
    protected function login($username, $password)
    {
        curl_setopt($this->http, CURLOPT_POST, true);

        // Workaround for login problems when running behind lighttpd proxy @see https://redmine.lighttpd.net/issues/1717
        curl_setopt($this->http, CURLOPT_HTTPHEADER, ['Content-Length: 1']);

        curl_setopt(
            $this->http,
            CURLOPT_URL,
            $this->base_url . '/user/login?login=' . $this->encodeUrlPart(
                $username
            ) . '&password=' . $this->encodeUrlPart(
                $password
            )
        );
        curl_setopt($this->http, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->http, CURLOPT_HEADER, true);
        curl_setopt($this->http, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
        if (!$this->verify_ssl) {
            curl_setopt($this->http, CURLOPT_SSL_VERIFYHOST, 0);
        }
        curl_setopt($this->http, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($this->http, CURLOPT_VERBOSE, $this->debug_verbose);
        curl_setopt($this->http, CURLOPT_POSTFIELDS, 'a');
        if (is_numeric($this->connectTimeout)) {
            curl_setopt($this->http, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        }
        if (is_numeric($this->timeout)) {
            curl_setopt($this->http, CURLOPT_TIMEOUT, $this->timeout);
        }
        $content = curl_exec($this->http);
        $response = curl_getinfo($this->http);

        $this->handleLoginResponse($content, $response);

        $this->headers[CURLOPT_HTTPHEADER] = ['Cache-Control: no-cache'];
        curl_close($this->http);
    }

    /**
     * Handles a login response from YouTrack. If login was successful, then cookies are saved in $cookies attribute,
     * otherwise Exception is thrown.
     *
     * @param string $content Response content returned from YouTrack server
     * @param array $response Response from function curl_getinfo
     * @throws Exception
     * @throws IncorrectLoginException
     */
    protected function handleLoginResponse($content, array $response)
    {
        if ((int)$response['http_code'] != 200) {
            if ((int)$response['http_code'] == 403) {
                throw new IncorrectLoginException('/user/login', $response, $content);
            }
            throw new Exception('/user/login', $response, $content);
        }
        $cookies = [];
        preg_match_all('/^Set-Cookie: (.*?)=(.*?)$/smi', $content, $cookies, PREG_SET_ORDER);
        foreach ($cookies as $cookie) {
            $parts = parse_url($cookie[0]);
            $this->cookies[] = $parts['path'];
        }
    }

    /**
     * Executes a request with the given parameters and returns the response.
     *
     * @param int $ignoreHttpReturnStatusCode Ignore the given http status code.
     * @param string $bodyContentType
     * @param string $method The http method (GET, PUT, POST).
     * @param string $url The request url.
     * @param string|array $body Data that should be send or the filename of the file if PUT is used. If this is an
     *                           array, it will be used as CURLOPT_POSTFIELDS
     * @param string[] $additionalHeaders Additional request headers to send
     * @return array An exception is thrown if an error occurs.
     * @throws \Exception An exception is thrown if an error occurs.
     * @throws Exception An exception is thrown if an error occurs.
     * @throws NotAuthorizedException An exception is thrown if an error occurs.
     * @throws NotFoundException An exception is thrown if an error occurs.
     */
    protected function request(
        $method,
        $url,
        $body = null,
        $ignoreHttpReturnStatusCode = 0,
        $bodyContentType = 'application/xml',
        $additionalHeaders = []
    ) {
        if (
            0 !== strpos($url, 'http://') &&
            0 !== strpos($url, 'https://')
        ) {
            $url = $this->base_url . $url;
        }
        $this->http = curl_init($url);
        $headers = $this->headers;

        $headers[CURLOPT_HTTPHEADER] = array_merge($headers[CURLOPT_HTTPHEADER], $additionalHeaders);

        curl_setopt($this->http, CURLOPT_FOLLOWLOCATION, true);
        
        switch ($method) {
            case 'GET':
                curl_setopt($this->http, CURLOPT_HTTPGET, true);
                break;
            case 'PUT':
                curl_setopt($this->http, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case 'POST':
                curl_setopt($this->http, CURLOPT_POST, true);
                break;
            case 'DELETE':
                curl_setopt($this->http, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default:
                throw new \Exception("Unknown HTTP method $method for YouTrack!");
        }

        $handleBody = false;
        if (in_array($method, ['PUT', 'POST'], true) && !empty($body)) {
            $handleBody = true;
        }

        if ($handleBody) {

            $filename = null;
            if (is_array($body) && isset($body['filename']) && isset($body['file'])) {
                $filename = $body['filename'];
                $body = $body['file'];
            }

            if (is_string($body)) {

                $isFile = false;
                if (file_exists($body)) {
                    $isFile = true;
                    if (
                        PHP_VERSION_ID >= 50500 &&
                        class_exists('\\CURLFile')
                    ) {
                        $file = new \CURLFile($body);
                        $mimeType = $this->getMimeTypeByFileExtension($body);
                        if (null !== $mimeType) {
                            $file->setMimeType($mimeType);
                        }
                        if (isset($filename)) {
                            $file->setPostFilename($filename);
                        }
                    } else {
                        $file = '@' . $body;
                        if (isset($filename)) {
                            $file .= '; filename=' . $filename;
                        }
                    }

                    $body = [
                        'file' => $file,
                    ];
                }

                if (!$isFile) {
                    $headers[CURLOPT_HTTPHEADER][] = 'Content-Type: ' . $bodyContentType . '; charset=UTF-8';
                    $headers[CURLOPT_HTTPHEADER][] = 'Content-Length: ' . strlen($body);
                }
                curl_setopt($this->http, CURLOPT_POSTFIELDS, $body);

            } elseif (is_array($body)) {

                if (false !== stripos($bodyContentType, 'json')) {
                    $body = json_encode($body);
                    $headers[CURLOPT_HTTPHEADER][] = 'Content-Type: ' . $bodyContentType . '; charset=UTF-8';
                    $headers[CURLOPT_HTTPHEADER][] = 'Content-Length: ' . strlen($body);
                }
                curl_setopt($this->http, CURLOPT_POSTFIELDS, $body);
            } else {
                curl_close($this->http);
                throw new \InvalidArgumentException(
                    'Cannot handle body of type ' . gettype($body) . '. Expected string or array.'
                );
            }
        }

        curl_setopt($this->http, CURLOPT_HTTPHEADER, $headers[CURLOPT_HTTPHEADER]);
        curl_setopt($this->http, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($this->http, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->http, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
        if (!$this->verify_ssl) {
            curl_setopt($this->http, CURLOPT_SSL_VERIFYHOST, 0);
        }
        curl_setopt($this->http, CURLOPT_VERBOSE, $this->debug_verbose);
        curl_setopt($this->http, CURLOPT_COOKIE, implode(';', $this->cookies));
        if (is_numeric($this->connectTimeout)) {
            curl_setopt($this->http, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        }
        if (is_numeric($this->timeout)) {
            curl_setopt($this->http, CURLOPT_TIMEOUT, $this->timeout);
        }
        $content = curl_exec($this->http);
        $response = curl_getinfo($this->http);
        curl_close($this->http);

        if (
            (int)$response['http_code'] !== 200 &&
            (int)$response['http_code'] !== 201 &&
            (int)$response['http_code'] !== $ignoreHttpReturnStatusCode
        ) {
            if ((int)$response['http_code'] === 403) {
                throw new NotAuthorizedException($url, $response, $content);
            }
            if ((int)$response['http_code'] === 404) {
                throw new NotFoundException($url, $response, $content);
            }
            throw new Exception($url, $response, $content);
        }

        if ($this->responseLogging && !empty($content)) {
            // for fetching results for test data
            file_put_contents($this->responseLoggingPath . '/' . md5($content) . '.xml', $content);
        }

        return [
            'content' => $content,
            'response' => $response,
        ];
    }

    /**
     * @param string $content
     *
     * @return SimpleXMLElement
     */
    private function parseXML($content)
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml instanceof SimpleXMLElement) {
            return $xml;
        }
        /** @var \LibXMLError[] $errors */
        $errors = libxml_get_errors();
        $message = 'Failed to parse YouTrack XML:';
        foreach ($errors as $error) {
            $message .= "\n{$error->code} {$error->message}";
        }
        $message .= "\n\nFull response was:\n" . $content;
        throw new \RuntimeException($message);
    }

    /**
     * Makes a request and parses the response as XML
     *
     * @param string $method
     * @param string $url
     * @param string|array $body If this is an array, it will be used as CURLOPT_POSTFIELDS
     * @param int $ignore_status
     * @return SimpleXMLElement|string
     * @throws Exception
     * @throws \Exception
     */
    protected function requestXml($method, $url, $body = null, $ignore_status = 0)
    {
        $r = $this->request(
            $method,
            $url,
            "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n" . $body,
            $ignore_status,
            'application/xml',
            ['Accept: application/xml']
        );
        $response = $r['response'];
        $content = $r['content'];
        if (
            !empty($response['content_type'])
            && (
                preg_match('/application\/xml/', $response['content_type'])
                || preg_match('/text\/xml/', $response['content_type'])
            )
        ) {
            try {
                $result = $this->parseXML($content);
            } catch (\RuntimeException $exc) {
                throw new \RuntimeException("Malformed data received from $method $url", 0, $exc);
            }
            return $result;
        }
        return $content;
    }

    /**
     * @param string $url
     * @return SimpleXMLElement
     */
    protected function get($url)
    {
        return $this->requestXml('GET', $url);
    }

    /**
     * @param string $url
     * @return SimpleXMLElement
     */
    protected function put($url)
    {
        return $this->requestXml('PUT', $url, '<empty/>\n\n');
    }

    /**
     * Gets all information about requested issue
     *
     * @link https://www.jetbrains.com/help/youtrack/incloud/Get-an-Issue.html
     * @param string $id YouTrack issue ID, e.g.: "SDBX-1234"
     * @param array $params key/values, e.g. 'wikifyDescription' => 'true'
     * @return Issue
     */
    public function getIssue($id, $params = [])
    {
        $paramString = '';
        if (!empty($params)) {
            $paramString = '?' . $this->httpBuildQuery($params);
        }
        $issue = $this->get('/issue/' . $this->encodeUrlPart($id) . $paramString);
        return new Issue($issue, $this);
    }

    /**
     * Creates an issue with properties from $params
     *
     * All $param values will be casted to string!
     *
     * If a $param value is longer than 100 chars it will be transferred via POSTFIELDS (not query string)
     *
     * may be this is an general $params value:
     * <code>
     *  $params = [
     * 'project' => $project,
     * 'assignee' => $assignee,
     * 'summary' => $summary,
     * 'description' => $description,
     * 'priority' => $priority,
     * 'type' => $type,
     * 'subsystem' => $subsystem,
     * 'state' => $state,
     * 'affectsVersion' => $affectsVersion,
     * 'fixedVersion' => $fixedVersion,
     * 'fixedInBuild' => $fixedInBuild,
     * ];
     * </code>
     *
     * @link https://www.jetbrains.com/help/youtrack/incloud/Create-New-Issue.html
     * @param string $project the obligatory project name
     * @param string $summary the obligatory issue summary
     * @param array $params optional additional parameters for the new issue (look into your personal YouTrack
     *     instance!)
     * @return Issue
     */
    public function createIssue($project, $summary, $params = [])
    {
        $params['project'] = (string)$project;
        $params['summary'] = (string)$summary;

        array_walk(
            $params,
            function (&$value) {
                // php manual: If funcname needs to be working with the actual values of the array,
                //  specify the first parameter of funcname as a reference. Then, any changes made to
                //  those elements will be made in the original array itself.
                $value = (string)$value;
            }
        );
        $r = $this->request('POST', '/issue', $params);
        $response = $r['response'];
        $content = $r['content'];
        if (!empty($response['content_type'])) {
            if (
                preg_match('/application\/xml/', $response['content_type'])
                || preg_match('/text\/xml/', $response['content_type'])
            ) {
                try {
                    $result = $this->parseXML($content);
                } catch (\RuntimeException $exc) {
                    throw new \RuntimeException('Malformed data received from POST /issue', 0, $exc);
                }
                $issue = $result;
            }
        }
        if (!isset($issue)) {
            $issue = $content;
        }

        return new Issue($issue, $this);
    }

    /**
     * Please note that this POST method allows updating issue summary and/or description, only. To update issue
     * fields, please use method to Apply Command to an Issue.
     *
     * @link https://www.jetbrains.com/help/youtrack/incloud/Update-an-Issue.html
     * @param string $id
     * @param string $summary
     * @param string $description
     * @return string API response content
     */
    public function updateIssue($id, $summary, $description)
    {
        $params = [];
        $params['summary'] = (string)$summary;
        $params['description'] = (string)$description;
        $r = $this->request(
            'POST',
            '/issue/' . $this->encodeUrlPart($id),
            $params
        );
        return $r['content'];
    }

    /**
     * @link https://www.jetbrains.com/help/youtrack/incloud/Update-an-Issue.html
     * @param string $id
     * @param string $summary
     * @return string API response content
     * @throws Exception
     * @throws \Exception
     */
    public function updateIssueSummary($id, $summary)
    {
        $r = $this->request(
            'POST',
            '/issue/' . $this->encodeUrlPart($id) . '?summary=' . $this->encodeUrlPart($summary)
        );
        return $r['content'];
    }

    /**
     * Deletes an issue with specified ID
     *
     * @param string $id YouTrack issue ID
     * @return mixed
     * @throws Exception
     * @throws \Exception
     */
    public function deleteIssue($id)
    {
        $r = $this->request('DELETE', '/issue/' . $this->encodeUrlPart($id), '');
        return $r['content'];
    }

    /**
     * Get a list of all accessible projects from the server.
     *
     * @param bool $verbose If full representation of projects is returned. If this parameter is false,
     *                      only short names and id's are returned.
     * @return Project[]
     * @see https://www.jetbrains.com/help/youtrack/incloud/Get-Accessible-Projects.html
     */
    public function getAccessibleProjects($verbose = false)
    {
        $verbose = ($verbose === true) ? 'true' : 'false';
        $xml = $this->get('/project/all?verbose=' . $verbose);
        $projects = [];

        foreach ($xml->children() as $node) {
            /** @var SimpleXMLElement $node */
            $project = new Project(new SimpleXMLElement($node->asXML()), $this);
            $projects[] = $project;
        }
        return $projects;
    }

    /**
     * Returns all comments related with provided issueId
     *
     * @param string $issueId
     * @return Comment[]
     * @throws Exception
     * @throws \Exception
     */
    public function getComments($issueId)
    {
        $comments = [];
        $xml = $this->requestXml('GET', '/issue/' . $this->encodeUrlPart($issueId) . '/comment');
        foreach ($xml->children() as $node) {
            $comments[] = new Comment($node, $this);
        }
        return $comments;
    }

    /**
     * @param string $issueId
     * @param string $text
     * @return bool
     * @see https://www.jetbrains.com/help/youtrack/incloud/Apply-Command-to-an-Issue.html
     */
    public function createComment($issueId, $text)
    {
        return $this->executeCommand($issueId, ' ', $text);
    }

    /**
     * @param string $commentId
     * @param string $issueId
     * @param string $updatedText
     * @return bool
     * @see https://www.jetbrains.com/help/youtrack/incloud/Update-a-Comment.html
     */
    public function updateComment($issueId, $commentId, $updatedText)
    {
        $url = sprintf(
            '/issue/%s/comment/%s',
            $this->encodeUrlPart($issueId),
            $this->encodeUrlPart($commentId)
        );
        $result = $this->request(
            'PUT',
            $url,
            ['text' => $updatedText],
            0,
            'application/json'
        );
        $response = $result['response'];
        if ($response['http_code'] !== 200) {
            return false;
        }
        return true;

    }

    /**
     * Returns all attachments for specified issue ID
     *
     * @param $id string Issue ID
     * @return Attachment[]
     */
    public function getAttachments($id)
    {
        $attachments = [];
        $xml = $this->requestXml('GET', '/issue/' . $this->encodeUrlPart($id) . '/attachment');
        foreach ($xml->children() as $node) {
            $attachments[] = new Attachment($node, $this);
        }
        return $attachments;
    }

    /**
     * Returns the file content from the given attachment url
     *
     * @param string $url The attachment url
     *
     * @return bool
     */
    public function getAttachmentContent($url)
    {
        $result = $this->request('GET', $url);

        if ($result['response']['http_code'] === 200) {
            return $result['content'];
        }
        return false;
    }

    /**
     * @param string $issueId The issue id
     * @param Attachment $attachment The attachment
     * @return array
     */
    public function createAttachmentFromAttachment($issueId, Attachment $attachment)
    {
        $params = $this->getAttachmentParams(
            $attachment->getName(),
            $attachment->getAuthorLogin(),
            $attachment->getCreated(),
            $attachment->getGroup()
        );

        return $this->request(
            'POST',
            '/issue/' . $this->encodeUrlPart($issueId) . '/attachment?' . $this->httpBuildQuery($params),
            $attachment->getUrl()
        );
    }

    /**
     * Creates an attachment for specified issue ID
     *
     * @param string $issueId
     * @param string $filename
     * @param string $name
     * @param string $authorLogin
     * @param \DateTime $created
     * @param string $group
     *
     * @return array
     * @throws \Exception
     * @deprecated Use `createAttachmentFromAttachment` instead
     */
    public function createAttachment(
        $issueId,
        $filename,
        $name = '',
        $authorLogin = '',
        \DateTime $created = null,
        $group = ''
    ) {
        if (!file_exists($filename)) {
            throw new \Exception("Can't open file $filename!");
        }

        $params = $this->getAttachmentParams($name, $authorLogin, $created, $group);

        return $this->request(
            'POST',
            '/issue/' . $this->encodeUrlPart($issueId) . '/attachment?' . $this->httpBuildQuery($params),
            $filename
        );
    }

    /**
     * Deletes an attachment
     *
     * @param Issue $issue
     * @param Attachment $attachment
     * @return bool
     * @throws Exception
     * @throws NotAuthorizedException
     * @throws NotFoundException
     * @throws \Exception
     */
    public function deleteAttachment(Issue $issue, Attachment $attachment)
    {
        $issueId = $issue->getId();
        $attachmentId = $attachment->getId();

        $result = $this->request(
            'DELETE',
            '/issue/' . $this->encodeUrlPart($issueId) . '/attachment/' . $this->encodeUrlPart($attachmentId)
        );

        $response = $result['response'];

        if ($response['http_code'] === 200) {
            return true;
        }
        return false;
    }

    /**
     * Returns attachment parameters
     *
     * @param string $name
     * @param string $authorLogin
     * @param \DateTime $created
     * @param string $group
     *
     * @return array
     */
    protected function getAttachmentParams($name, $authorLogin, $created, $group)
    {
        $params = [];

        if ($name) {
            $params['name'] = $name;
        }
        if ($authorLogin) {
            $params['authorLogin'] = $authorLogin;
        }
        if ($group) {
            $params['group'] = $group;
        }
        if ($created) {
            if ($created instanceof \DateTime) {
                $created = $created->getTimestamp() * 1000;
            }
            $params['created'] = $created;
        }

        return $params;
    }

    /**
     * @link https://www.jetbrains.com/help/youtrack/incloud/Import-Attachment-to-an-Issue.html
     *
     * @param string $issueId The issue id
     * @param string $authorLogin Attachment submitter login
     * @param string $filename
     * @param $file
     * @param string $created Attachment creation time
     * @return array
     * @throws Exception
     * @throws NotAuthorizedException
     * @throws NotFoundException
     * @throws \Exception
     * @deprecated Please use the new method `createAttachmentFromAttachment`
     */
    public function importAttachment($issueId, $authorLogin, $filename, $file, $created)
    {
        $params = [
            'name' => $filename,
            'authorLogin' => $authorLogin,
            'created' => $created,
        ];

        return $this->request(
            'POST',
            '/issue/' . $this->encodeUrlPart($issueId) . '/attachment?' . $this->httpBuildQuery($params),
            $file
        );
    }

    /**
     * @param string $issueId
     * @param bool $outward_only
     * @return Link[]
     * @throws \Exception
     */
    public function getLinks($issueId, $outward_only = false)
    {
        $links = [];
        $xml = $this->requestXml('GET', '/issue/' . $this->encodeUrlPart($issueId) . '/link');
        foreach ($xml->children() as $node) {
            /** @var SimpleXMLElement $node */
            if (($node->attributes()->source != $issueId) || !$outward_only) {
                $links[] = new Link($node, $this);
            }
        }
        return $links;
    }

    /**
     * Returns an user object
     *
     * @param string $login
     * @return User
     */
    public function getUser($login)
    {
        return new User($this->get('/admin/user/' . $this->encodeUrlPart($login)), $this);
    }

    /**
     * @param string $user
     */
    public function createUser($user)
    {
        $this->importUsers([$user]);
    }

    /**
     * @param string $login
     * @param string $full_name
     * @param string $email
     * @param string $jabber
     * @return SimpleXMLElement|void
     */
    public function createUserDetailed($login, $full_name, $email, $jabber)
    {
        return $this->importUsers([['login' => $login, 'fullName' => $full_name, 'email' => $email]]);
    }

    /**
     * @param array $users
     * @return SimpleXMLElement|void
     */
    public function importUsers($users)
    {
        if (count($users) <= 0) {
            return;
        }
        $xml = "<list>\n";
        foreach ($users as $user) {
            $xml .= '  <user';
            foreach ($user as $key => $value) {
                $xml .= " $key=\"" . htmlspecialchars($value) . '"';
            }
            $xml .= " />\n";
        }
        $xml .= '</list>';
        return $this->requestXml('PUT', '/import/users', $xml, 400);
    }

    public function importIssuesXml($project_id, $assignee_group, $xml)
    {
        throw new NotImplementedException('import_issues_xml(project_id, assignee_group, xml)');
    }

    /**
     * @link https://www.jetbrains.com/help/youtrack/incloud/Import-Links.html
     *
     * @param array[] $links
     * @return SimpleXMLElement|string|bool
     */
    public function importLinks(array $links)
    {
        if (count($links) <= 0) {
            return false;
        }
        $xml = "<list>\n";
        foreach ($links as $link) {
            $xml .= '  <link';
            foreach ($link as $key => $value) {
                $xml .= " $key=\"" . htmlspecialchars($value) . '"';
            }
            $xml .= " />\n";
        }
        $xml .= '</list>';
        return $this->requestXml('PUT', '/import/links', $xml, 400);
    }

    /**
     * @param string $project_id
     * @param array[] $issues
     * @return bool|SimpleXMLElement|string
     * @throws Exception
     */
    public function importIssues($project_id, $issues)
    {
        if (count($issues) <= 0) {
            return false;
        }
        $xml = "<issues>\n";
        foreach ($issues as $issue) {
            $xml .= '  <issue>';
            if (isset($issue['comments'])) {
                foreach ($issue['comments'] as $comment) {
                    $xml .= '    <comment';
                    foreach ($comment as $key => $value) {
                        $xml .= " $key=\"" . $value . '"';
                    }
                    $xml .= ' />';
                }
                unset($issue['comments']);
            }
            foreach ($issue as $key => $value) {
                $xml .= "<field name=\"$key\"><value>" . htmlspecialchars($value) . '</value></field>';
            }
            $xml .= "</issue>\n";
        }
        $xml .= '</issues>';
        return $this->requestXml('PUT', '/import/' . $this->encodeUrlPart($project_id) . '/issues', $xml, 400);
    }

    /**
     * Returns project for given project ID
     *
     * @param string $project_id
     * @return Project
     */
    public function getProject($project_id)
    {
        return new Project($this->get('/admin/project/' . $this->encodeUrlPart($project_id)), $this);
    }

    /**
     * @param string $project_id
     * @return Group[]
     */
    public function getProjectAssigneeGroups($project_id)
    {
        $xml = $this->get('/admin/project/' . $this->encodeUrlPart($project_id) . '/assignee/group');
        $groups = [];
        foreach ($xml->children() as $group) {
            /** @var SimpleXMLElement $group */
            $groups[] = new Group(new SimpleXMLElement($group->asXML()), $this);
        }
        return $groups;
    }

    /**
     * @param string $name
     * @return Group
     */
    public function getGroup($name)
    {
        return new Group($this->get('/admin/group/' . $this->encodeUrlPart($name)), $this);
    }

    /**
     * @param string $login
     * @return Group[]
     */
    public function getUserGroups($login)
    {
        $xml = $this->get('/admin/user/' . $this->encodeUrlPart($login) . '/group');
        $groups = [];
        foreach ($xml->children() as $group) {
            /** @var SimpleXMLElement $group */
            $groups[] = new Group(new SimpleXMLElement($group->asXML()), $this);
        }
        return $groups;
    }

    /**
     * @param string $login
     * @param string $group_name
     * @return mixed
     * @throws Exception
     * @throws \Exception
     */
    public function setUserGroup($login, $group_name)
    {
        $r = $this->request(
            'POST',
            '/admin/user/' . $this->encodeUrlPart($login) . '/group/' . $this->encodeUrlPart($group_name)
        );
        return $r['response'];
    }

    /**
     * @param Group $group
     * @return mixed
     */
    public function createGroup(Group $group)
    {
        $r = $this->put(
            '/admin/group/' . $this->encodeUrlPart($group->name) . '?description=noDescription&autoJoin=false'
        );
        return $r['response'];
    }

    /**
     * @param string $name
     * @return Role
     */
    public function getRole($name)
    {
        return new Role($this->get('/admin/role/' . $this->encodeUrlPart($name)), $this);
    }

    /**
     * @param string $username
     * @return Role[]
     */
    public function getUserRoles($username)
    {
        $xml = $this->get('/admin/user/' . $this->encodeUrlPart($username) . '/role');
        $roles = [];
        foreach ($xml->children() as $role) {
            /** @var SimpleXMLElement $role */
            $roles[] = new Role(new SimpleXMLElement($role->asXML()), $this);
        }
        return $roles;
    }

    /**
     * @param string $project_id
     * @param string $name
     * @return Subsystem
     */
    public function getSubsystem($project_id, $name)
    {
        return new Subsystem(
            $this->get(
                '/admin/project/' . $this->encodeUrlPart($project_id) . '/subsystem/' . $this->encodeUrlPart($name)
            ),
            $this
        );
    }

    /**
     * @param string $project_id
     * @return Subsystem[]
     */
    public function getSubsystems($project_id)
    {
        if (empty($project_id)) {
            throw new \InvalidArgumentException('You need to set an valid project id to get the subsystems');
        }
        $xml = $this->get('/admin/project/' . $this->encodeUrlPart($project_id) . '/subsystem');
        $subsystems = [];
        foreach ($xml->children() as $subsystem) {
            /** @var SimpleXMLElement $subsystem */
            $subsystems[] = new Subsystem(new SimpleXMLElement($subsystem->asXML()), $this);
        }
        return $subsystems;
    }

    /**
     * @param string $project_id
     * @return Version[]
     */
    public function getVersions($project_id)
    {
        $xml = $this->get('/admin/project/' . $this->encodeUrlPart($project_id) . '/version?showReleased=true');
        $versions = [];
        foreach ($xml->children() as $version) {
            /** @var SimpleXMLElement $version */
            $versions[] = new Version(new SimpleXMLElement($version->asXML()), $this);
        }
        return $versions;
    }

    /**
     * @param string $project_id
     * @param string $name
     * @return Version
     */
    public function getVersion($project_id, $name)
    {
        return new Version(
            $this->get(
                '/admin/project/' . $this->encodeUrlPart($project_id) . '/version/' . $this->encodeUrlPart($name)
            ),
            $this
        );
    }

    /**
     * @link https://www.jetbrains.com/help/youtrack/incloud/Get-All-Build-Bundles.html
     *
     * To get a specific build bundle: getBundle('build', $bundleName)
     *
     * @see getBundle
     * @return BuildBundle[]
     */
    public function getBuildBundles()
    {
        $xml = $this->get('/admin/customfield/buildBundle');
        $bundles = [];
        foreach ($xml->children() as $bundle) {
            /** @var SimpleXMLElement $bundle */
            $bundles[] = new BuildBundle(new SimpleXMLElement($bundle->asXML()), $this);
        }
        return $bundles;
    }

    /**
     * @link https://www.jetbrains.com/help/youtrack/incloud/GET-Builds.html
     *
     * @param string $project_id
     *
     * @return Build[]
     */
    public function getBuilds($project_id)
    {
        $xml = $this->get('/admin/project/' . $this->encodeUrlPart($project_id) . '/build');
        $builds = [];
        foreach ($xml->children() as $build) {
            /** @var SimpleXMLElement $build */
            $builds[] = new Build(new SimpleXMLElement($build->asXML()), $this);
        }
        return $builds;
    }

    /**
     * @link https://www.jetbrains.com/help/youtrack/incloud/GET-Users.html
     *
     * @param string $q Search query (part of user login, name or email)
     * @param string $group Filter by group (groupID)
     * @param string $role Filter by role
     * @param string $project Filter by project (projectID)
     * @param string $permission Filter by permission
     * @param bool $onlineOnly Get only users which are currently online
     * @param int $start Paginator mode (takes 10 records)
     *
     * @return User[]
     */
    public function getUsers(
        $q = '',
        $group = '',
        $role = '',
        $project = '',
        $permission = '',
        $onlineOnly = false,
        $start = 0
    ) {
        $users = [];
        $q = trim((string)$q);
        $params = [
            'q' => $q,
            'group' => $group,
            'role' => $role,
            'project' => $project,
            'permission' => $permission,
            'onlineOnly' => $onlineOnly,
            'start' => $start,
        ];
        $this->cleanUrlParameters($params);
        $xml = $this->get('/admin/user/?' . $this->httpBuildQuery($params));
        if (!empty($xml) && is_object($xml)) {
            foreach ($xml->children() as $user) {
                /** @var SimpleXMLElement $user */
                $users[] = new User(new SimpleXMLElement($user->asXML()), $this);
            }
        }
        return $users;
    }

    /**
     * @link https://www.jetbrains.com/help/youtrack/incloud/Add-New-Build-to-a-Bundle.html
     *
     * @param string $bundle_name Name of a bundle to add a new build.
     * @param string $build_name Name of a new build.
     * @param string $description Build's description.
     * @param int $color_index Sequential number of a color scheme (background color/text color pair) for the build.
     * @param \DateTime $assemble_date Assemble date for the new build. Default: current time.
     *
     * @return string API response, typically empty
     */
    public function createBuild(
        $bundle_name,
        $build_name,
        $description,
        $color_index = null,
        \DateTime $assemble_date = null
    ) {
        $params = [
            'description' => $description,
            'colorIndex' => $color_index === null ? 0 : $color_index,
            'assembleDate' => ($assemble_date === null ? time() : $assemble_date->getTimestamp()) * 1000,
        ];
        $bundle_name = $this->encodeUrlPart($bundle_name);
        $build_name = $this->encodeUrlPart($build_name);
        return $this->put(
            "/admin/customfield/buildBundle/{$bundle_name}/{$build_name}?" . $this->httpBuildQuery($params)
        );
    }

    public function createBuilds()
    {
        throw new NotImplementedException('create_builds()');
    }

    /**
     * @param Project $project
     * @return SimpleXMLElement
     */
    public function createProject(Project $project)
    {
        return $this->createProjectDetailed($project->id, $project->name, $project->description, $project->leader);
    }

    /**
     * @param string $project_id
     * @param string $project_name
     * @param string $project_description
     * @param string $project_lead_login
     * @param int $starting_number
     * @return SimpleXMLElement
     */
    public function createProjectDetailed(
        $project_id,
        $project_name,
        $project_description,
        $project_lead_login,
        $starting_number = 1
    ) {
        $params = [
            'projectName' => (string)$project_name,
            'description' => (string)$project_description,
            'projectLeadLogin' => (string)$project_lead_login,
            'lead' => (string)$project_lead_login,
            'startingNumber' => (string)$starting_number,
        ];
        return $this->put('/admin/project/' . $this->encodeUrlPart($project_id) . '?' . $this->httpBuildQuery($params));
    }

    /**
     * @param string $project_id
     * @param Subsystem[] $subsystems
     */
    public function createSubsystems($project_id, array $subsystems)
    {
        foreach ($subsystems as $subsystem) {
            $this->createSubsystem($project_id, $subsystem);
        }
    }

    /**
     * @param string $project_id
     * @param Subsystem $subsystem
     * @return string
     */
    public function createSubsystem($project_id, Subsystem $subsystem)
    {
        return $this->createSubsystemDetailed(
            $project_id,
            $subsystem->name,
            $subsystem->isDefault,
            $subsystem->defaultAssignee
        );
    }

    /**
     * @param string $project_id
     * @param string $name
     * @param string $is_default
     * @param string $default_assignee_login
     * @return string
     */
    public function createSubsystemDetailed($project_id, $name, $is_default, $default_assignee_login)
    {
        $params = [
            'isDefault' => (string)$is_default,
            'defaultAssignee' => (string)$default_assignee_login,
        ];
        $this->put(
            '/admin/project/' . $this->encodeUrlPart($project_id) . '/subsystem/' . $this->encodeUrlPart($name) .
            '?' . $this->httpBuildQuery($params)
        );
        return 'Created';
    }

    /**
     * @param string $project_id
     * @param string $name
     * @return SimpleXMLElement
     */
    public function deleteSubsystem($project_id, $name)
    {
        return $this->requestXml(
            'DELETE',
            '/admin/project/' . $this->encodeUrlPart($project_id) . '/subsystem/' . $this->encodeUrlPart($name)
        );
    }

    /**
     * @param string $project_id
     * @param Version[] $versions
     */
    public function createVersions($project_id, $versions)
    {
        foreach ($versions as $version) {
            $this->createVersion($project_id, $version);
        }
    }

    /**
     * @param string $project_id
     * @param Version $version
     * @return SimpleXMLElement
     */
    public function createVersion($project_id, Version $version)
    {
        return $this->createVersionDetailed(
            $project_id,
            $version->name,
            $version->isReleased,
            $version->isArchived,
            $version->releaseDate,
            $version->description
        );
    }

    /**
     * @param string $project_id
     * @param string $name
     * @param string $is_released
     * @param string $is_archived
     * @param string $release_date
     * @param string $description
     * @return SimpleXMLElement
     */
    public function createVersionDetailed(
        $project_id,
        $name,
        $is_released,
        $is_archived,
        $release_date = null,
        $description = ''
    ) {
        $params = [
            'description' => (string)$description,
            'isReleased' => (string)$is_released,
            'isArchived' => (string)$is_archived,
        ];
        if (!empty($release_date)) {
            $params['releaseDate'] = $release_date;
        }
        return $this->put(
            '/admin/project/' . $this->encodeUrlPart($project_id) . '/version/' . $this->encodeUrlPart(
                $name
            ) . '?' . $this->httpBuildQuery(
                $params
            )
        );
    }

    /**
     * Get Number of Issues for Several Queries
     *
     * For input like this
     * <code>
     * $queries = [
     *   '#Resolved',
     *   '#Fixed'
     * ];
     * </code>
     *
     * Returns something like
     * <code>
     * array (
     *   0 => 7286,
     *   '#Resolved' => 7286,
     *   1 => 5625,
     *   '#Fixed' => 5625,
     *   )
     * </code>
     *
     * @link https://www.jetbrains.com/help/youtrack/incloud/Get-Number-of-Issues-for-Several-Queries.html
     * @param array $queries List with queries as string
     * @param bool $rough Calculate approximate counts.
     * @param bool $sync Calculate counts synchronously. Setting this parameter true may influence YouTrack performance.
     * @return int[] Integer array of counts for each query
     */
    public function executeCountQueries(array $queries, $rough = false, $sync = true)
    {
        $body = '<queries>';
        foreach ($queries as $query) {
            $body .= '<query><![CDATA[' . $query . ']]></query>';
        }
        $body .= '</queries>';

        $rough = $rough ? 'true' : 'false';
        $sync = $sync ? 'true' : 'false';

        $xml = $this->requestXml(
            'POST',
            '/issue/counts?rough=' . $rough . '&sync=' . $sync,
            $body
        );
        if (isset($xml->count)) {
            $counts = (array)$xml->count;
            $result = [];
            array_walk(
                $counts,
                function (&$v, $k) use (&$result, &$queries) {
                    $v = (int)$v;
                    $result[$k] = $v;
                    $result[$queries[$k]] = $v;
                }
            );
            return $result;
        }
        return [];
    }

    /**
     * @link https://www.jetbrains.com/help/youtrack/incloud/Get-Issues-in-a-Project.html
     *
     * @param string $projectId
     * @param string $filter A query to search for issues.
     * @param int $after A number of issues to skip before getting a list of issues.
     * @param int $max Maximum number of issues to get.
     *
     * @return Issue[]
     */
    public function getIssues($projectId, $filter, $after = 0, $max = 10)
    {
        $params = [
            'filter' => (string)$filter,
            'after' => (string)$after,
            'max' => (string)$max,
        ];
        $this->cleanUrlParameters($params);
        $xml = $this->get(
            '/issue/byproject/' . $this->encodeUrlPart($projectId) . '?' . $this->httpBuildQuery($params)
        );
        $issues = [];

        if (!$xml instanceof SimpleXMLElement) {
            return $issues;
        }

        foreach ($xml->children() as $issue) {
            /** @var SimpleXMLElement $issue */
            $issues[] = new Issue(new SimpleXMLElement($issue->asXML()), $this);
        }
        return $issues;
    }

    /**
     * Get issues by filter only. Can be used to fetch issues without specifying project
     *
     * @link https://www.jetbrains.com/help/youtrack/incloud/Get-the-List-of-Issues.html
     * @param string $filter A query to search for issues. You can also specify several queries.
     *  Results for these search filters will be returned in subsequent blocks, a list of issues
     *  per each filter.
     * @param string $after A number of issues to skip before getting a list of issues. That is,
     *  when you specify, for example, after=12 in request, then in the response you will get all
     *  issues matching request but without first twelve issues found.
     * @param string $max Maximum number of issues to get. If not provided, only 10 issues will
     *  be returned by default.
     * @param array $with List of fields that should be included in the result.
     * @return Issue[]
     */
    public function getIssuesByFilter($filter, $after = null, $max = null, $with = null)
    {
        $params = [
            'filter' => (string)$filter,
        ];

        if (isset($after)) {
            $params['after'] = (string)$after;
        }
        if (isset($max)) {
            $params['max'] = (string)$max;
        }

        $this->cleanUrlParameters($params);

        $params_string = $this->httpBuildQuery($params);
        if (is_array($with)) {
            foreach ($with as $with_value) {
                $params_string .= '&with=' . $this->encodeUrlPart($with_value);
            }
        }

        $xml = $this->get('/issue?' . $params_string);
        $issues = [];
        foreach ($xml->children() as $issue) {
            /** @var SimpleXMLElement $issue */
            $issues[] = new Issue(new SimpleXMLElement($issue->asXML()), $this);
        }
        return $issues;
    }

    /**
     *  Apply Command to an Issue
     *
     * @link https://www.jetbrains.com/help/youtrack/incloud/Apply-Command-to-an-Issue.html
     * @param string $issue_id A command will be applied to an issue with this issueID.
     * @param string $command A command to apply
     * @param string|null $comment A comment to add to an issue.
     * @param string|null $group User group name. Use to specify visibility settings of a comment to be post.
     * @param bool $disableNotifications If set 'true' then no notifications about changes made with the specified
     *     command will be send. By default, is 'false'.
     * @param string|null $runAs Login for a user on whose behalf the command should be executed.
     * @return bool If YouTrack returns with HTTP 200 true, else false
     * @throws Exception
     * @throws \Exception
     */
    public function executeCommand(
        $issue_id,
        $command,
        $comment = null,
        $group = null,
        $disableNotifications = false,
        $runAs = null
    ) {
        $params = [
            'command' => (string)$command,
            'disableNotifications' => (boolean)$disableNotifications,
        ];
        if (!empty($comment)) {
            $params['comment'] = (string)$comment;
        }
        if (!empty($group)) {
            $params['group'] = (string)$group;
        }
        if (!empty($runAs)) {
            $params['runAs'] = (string)$runAs;
        }

        $result = $this->request('POST', '/issue/' . $this->encodeUrlPart($issue_id) . '/execute', $params);
        $response = $result['response'];
        if ($response['http_code'] !== 200) {
            return false;
        }
        return true;
    }

    /**
     * @param string $name
     * @return CustomFieldPrototype
     */
    public function getCustomField($name)
    {
        return new CustomFieldPrototype($this->get('/admin/customfield/field/' . $this->encodeUrlPart($name)), $this);
    }

    /**
     * @return CustomFieldPrototype[]
     */
    public function getCustomFields()
    {
        $xml = $this->get('/admin/customfield/field');
        $fields = [];
        foreach ($xml->children() as $field) {
            /** @var SimpleXMLElement $field */
            $fields[] = new CustomFieldPrototype(new SimpleXMLElement($field->asXML()), $this);
        }
        return $fields;
    }

    /**
     * @param CustomFieldPrototype[] $fields
     */
    public function createCustomFields($fields)
    {
        foreach ($fields as $field) {
            $this->createCustomField($field);
        }
    }

    /**
     * @param CustomField $field
     * @return string
     */
    public function createCustomField(CustomField $field)
    {
        return $this->createCustomFieldDetailed(
            $field->name,
            $field->type,
            $field->isPrivate,
            $field->visibleByDefault
        );
    }

    /**
     * @param string $name
     * @param string $type_name
     * @param string $is_private
     * @param string $default_visibility
     * @return string
     */
    public function createCustomFieldDetailed($name, $type_name, $is_private, $default_visibility)
    {
        $params = [
            'typeName' => (string)$type_name,
            'isPrivate' => (string)$is_private,
            'defaultVisibility' => (string)$default_visibility,
        ];
        $this->put('/admin/customfield/field/' . $this->encodeUrlPart($name) . '?' . $this->httpBuildQuery($params));
        return 'Created';
    }

    /**
     * @param string $field_type 'ownedField|enum|build'
     * @param string $bundle_name
     *
     * @return Bundle
     * @throws \Exception
     */
    public function getBundle($field_type, $bundle_name)
    {
        $field_type = $this->getFieldType($field_type);

        $className = 'YouTrack\\' . ucfirst($field_type) . 'Bundle';

        $bundlePath = null;
        if (isset($this->bundle_paths[$field_type])) {
            $bundlePath = $this->bundle_paths[$field_type];
        }

        if (!$bundlePath) {
            throw new \Exception('Unknown bundle field type');
        }

        return new $className(
            $this->get(
                sprintf(
                    '/admin/customfield/%s/%s',
                    $this->encodeUrlPart($bundlePath),
                    $this->encodeUrlPart($bundle_name)
                )
            ),
            $this
        );
    }

    /**
     * @param $fieldType
     *
     * @return string
     */
    public function getFieldType($fieldType)
    {
        if (false !== strpos($fieldType, '[')) {
            return substr($fieldType, 0, -3);
        }
        return $fieldType;
    }

    /**
     * @param string $name
     * @return EnumBundle
     */
    public function getEnumBundle($name)
    {
        return $this->getBundle('enum', $name);
    }

    /**
     * @param EnumBundle $bundle
     * @return SimpleXMLElement
     */
    public function createEnumBundle(EnumBundle $bundle)
    {
        return $this->requestXml('PUT', '/admin/customfield/bundle', $bundle->toXML(), 400);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws Exception
     * @throws \Exception
     */
    public function deleteEnumBundle($name)
    {
        $r = $this->request('DELETE', '/admin/customfield/bundle/' . $this->encodeUrlPart($name), '');
        return $r['content'];
    }

    /**
     * @param string $name
     * @param string $value
     * @return SimpleXMLElement
     */
    public function addValueToEnumBundle($name, $value)
    {
        return $this->put(
            '/admin/customfield/bundle/' . $this->encodeUrlPart($name) . '/' . $this->encodeUrlPart($value)
        );
    }

    /**
     * @param string $name bundle name
     * @param string $value bundle value to update
     * @param string $newValue new bundle value
     * @return array
     */
    public function updateValueInEnumBundle($name, $value, $newValue)
    {
        return $this->request(
            'POST',
            '/admin/customfield/bundle/' . $this->encodeUrlPart($name) . '/' . $this->encodeUrlPart($value) .
            '?newValue=' . $this->encodeUrlPart($newValue)
        );
    }

    /**
     * @param string $name
     * @param string[] $values
     * @return string
     */
    public function addValuesToEnumBundle($name, $values)
    {
        foreach ($values as $value) {
            $this->addValueToEnumBundle($name, $value);
        }
        return implode(', ', $values);
    }

    /**
     * @param string $project_id
     * @param string $name
     * @return CustomField
     */
    public function getProjectCustomField($project_id, $name)
    {
        $xml = $this->get(
            '/admin/project/' . $this->encodeUrlPart($project_id) . '/customfield/' . $this->encodeUrlPart($name)
        );

        return new CustomField(
            $xml,
            $this
        );
    }

    /**
     * @param string $project_id
     * @return CustomField[]
     */
    public function getProjectCustomFields($project_id)
    {
        $xml = $this->get('/admin/project/' . $this->encodeUrlPart($project_id) . '/customfield');
        $fields = [];
        foreach ($xml->children() as $cfield) {
            /** @var SimpleXMLElement $cfield */
            $fields[] = new CustomField(new SimpleXMLElement($cfield->asXML()), $this);
        }
        return $fields;
    }

    /**
     * @param string $project_id
     * @param CustomField $pcf
     * @return SimpleXMLElement
     */
    public function createProjectCustomField($project_id, CustomField $pcf)
    {
        return $this->createProjectCustomFieldDetailed($project_id, $pcf->name, $pcf->emptyText, $pcf->params);
    }

    /**
     * @param string $project_id
     * @param string $name
     * @param string $empty_field_text
     * @param array $params
     * @return SimpleXMLElement
     */
    private function createProjectCustomFieldDetailed($project_id, $name, $empty_field_text, $params = [])
    {
        $_params = [
            'emptyFieldText' => (string)$empty_field_text,
        ];
        if (!empty($params)) {
            $_params = array_merge($_params, $params);
        }
        return $this->put(
            '/admin/project/' . $this->encodeUrlPart($project_id) . '/customfield/' . $this->encodeUrlPart($name) .
            '?' . $this->httpBuildQuery($_params)
        );
    }

    /**
     * @return IssueLinkType[]
     */
    public function getIssueLinkTypes()
    {
        $xml = $this->get('/admin/issueLinkType');
        $lts = [];
        foreach ($xml->children() as $node) {
            /** @var SimpleXMLElement $node */
            $lts[] = new IssueLinkType(new SimpleXMLElement($node->asXML()), $this);
        }
        return $lts;
    }

    /**
     * @param IssueLinkType[] $lts
     */
    public function createIssueLinkTypes($lts)
    {
        foreach ($lts as $lt) {
            $this->createIssueLinkType($lt);
        }
    }

    /**
     * @param IssueLinkType $ilt
     * @return SimpleXMLElement
     */
    public function createIssueLinkType(IssueLinkType $ilt)
    {
        return $this->createIssueLinkTypeDetailed($ilt->name, $ilt->outwardName, $ilt->inwardName, $ilt->directed);
    }

    /**
     * @param string $name
     * @param string $outward_name
     * @param string $inward_name
     * @param string $directed
     * @return SimpleXMLElement
     */
    public function createIssueLinkTypeDetailed($name, $outward_name, $inward_name, $directed)
    {
        $params = [
            'outwardName' => (string)$outward_name,
            'inwardName' => (string)$inward_name,
            'directed' => (string)$directed,
        ];
        return $this->put('/admin/issueLinkType/' . $this->encodeUrlPart($name) . '?' . $this->httpBuildQuery($params));
    }

    /**
     * @return bool
     */
    public function getVerifySsl()
    {
        return $this->verify_ssl;
    }

    /**
     * Use this method to enable or disable the ssl_verifypeer option of curl.
     * This is usefull if you use self-signed ssl certificates.
     *
     * @param bool $verify_ssl
     * @return $this
     */
    public function setVerifySsl($verify_ssl)
    {
        $this->verify_ssl = $verify_ssl;
        return $this;
    }

    /**
     * @param int $timeout seconds
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = (int)$timeout;

        return $this;
    }

    /**
     * @param int $connectTimeout seconds
     * @return $this
     */
    public function setConnectTimeout($connectTimeout)
    {
        $this->connectTimeout = (int)$connectTimeout;

        return $this;
    }

    /**
     * get pairs (state,revolved attribute) in hash.
     * same info is get online on:
     * Project Fields › States (Click to change bundle name)
     *
     * @return null|array hash key: state string
     *              value: true is resolved attribute set to true
     */
    public function getGlobalIssueStates()
    {
        $xml = $this->get('/project/states');
        $states = null;
        foreach ($xml->children() as $node) {
            $states[(string)$node['name']] = ((string)$node['resolved'] === 'true');
        }
        return $states;
    }

    /**
     * Useful when you have configured different states for different projects
     * in this cases you will create bundles with name with global scope,
     * i.e. name can not be repeated on YouTrack installation.
     *
     * @param string $name
     * @return string hash key: state string value: hash('description' => string, 'isResolved' => boolean)
     */
    public function getStateBundle($name)
    {
        $cmd = '/admin/customfield/stateBundle/' . $this->encodeUrlPart($name);
        $xml = $this->get($cmd);
        $bundle = null;
        foreach ($xml->children() as $node) {
            $bundle[(string)$node] = [
                'description' => isset($node['description']) ? (string)$node['description'] : '',
                'isResolved' => (string)$node['isResolved'] === 'true',
            ];
        }
        return $bundle;
    }

    /**
     * @param string $filename
     *
     * @return null|string
     */
    private function getMimeTypeByFileExtension($filename)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $ext = strtolower($ext);

        $map = [
            'png' => 'image/png',
            'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
        ];

        if (isset($map[$ext])) {
            return $map[$ext];
        }

        return null;
    }

    /**
     * Get list of all available agile board configurations.
     *
     * @link https://www.jetbrains.com/help/youtrack/incloud/Get-List-of-Agile-Boards.html
     * @return AgileSetting[]
     */
    public function getAgileBoards()
    {
        $xml = $this->requestXml('GET', '/admin/agile');
        $boards = [];
        foreach ($xml->children() as $board) {
            /** @var SimpleXMLElement $board */
            $boards[] = new AgileSetting(new SimpleXMLElement($board->asXML()), $this);
        }
        return $boards;
    }

    /**
     * Get sprint by id
     *
     * @param string $boardId Identifier of the agile board, for which you want to get the sprint.
     * @param string $sprintId Identifier of the sprint you want to get.
     * @return Sprint
     * @see https://www.jetbrains.com/help/youtrack/incloud/Get-Sprint-by-ID.html
     */
    public function getSprintById($boardId, $sprintId)
    {
        $xml = $this->requestXml(
            'GET',
            '/admin/agile/' . $this->encodeUrlPart($boardId) . '/sprint/' . $this->encodeUrlPart($sprintId)
        );
        return new Sprint($xml, $this);
    }

    /**
     * Update existing agile configuration.
     *
     * @link https://www.jetbrains.com/help/youtrack/incloud/Update-Agile-Configuration.html
     * @param string $agileId Id of agile configuration that should be updated
     * @param string $xml
     * @return SimpleXMLElement
     */
    public function updateAgile($agileId, $xml)
    {
        return $this->requestXml('PUT', '/admin/agile/' . $this->encodeUrlPart($agileId), $xml);
    }

    /**
     * Gets time tracking status (enabled / disabled) for a project
     *
     * @link https://www.jetbrains.com/help/youtrack/incloud/GET-Time-Tracking-Settings-for-a-Project.html
     * @param string $project
     * @return boolean
     */
    public function isTimetrackingEnabled($project)
    {
        $xml = $this->requestXml('GET', '/admin/project/' . $this->encodeUrlPart($project) . '/timetracking');
        $enabled = $xml->attributes()->enabled;
        return (string)$enabled[0] === 'true';
    }

    /**
     * Import workitems for a given issue
     *
     * @link https://www.jetbrains.com/help/youtrack/incloud/Import-Workitems.html
     *
     * @param string $issueId
     * @param array $workItems
     * @return SimpleXMLElement|null
     */
    public function importWorkitems($issueId, $workItems)
    {
        if (count($workItems) <= 0) {
            return null;
        }
        $xml = "<workItems>\n";
        foreach ($workItems as $workItem) {
            $xml .= "  <workItem>\n";
            if (isset($workItem['author'])) {
                $xml .= '    <author login="' . htmlspecialchars($workItem['author']) . "\"></author>\n";
                unset($workItem['author']);
            }
            foreach ($workItem as $key => $value) {
                $xml .= "    <$key>" . htmlspecialchars($value) . "</$key>\n";
            }
            $xml .= "  </workItem>\n";
        }
        $xml .= '</workItems>';
        return $this->requestXml('PUT', '/import/issue/' . $this->encodeUrlPart($issueId) . '/workitems', $xml, 400);
    }

    /**
     * Get all workitems for a given issue
     *
     * @link https://www.jetbrains.com/help/youtrack/incloud/Get-Available-Work-Items-of-Issue.html
     *
     * @param string $issueId
     *
     * @return Workitem[]
     */
    public function getWorkitems($issueId)
    {
        $items = [];
        $xml = $this->requestXml('GET', '/issue/' . $this->encodeUrlPart($issueId) . '/timetracking/workitem/');
        foreach ($xml->children() as $node) {
            $items[] = new Workitem($node, $this);
        }
        return $items;
    }

    /**
     * Delete a given workitem
     *
     * @link https://www.jetbrains.com/help/youtrack/standalone/Delete-Existing-Work-Item.html
     *
     * @param string $issueId
     * @param string $workitemId Unique ID of the existing workitem to delete.
     * @return bool
     *
     * @throws Exception
     * @throws NotAuthorizedException
     * @throws NotFoundException
     */
    public function deleteWorkitem($issueId, $workitemId)
    {
        $result = $this->request(
            'DELETE',
            '/issue/' . $this->encodeUrlPart($issueId) . '/timetracking/workitem/' . $this->encodeUrlPart($workitemId)
        );
        return $result['response']['http_code'] === 200;
    }

    /**
     * Get issue history by issue id
     *
     * @link https://www.jetbrains.com/help/youtrack/incloud/Get-Issue-History.html
     *
     * @param string $issueId
     *
     * @return array
     */
    public function getIssueHistory($issueId)
    {
        $items = [];
        $xml = $this->requestXml('GET', '/issue/' . $this->encodeUrlPart($issueId) . '/history');
        foreach ($xml->children() as $node) {
            $item = [];
            foreach ($node as $fieldNode) {
                if ((string)$fieldNode['name'] === '') {
                    continue;
                }
                $item[(string)$fieldNode['name']] = (string)$fieldNode->value;
            }
            $items[$item['updated'] === 'null' ? 0 : $item['updated']] = $item;
        }
        ksort($items);
        return $items;
    }

    public function getCurrentUser()
    {
        return new CurrentUser($this->get('/user/current'), $this);
    }
}
