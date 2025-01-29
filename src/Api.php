<?php

namespace Val;

use Val\App;
use Val\App\{JSON, CSRF, Auth};

Class Api
{
    // The only method allowed for the requested API action method is GET.
    private bool $flagOnlyGET = false;

    // The only method allowed for the requested API action method is POST.
    private bool $flagOnlyPOST = false;

    // Only an Authenticated user can request the API action.
    private bool $flagOnlyAuthenticated = false;

    // Only an Unauthenticated user can request the API action.
    private bool $flagOnlyUnauthenticated = false;

    // The specific status code and optional description which describe the 
    // reasons why data validation failed.
    private array $fail = [];

    // List of missing but required fields.
    private array $missing = [];

    // List of fields with an invalid value.
    private array $wrong = [];

    // Request data fields and their values.
    private array $fields = [];

    /**
     * The API constructor.
     */
    final private function __construct(

        // Whether the API is called internally (programmatically).
        private ?bool $isInternalCall = null,
        
        // Parameters for the internal call.
        private ?array $internalCallParams = null

    ) {}

    /**
     * The default API action that is called when no action is specified in the 
     * API call.
     */
    public function __invoke() {}

    /**
     * Loads the requested API.
     */
    final public static function _load()
    {
        // Validate the syntax.
        $regex = '/^[a-zA-Z]{1,50}$/';
        $apiName = $_GET['_api'];

        if (!preg_match($regex, $apiName))
            return (new Api)->respondError(404);

        // Load the API Class.
        $className = ucfirst($apiName);
        $path = App::$DIR_API . "/{$className}.php";

        if (!is_file($path))
            return (new Api)->respondError(404);

        $className = self::getNamespace($path) . $className;
        require_once $path;

        if (!class_exists($className))
            return (new Api)->respondError(404);

        // Call the action.
        $action = $_GET['_action'];

        if (!$action) {

            $api = new $className;
            $api(); // The magic method "__invoke()" is the default action.
            $api->respondOnFail();
            return;
        }

        // Check the action syntax.
        if (!preg_match($regex, $action))
            return (new Api)->respondError(404);

        $api = new $className;

        // Check whether it is a public method.
        if (!App::_isCallable([$api, $action]))
            return (new Api)->respondError(404);

        $api->$action();
        $api->respondOnFail();
    }

    /**
     * Calls the specified API as an internal request (programmatically).
     * 
     * @throws \RuntimeException - error response from the API endpoint.
     * @throws \LogicException
     */
    final public static function peek(string $endpoint, array $params = []) : mixed
    {
        $endpoint = trim($endpoint, '/');

        if (!$endpoint)
            throw new \LogicException('Invalid API endpoint.');

        $parts = explode('/', $endpoint);

        if (count($parts) > 2)
            throw new \LogicException('The API endpoint should consist of 
                no more than 2 parts.');

        // Load the API Class.
        $className = ucfirst($parts[0]);
        $path = App::$DIR_API . "/{$className}.php";

        if (!is_file($path))
            throw new \LogicException('The file of the API Class was not 
                found.');

        $className = self::getNamespace($path) . $className;
        require_once $path;

        if (!class_exists($className))
            throw new \LogicException('The API Class is not defined.');
        
        // Call the action.
        $action = $parts[1] ?? null;

        $api = new $className(true, $params);

        if (!$action)
            return $api();

        if (App::_isCallable([$api, $action]))
            return $api->$action();

        return null;
    }

    /**
     * Returns the namespace of the API Class file.
     */
    private static function getNamespace($file) : string
    {
        $contents = file_get_contents($file);
        if (!$contents) return '\\';
        preg_match('/namespace\s+([a-z0-9_\\\\]+)|class/i', $contents, $matches);
        return ($matches[1] ?? '') . '\\';
    }

    /**
     * Allows only GET requests to call the API action method. Responds with an
     * HTTP status code of "405 Method Not Allowed" if a different request
     * method is used.
     * 
     * @throws \LogicException
     */
    final protected function onlyGET() : self
    {
        if ($this->isInternalCall)
            return $this;

        if ($this->flagOnlyPOST)
            throw new \LogicException('It is not possible to allow only the
                GET method, only the POST method is already allowed.');

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {

            $this->flagOnlyGET = true;
            return $this;
        }

        $this->respondError(405);
    }

    /**
     * Allows only POST requests to call the API action method. Responds with
     * an HTTP status code of "403 Forbidden" if the CSRF Token is invalid.
     * Responds with an HTTP status code of "405 Method Not Allowed" if a
     * different request method is used.
     * 
     * @throws \LogicException
     */
    final protected function onlyPOST() : self
    {
        if ($this->isInternalCall)
            return $this;

        if ($this->flagOnlyGET)
            throw new \LogicException('It is not possible to allow only the 
                POST method, only the GET method is already allowed.');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if(CSRF::tokensMatch()) {

                $this->flagOnlyPOST = true;
                return $this;
            }

            $this->respondError(403);
        }

        $this->respondError(405);
    }
    
    /**
     * Allows only Authenticated user to call the API action method. Responds 
     * with an HTTP status code of "401 Unauthorized" if user is not
     * Authenticated.
     * 
     * @throws \LogicException
     */
    final protected function onlyAuthenticated() : self
    {
        if ($this->flagOnlyUnauthenticated)
            throw new \LogicException('It is not possible to allow only
                Authenticated user, because the option "allow only 
                Unauthenticated user" was set.');

        if (Auth::getAccountId()) {

            $this->flagOnlyAuthenticated = true;
            return $this;
        }

        if ($this->isInternalCall)
            throw new \LogicException('Peeking an API endpoint that allows 
            "only Authenticated user", but the user is not authenticated.');

        $this->respondError(401);
    }

    /**
     * Allows only Unauthenticated user to call the API action method. Responds
     * with an HTTP status code of "403 Forbidden" if user is not
     * Unauthenticated.
     * 
     * @throws \LogicException
     */
    final protected function onlyUnauthenticated() : self
    {
        if ($this->flagOnlyAuthenticated)
            throw new \LogicException('It is not possible to allow only
                Unauthenticated user, because the option "allow only 
                Authenticated user" was set.');

        if (!Auth::getAccountId()) {

            $this->flagOnlyUnauthenticated = true;
            return $this;
        }

        if ($this->isInternalCall)
            throw new \LogicException('Peeking an API endpoint that allows 
            "only Unauthenticated user", but the user is authenticated.');
        
        $this->respondError(403);
    }

    /**
     * Registers the required fields for the requested API action method. Also, 
     * calls the corresponding validation method (if defined) for each field. 
     * Sends a fail response if any of the listed fields are missing or did not
     * pass the corresponding validation (if defined).
     * 
     * Validation method definition syntaxes and examples:
     * 
     *  a) Without a parameter.
     *  Inside the validation method use the $this->val('field') construction, 
     *  to get the value of the field.
     * 
     *      protected function validate<Fieldname>()
     *      {
     *          $value = $this->val('<fieldname>');
     *          ...
     *      }
     * 
     *  b) With a parameter that represents the value of the field being
     *  validated.
     * 
     *      protected function validate<Fieldname>($value)
     *      {
     *          $valueInt = intval($value);
     *          ...
     *      }
     * 
     *  c) With a parameter that represents a reference to the value of the
     *  field being validated. That is, the field value can be changed or 
     *  modified, before it is used in the API action method.
     * 
     *      protected function validate<Fieldname>(&$value)
     *      {
     *          $value = trim($value);
     *          ...
     *      }
     * 
     * (!) <Fieldname> - the name of the field being validated. When written 
     *     in the method name, the first letter must be capitalized (just after
     *     "validate").
     */
    final protected function required(string|array ...$fields) : self
    {
        return $this->registerFields($fields, true);
    }

    /**
     * Registers the optional fields for the requested API action method. Also, 
     * calls the corresponding validation method (if defined) for each field. 
     * Sends a fail response if any of the listed fields did not pass the 
     * corresponding validation (if defined).
     * 
     * For details on how to write the validation methods, read the description 
     * of the "required()" method.
     */
    final protected function optional(string|array ...$fields) : self
    {
        return $this->registerFields($fields);
    }

    /**
     * Registers the required or optional fields for the requested API methed.
     * 
     * For details, read the descriptions of the "required()" and "optional()"
     * methods.
     */
    private function registerFields(array $fields, bool $required = false) : self
    {
        // Register fields.
        foreach ($fields as $field) {

            // Grouped fields, that use the same validation method.
            if (is_array($field)) {

                foreach ($field as $_field) {

                    $this->registerField($_field, $required);
                }

                continue;
            }

            $this->registerField($field, $required);
        }

        // Send a response if any of the required fields are missing.
        if ($this->missing) $this->respondOnFail();

        // Call the corresponding validation method (if defined) for each
        // registered field.
        foreach ($fields as $field) {

            // Grouped fileds use the validation method of the first field in
            // the group.
            if (is_array($field)) {
                
                $firstField = $field[0];

                foreach ($field as $_field) {

                    $this->validateField($_field, $firstField);
                }

                continue;
            }

            $this->validateField($field);
        }

        return $this->respondOnFail();
    }

    /**
     * Registers the specified field. If the field is required but is not set,
     * then it is added to the missing fields list.
     * 
     * @throws \LogicException
     */
    private function registerField(string $field, bool $required) : void
    {
        if ($this->isInternalCall) {

            if (isset($this->internalCallParams[$field])) {

                $this->fields[$field] = $this->internalCallParams[$field];
                return;
            }

            if (!$required) {

                $this->fields[$field] = null;
                return;
            }

            throw new \LogicException("Missing the required field \"$field\".");
        }

        if ($this->flagOnlyGET && isset($_GET[$field])) {

            $this->fields[$field] = $_GET[$field];
            return;
        }

        if ($this->flagOnlyPOST && isset($_POST[$field])) {

            $this->fields[$field] = $_POST[$field];
            return;
        }

        if (isset($_REQUEST[$field])) { 

            $this->fields[$field] = $_REQUEST[$field]; // priority: COOKIE, POST, GET
            return;
        }

        if ($required) {

            $this->missing[] = $field;
            return;
        }

        $this->fields[$field] = null;
    }

    /**
     * Calls the corresponding validation method (if defined) for the specified
     * field.
     * 
     * @throws \LogicException
     */
    private function validateField(string $field, ?string $firstField = null) : void
    {
        $value =& $this->fields[$field];

        if ($value === null)
            return;

        $method = 'validate' . ucfirst($firstField ?? $field);

        if (method_exists($this, $method)) {

            $this->$method($value);
            return;
        }

        if ($firstField) {

            throw new \LogicException('The validation method must be defined 
                when grouping fields, using the name of the first field in the
                group.');
        }
    }

    /**
     * Returns the value of the specified field.
     * 
     * @throws \InvalidArgumentException
     */
    final protected function val(string $field)
    {
        if (!array_key_exists($field, $this->fields))
            throw new \InvalidArgumentException('The "' . $field . '" field is 
                not registered, make sure it is specified as a required or 
                optional field.');

        return $this->fields[$field];
    }

    /**
     * Responds to a successfult request. Sets the HTTP status code to "200 OK"
     * and exits the application.
     */
    final protected function respondSuccess() : ?bool
    {
        if ($this->isInternalCall)
            return true;

        http_response_code(200);
        App::exit();
    }

    /**
     * Responds with data to a successful request. Sets the HTTP status code to
     * "200 OK", sends the data and exits the application.
     * 
     * Follow the recommended response formats (which can be combined if
     * necessary):
     * 
     *  a) E.g. a single post:
     *      ["post" => ["id" => 1, "name" => "Qwerty"]]
     * 
     *  b) E.g. multiple posts:
     *      ["posts" => [
     *          ["id" => 1, "name" => "Qw"],
     *          ["id" => 2, "name" => "Er"], ...
     *      ]]
     * 
     */
    final protected function respondData(array $data) : ?array
    {
        if ($this->isInternalCall)
            return $data;

        http_response_code(200);
        echo JSON::encode($data);
        App::exit();
    }

    /**
     * Used for unsuccessful request processing or invalid call conditions. 
     * Sets the HTTP status code to "500 Internal Server Error" (by default) or
     * "4xx Client Error" (except 400) and exits the application.
     * 
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    final protected function respondError(int $code = 500) : never
    {
        if ($code < 401 || $code > 500)
            throw new \InvalidArgumentException('The "int $code" parameter 
                value must be between 401 and 500 inclusive.');

        if ($this->isInternalCall) {

            throw new \RuntimeException("An error occured with the status code 
                \"$code\".");
        }

        http_response_code($code);
        App::exit();
    }

    /**
     * Sends a response only in case there is a problem with the data provided,
     * or if any precondition of the call has not been met. Sets the HTTP
     * status code to "400 Bad Request", sends the response and exits the
     * application.
     * 
     * The response format can be one of the following or a combination of
     * them:
     * 
     *  a) From setFail() method:
     *      ['fail' => [
     *          'status' => "EXISTING_USERNAME",
     *          'description' => "The username already exists."
     *     ]]
     * 
     *  b) From setWrong() method:
     *      ['wrong' => [
     *          "price" => [
     *              'status' => "custom_status_code",
     *              'params' => ["minValue" => 1]
     *         ],
     *     ]]
     * 
     *  c) A list of missing but required fields, specified in the required()
     *  method:
     *      ['missing' => ["title", "url", "notes"]]
     * 
     * @throws \LogicException
     */
    final protected function respondOnFail() : self
    {
        $response = [];

        // Set the fail status of the request.
        if ($this->fail) {
            
            $response['fail'] = $this->fail;
        }

        // List fields that didn't pass validation.
        if ($this->wrong) {

            $response['wrong'] = $this->wrong;
        }

        // List missing fields
        if ($this->missing) {

            // Hide missing fields in the production.
            if (App::isProd()) {

                if (!$response['fail']) {

                    $response['fail'] = ['status' => 'Invalid request.'];
                }

            } else {

                $response['missing'] = $this->missing;
            }
        }

        if (!$response)
            return $this;

        if ($this->isInternalCall) {
            
            throw new \LogicException('Failed API peek: '
                . JSON::encode($response));
        }

        http_response_code(400);
        echo JSON::encode($response);
        App::exit();
    }

    /**
     * Sets a custom status string and an optional description which describe
     * the reasons why data validation failed for the current request.
     * 
     * Example:
     * 
     *      $this->setFail("EXISTING_USERNAME", "Username already exists.");
     * 
     */
    final protected function setFail(string $status, ?string $description = null) : self
    {
        $this->fail = ['status' => $status];
        if ($description) $this->fail['description'] = $description;

        return $this;
    }

    /**
     * Sets a custom status string and a list of mandatory parameters, for the
     * specified field, whose value is not valid.
     * 
     * Example:
     * 
     *      $this->setWrong("price", "INVALID_PRICE", [
     *          "minValue" => 1,
     *          "maxValue" => 10000
     *      ]);
     * 
     */
    final protected function setWrong(string $field, string $status, ?array $params = null) : self
    {
        $this->wrong[$field] = ['status' => $status];
        if ($params) $this->wrong[$field]['params'] = $params;

        return $this;
    }

}
