<?php

namespace Val;

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
    private array $fail;

    // List of missing but required fields.
    private array $missing;

    // List of fields with an invalid value.
    private array $wrong;

    // Request data fields and their values.
    private array $fields;

    /**
     * Calls the requested API action method. Responds with an HTTP status code of 
     * "404 Not Found" if the action method is private or does not exists. Sends the 
     * fail response after calling the requested action method, if necessary.
     */
    final public function __construct(?string $action = null)
    {
        if (!$action || !App::_isCallable([$this, $action])) {

            $this->respondError(404);
        }

        $this->$action()->respondOnFail();
    }

    /**
     * Used only for a successful POST request. Sets the HTTP status code to "200 OK" 
     * and exits the application.
     * 
     * @throws \LogicException
     */
    final protected function respondSuccess()
    {
        if (!$this->flagOnlyPOST)
            throw new \LogicException('This type of response is valid only for the POST method.');
        
        http_response_code(200);
        App::exit();
    }

    /**
     * Used only for a successful GET request. Sets the HTTP status code to "200 OK", 
     * sends the data and exits the application.
     * 
     * Follow the recommended response formats (which can be combined if necessary):
     * 
     *  a) E.g. a single post.
     *      ["post" => ["id" => 1, "name" => "Qwerty"]]
     * 
     *  b) E.g. multiple posts.
     *      ["posts" => [["id" => 1, "name" => "Qw"], ["id" => 2, "name" => "Er"], ...]]
     * 
     * @throws \LogicException
     */
    final protected function respondData(array $data)
    {
        if (!$this->flagOnlyGET)
            throw new \LogicException('This type of response is valid only for the GET method.');
        
        http_response_code(200);
        echo JSON::encode($data);
        App::exit();
    }

    /**
     * Used for unsuccessful request processing or invalid call conditions. Sets the 
     * HTTP status code to "500 Internal Server Error" or "4xx Client Error" (except 
     * 400) and exits the application. This type of response is valid for both GET and 
     * POST methods.
     * 
     * @throws \InvalidArgumentException
     */
    final protected function respondError(int $code)
    {
        if ($code < 401 || $code > 500)
            throw new \InvalidArgumentException('The "int $code" parameter value must be between 401 and 500 inclusive.');

        http_response_code($code);
        App::exit();
    }

    /**
     * Warning (!) use this method only if a custom fail response is required.
     * 
     * Used when there is a problem with the data provided, or if any precondition of 
     * the call has not been met. Sets the HTTP status code to "400 Bad Request", sends 
     * the response and exits the application.
     * 
     * Follow the recommended response formats (which can be combined if necessary):
     * 
     *  a) E.g. describe the reasons why data validation failed.
     *      ['fail' => ['status' => "specific_status_code", 'description' => "Description"]]
     * 
     *  b) E.g. enumerate missing but required fields.
     *      ['missing' => ["title", "url", "notes"]]
     * 
     *  c) E.g. describe invalid field values and their mandatory parameters.
     *      ['wrong' => ["price" => ['status' => "specific_status_code", 'params' => ["minValue" => 1]]]]
     * 
     */
    final protected function respondFail(array $response)
    {
        http_response_code(400);
        echo JSON::encode($response);
        App::exit();
    }

    /**
     * Sets the specific status code and optional description which describe the reasons 
     * why data validation failed. The See the documentation for the self::respondFail() 
     * method for details.
     */
    final protected function setFail(string $status, ?string $description = null) : self
    {
        $this->fail = ['status' => $status];
        if ($description) $this->fail['description'] = $description;
        return $this;
    }

    /**
     * Sets the description for the field whose value is not valid. The description 
     * contains a specific status code and mandatory parameters for the field. See the 
     * documentation for the self::respondFail() method for details.
     */
    final protected function setWrong(string $field, string $status, ?array $params = null) : self
    {
        $this->wrong[$field] = ['status' => $status];
        if ($params) $this->wrong[$field]['params'] = $params;
        return $this;
    }

    /**
     * Sends a fail response, only if the request failed.
     */
    final protected function respondOnFail() : self
    {
        $response = [];

        if ($this->fail) $response['fail'] = $this->fail;
        if ($this->missing) $response['missing'] = $this->missing;
        if ($this->wrong) $response['wrong'] = $this->wrong;
        if ($response) $this->respondFail($response);

        return $this;
    }

    /**
     * Allows only GET requests to call the API action method. Responds with an HTTP 
     * status code of "405 Method Not Allowed" if another request method is used.
     * 
     * @throws \LogicException
     */
    final protected function onlyGET() : self
    {
        if ($this->flagOnlyPOST)
            throw new \LogicException('It is not possible to allow only the GET method, only the POST method is already allowed.');

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {

            $this->flagOnlyGET = true;
            return $this;
        }

        $this->respondError(405);
    }

    /**
     * Allows only POST requests to call the API action method. Responds with an HTTP 
     * status code of "403 Forbidden" if the CSRF Token is invalid. Responds with an 
     * HTTP status code of "405 Method Not Allowed" if another request method is used.
     * 
     * @throws \LogicException
     */
    final protected function onlyPOST() : self
    {
        if ($this->flagOnlyGET)
            throw new \LogicException('It is not possible to allow only the POST method, only the GET method is already allowed.');

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
     * Allows only Authenticated user to call the API action method. Responds with an 
     * HTTP status code of "401 Unauthorized" if user is not Authenticated.
     * 
     * @throws \LogicException
     */
    final protected function onlyAuthenticated() : self
    {
        if ($this->flagOnlyUnauthenticated)
            throw new \LogicException('It is not possible to allow only Authenticated user, only Unauthenticated user is already allowed.');

        if (Auth::getAccountId()) {

            $this->flagOnlyAuthenticated = true;
            return $this;
        }

        $this->respondError(401);
    }

    /**
     * Allows only Unauthenticated user to call the API action method. Responds with an 
     * HTTP status code of "403 Forbidden" if user is not Unauthenticated.
     * 
     * @throws \LogicException
     */
    final protected function onlyUnauthenticated() : self
    {
        if ($this->flagOnlyAuthenticated)
            throw new \LogicException('It is not possible to allow only Unauthenticated user, only Authenticated user is already allowed.');

        if (!Auth::getAccountId()) {

            $this->flagOnlyUnauthenticated = true;
            return $this;
        }
        
        $this->respondError(403);
    }

    /**
     * Gets the value of the specified field.
     * 
     * @throws \InvalidArgumentException
     */
    final protected function val(string $field)
    {
        if (!isset($this->fields[$field]))
            throw new \InvalidArgumentException('The "' . $field . '" field is not set, make sure it is specified as a required field.');

        return $this->fields[$field];
    }

    /**
     * Sets the required fields for the requested API action method. Also calls 
     * user-defined validation methods for the required fields. Sends a fail response 
     * if any of the listed fields is missing or any of the listed fields has an 
     * invalid value.
     * 
     * Validation method definition syntaxes:
     * 
     *  a) Without a parameter. Inside the method function, use the $this->val('field') 
     *  construction to get the value of the field.
     * 
     *      protected function validate<Fieldname>() {}
     * 
     *  b) With a parameter containing the value of the field being validated.
     * 
     *      protected function validate<Fieldname>($value) {}
     * 
     *  c) With a parameter containing a reference to the value of the field being 
     *  validated. That is, the field value can be changed or modified.
     * 
     *      protected function validate<Fieldname>(&$value) {}
     * 
     * ...where "<Fieldname>" is the name of the field whose value is being validated.
     * 
     */
    final protected function required(string ...$fields) : self
    {
        foreach ($fields as $field) {

            if ($this->flagOnlyGET && isset($_GET[$field])) {

                $this->fields[$field] = $_GET[$field];
                continue;
            }

            if ($this->flagOnlyPOST && isset($_POST[$field])) {

                $this->fields[$field] = $_POST[$field];
                continue;
            }

            if (isset($_REQUEST[$field])) {

                $this->fields[$field] = $_REQUEST[$field];
                continue;
            }

            $this->missing[] = $field;
        }

        $this->respondOnFail();

        // Call user-defined validation methods for the required fields
        foreach ($this->fields as $field => &$value) {

            $method = 'validate' . ucfirst($field);

            if (method_exists($this, $method)) {

                $this->$method($value);
            }
        }

        return $this->respondOnFail();
    }

}
