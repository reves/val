<?php

namespace Val;

use DateTime;
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
     * Calls the requested API action method. Responds with an HTTP status code
     * of "404 Not Found" if the action method is private or does not exists.
     * Sends the fail response after calling the requested action method, if
     * necessary.
     */
    final public function __construct(?string $action = null)
    {
        if ($action) {

            if (App::_isCallable([$this, $action])) {

                $this->$action()->respondOnFail();
            }

        } else if (App::_isCallable($this)) {

            $this()->respondOnFail();
        }

        $this->respondError(404);
    }

    /**
     * Allows only GET requests to call the API action method. Responds with an
     * HTTP status code of "405 Method Not Allowed" if another request method
     * is used.
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
     * Allows only POST requests to call the API action method. Responds with
     * an HTTP status code of "403 Forbidden" if the CSRF Token is invalid.
     * Responds with an HTTP status code of "405 Method Not Allowed" if another
     * request method is used.
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
     * Allows only Authenticated user to call the API action method. Responds 
     * with an HTTP status code of "401 Unauthorized" if user is not
     * Authenticated.
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
     * Allows only Unauthenticated user to call the API action method. Responds
     * with an HTTP status code of "403 Forbidden" if user is not
     * Unauthenticated.
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
     * Sets the required fields for the requested API action method. Also calls 
     * user-defined validation methods for the required fields. Sends a fail
     * response if any of the listed fields is missing or any of the listed 
     * fields has an invalid value.
     * 
     * Validation method definition syntaxes:
     * 
     *  a) Without a parameter.
     *  Inside the validation method function, use the $this->val('field') 
     *  construction to get the value of the field.
     * 
     *      protected function validate<Fieldname>() {
     *         $value = $this->val('<fieldname>');
     *      }
     * 
     *  b) With a parameter containing the value of the field being validated.
     * 
     *      protected function validate<Fieldname>($value) {}
     * 
     *  c) With a parameter containing a reference to the value of the field
     *  being validated. That is, the field value can be changed or modified.
     * 
     *      protected function validate<Fieldname>(&$value) {}
     * 
     * ...where "<Fieldname>" is the name of the field whose value is being
     * validated.
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
     * Responds to a successfult request. Sets the HTTP status code to "200 OK"
     * and exits the application.
     */
    final protected function respondSuccess()
    {
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
    final protected function respondData(array $data)
    {
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
     */
    final protected function respondError(int $code = 500)
    {
        if ($code < 401 || $code > 500)
            throw new \InvalidArgumentException('The "int $code" parameter value must be between 401 and 500 inclusive.');

        http_response_code($code);
        App::exit();
    }

    /**
     * Sends a response only in case there is a problem with the data provided,
     * or if any precondition of the call has not been met. Sets the HTTP
     * status code to "400 Bad Request", sends the response and exits the
     * application.
     * 
     * The response format can be one of the following or a combination of them:
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
     */
    final protected function respondOnFail() : self
    {
        $response = [];

        if ($this->fail) $response['fail'] = $this->fail;
        if ($this->missing) $response['missing'] = $this->missing;
        if ($this->wrong) $response['wrong'] = $this->wrong;

        if ($response) {
            http_response_code(400);
            echo JSON::encode($response);
            App::exit();
        }

        return $this;
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
     * specified field whose value is not valid.
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

    /**
     * Returns dateTime formatted according to ISO 8601 format.
     */
    final public static function dateTime(?string $dateTime = null) : string
    {
        return (new DateTime($dateTime))->format(DateTime::ATOM);
    }

}
