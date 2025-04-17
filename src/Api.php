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

    // Registered data fields and their values.
    private array $fields = [];

    // List of missing but required fields.
    private array $missing = [];

    // List of fields with invalid values.
    private array $invalid = [];

    /**
     * The API constructor.
     */
    final public function __construct(

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
     * 
     * @internal
     */
    final public static function _load() : never
    {
        $apiName = $_GET['_api'];
        $action = $_GET['_action'] ?? null;

        // Validate syntax.
        $regex = '/^[a-zA-Z]{1,50}$/';

        if (!preg_match($regex, $apiName) || ($action && !preg_match($regex, $action)))
            self::respond(404);

        // Load the API Class.
        $className = self::loadApiClass($apiName);

        if (!$className)
            self::respond(404);

        // Call the action.
        $api = new $className;

        if (!$action) $api(); // The magic method "__invoke()" is the default action.
        else if (App::_isCallable([$api, $action])) $api->$action();

        $api->respondOnFail(); // when returned with $this->setInvalid()
        self::respond(404); // when empty action method or simply returned
    }

    /**
     * Calls the specified API as an internal request (programmatically).
     * 
     * @throws \RuntimeException - error response from the API endpoint.
     * @throws \LogicException
     */
    final public static function peek(string $endpoint, array $params = []) : mixed
    {
        $parts = explode('/', trim($endpoint, '/'), 2);

        if (!$parts[0])
            throw new \LogicException('Invalid API endpoint.');

        // Load the API Class.
        $className = self::loadApiClass($parts[0]);

        if ($className === null)
            throw new \LogicException('The API Class was not found.');

        // Call the action.
        $api = new $className(true, $params);
        $action = $parts[1] ?? null;

        return !$action
            ? $api()
            : (App::_isCallable([$api, $action]) ? $api->$action() : null);
    }

    /**
     * Loads the requested API Class and returns its fully qualified name, or
     * null if the class file or class definition is missing.
     * 
     * @internal
     */
    private static function loadApiClass(string $apiName) : ?string
    {
        $className = ucfirst($apiName);
        $path = App::$DIR_API . "/{$className}.php";

        if (!is_file($path))
            return null;

        require_once $path;
        $className = self::getNamespace($path) . $className;

        return class_exists($className) ? $className : null;
    }

    /**
     * Returns the namespace of the API Class file.
     * 
     * @internal
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

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

            return $this->error(405); // Method Not Allowed
        }

        $this->flagOnlyGET = true;
        return $this;
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

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

            return $this->error(405); // Method Not Allowed
        }

        if (!CSRF::tokensMatch()) {

            return $this->error(403); // Forbidden
        }

        $this->flagOnlyPOST = true;
        return $this;
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

        if (!Auth::getAccountId()) {

            if ($this->isInternalCall)
                throw new \LogicException('Peeking an API endpoint that allows 
                "only Authenticated user", but the user is not authenticated.');

            return $this->error(401);
        }

        $this->flagOnlyAuthenticated = true;
        return $this;
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

        if (Auth::getAccountId()) {

            if ($this->isInternalCall)
                throw new \LogicException('Peeking an API endpoint that allows 
                "only Unauthenticated user", but the user is authenticated.');
            
            return $this->error(403);
        }

        $this->flagOnlyUnauthenticated = true;
        return $this;
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
     * 
     * @throws \LogicException - for ::peek() calls, in case of a missing field.
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
     * @throws \LogicException - for ::peek() calls, in case of a missing field.
     * @see self::required() For details on how to write the validation methods.
     */
    final protected function optional(string|array ...$fields) : self
    {
        return $this->registerFields($fields);
    }

    /**
     * Registers the required or optional fields for the requested API methed.
     * 
     * @internal
     * @throws \LogicException - for ::peek() calls, in case of a failed request.
     * @see self::required() | self::optional() For details.
     */
    private function registerFields(array $fields, bool $required = false) : self
    {
        // Register fields.
        foreach ($fields as $field) {

            if (!is_array($field)) {
                $this->registerField($field, $required);
                continue;
            }

            // Grouped fields (that use the same validation method).
            foreach ($field as $_field) $this->registerField($_field, $required);
        }

        // Send a response if any of the required fields are missing.
        if ($this->missing) {
            
            $this->respondOnFail();
        }

        // For each registered field, call its corresponding validation method 
        // (if defined).
        foreach ($fields as $field) {

            if (!is_array($field)) {
                $this->validateField($field);
                continue;
            }

            // Grouped fileds use the validation method of the first field.
            foreach ($field as $_field) $this->validateField($_field, $field[0]);
        }

        return $this->respondOnFail();
    }

    /**
     * Registers the specified field. If the field is required but is not set,
     * then it is added to the missing fields list.
     * 
     * @internal
     * @throws \LogicException
     */
    private function registerField(string $field, bool $required) : void
    {
        if ($this->isInternalCall) {

            $value = $this->internalCallParams[$field] ?? null;

            if ($value !== null || !$required) {
                $this->fields[$field] = $value;
                return;
            }

            throw new \LogicException("Missing required field \"$field\".");
        }

        $value = $this->getFieldValue($field);

        if ($value !== null) {
            $this->fields[$field] = $value;
            return;
        }

        if ($required) {
            $this->missing[] = $field;
            return;
        }

        $this->fields[$field] = null; // register the optional field
    }

    /**
     * Returns the value of the specified field.
     * 
     * @internal
     */
    private function getFieldValue(string $field) : string|array|null
    {
        return match(true) {
            $this->flagOnlyGET => $_GET[$field] ?? null,
            $this->flagOnlyPOST => $_POST[$field] ?? null,
            // $_REQUEST default priority: COOKIE, POST, GET
            default => $_REQUEST[$field] ?? $_FILES[$field] ?? null
        };
    }

    /**
     * Validates the specified field using the its validation method 
     * (if defined).
     * 
     * @internal
     * @throws \LogicException
     */
    private function validateField(string $field, ?string $firstField = null) : void
    {
        $value =& $this->fields[$field];

        if ($value === null)
            return;

        $method = 'validate' . ucfirst($firstField ?? $field);

        if (method_exists($this, $method)) {
            $result = $this->$method($value);
            if (isset($result)) $this->setInvalid($field, ...(array)$result);
            return;
        }

        if ($firstField)
            throw new \LogicException('The validation method must be defined 
                when grouping fields, using the name of the first field in the
                group.');
    }

    /**
     * Returns the value of the specified field.
     * 
     * @throws \InvalidArgumentException
     */
    final protected function val(string $field) : mixed
    {
        if (!array_key_exists($field, $this->fields))
            throw new \InvalidArgumentException('The "' . $field . '" field is 
                not registered, make sure it is specified as a required or 
                optional field.');

        return $this->fields[$field];
    }

    /**
     * Marks the specified field as having an invalid value. Adds it to the 
     * fail response, with a custom status and a list of parameters to comply.
     * 
     * Example:
     * 
     *      $this->setInvalid("price", "INVALID_RANGE", [
     *          "minValue" => 1,
     *          "maxValue" => 10000
     *      ]);
     * 
     */
    final protected function setInvalid(string $field, string $status, ?array $params = null) : self
    {
        $this->invalid[$field] = ['status' => $status];
        if ($params) $this->invalid[$field]['params'] = $params;

        return $this;
    }

    /**
     * Sends the response with the specified status code and optional data, 
     * then exits the application.
     * 
     * @internal
     */
    private static function respond(int $code, ?array $data = null) : never
    {
        http_response_code($code);
        if ($data) echo JSON::encode($data);
        App::exit();
    }

    /**
     * Sends a successful response, with/without data. Sets the HTTP status 
     * code to "200 OK", sends the JSON response (if any data) and exits the 
     * application.
     * 
     * For ::peek() calls, returns the $data or `true` if no data.
     * 
     * Example response formats based on supplied $data:
     * 
     *  a) Single post:
     * 
     *      $data = ["id" => 1, "name" => "Qwerty"];
     * 
     *      Resulting response (JSON):
     *      {"id": 1, "name": "Qwerty"}
     * 
     *  b) Multiple posts:
     * 
     *      $data = [
     *          ["id" => 1, "name" => "Qw"],
     *          ["id" => 2, "name" => "Er"],
     *      ];
     * 
     *      Resulting response:
     *      [
     *          {"id": 1, "name": "Qw"},
     *          {"id": 2, "name": "Er"}
     *      ]
     * 
     */
    final protected function success(?array $data = null) : array|bool
    {
        if ($this->isInternalCall)
            return $data ?? true;

        return self::respond(200, $data);
    }

    /**
     * Sends an error response. Sets the HTTP status code to "500 Internal 
     * Server Error" by default or to the specified $code of "4xx Client Error" 
     * and exits the application.
     * 
     * For ::peek() calls, throws a \RuntimeException.
     * 
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    final protected function error(int $code = 500, ?string $status = null) : never
    {
        if ($code < 400 || $code > 500)
            throw new \InvalidArgumentException('The "int $code" parameter 
                value must be between 400 and 500, inclusive.');

        if ($this->isInternalCall)
            throw new \RuntimeException("API error with status code \"$code\""
                . $status ? ": $status." : ".");

        self::respond($code, $status ? ['status' => $status] : null);
    }

    /**
     * Sends a response only in case there are missing fields or fields with 
     * invalid data. Sets the HTTP status code to "400 Bad Request", sends the 
     * response and exits the application.
     * 
     * The response format can be one of the following OR A COMBINATION of
     * them:
     * 
     *  a) From setInvalid() method:
     *      {
     *          "invalid": {
     *              "price": {
     *                  "status": "CUSTOM_STATUS_CODE or message",
     *                  "params": {"minValue": 1}
     *              }
     *          }
     *      }
     * 
     *  b) A list of missing but required fields, specified in the required()
     *  method ((!) Missing fields are hidden in production env):
     *      {
     *          "missing": ["title", "url", "notes"]
     *      }
     * 
     * @internal
     * @throws \LogicException
     */
    private function respondOnFail() : self
    {
        $response = [];

        // List missing fields (if any).
        if ($this->missing) {

            // The missing fields are hidden in the production.
            if (App::isProd()) {

                if ($this->isInternalCall)
                    throw new \LogicException('Failed API peek: invalid request.');

                return $this->error(400, 'INVALID_REQUEST');
            }

            $response['missing'] = $this->missing;
        }

        // List fields that didn't pass validation (if any).
        if ($this->invalid) $response['invalid'] = $this->invalid;

        if (!$response)
            return $this;

        if ($this->isInternalCall)
            throw new \LogicException('Failed API peek: ' . JSON::encode($response));

        self::respond(400, $response);
    }

}
