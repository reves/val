<?php

namespace Val\App;

Final Class Auth
{
    protected static ?self $instance = null;

    // Authentication session data
    protected static ?array $session = null;

    /**
     * Identifies the authentication session cookie and tries to authenticate the user.
     */
    protected function __construct()
    {
        // Get the encrypted authentication session data from the cookie.
        if (Cookie::isSet(Config::auth('session_cookie_name'))) {

            self::$session = Token::extract(Cookie::get(Config::auth('session_cookie_name')));

            // The authentication session data decrypted and decoded successfully.
            if (self::$session !== null) {

                // The authentication session did not expire yet (user is still active).
                if (!Token::expired(self::$session['lastSeenAt'], Config::auth('session_ttl_days'), Token::TIME_DAYS)) {

                    DB::prepare('SELECT EXISTS(SELECT 1 FROM ' . Config::db('table_auth') . ' WHERE Id = UUID_TO_BIN(:sessionId)) AS AuthSessionFound')
                        ->bind(':sessionId', self::$session['sessionId']);

                    // The authentication session record was found in the database.
                    if (DB::single()['AuthSessionFound']) {

                        $device = Device::get();

                        // The data of the current device matches the data of the device used for the authentication session initialization.
                        if (
                            self::$session['device']['type'] === mb_substr($device['type'], 0, 63) &&
                            self::$session['device']['platform'] ===  mb_substr($device['platform'], 0, 63) &&
                            self::$session['device']['browser'] === mb_substr($device['browser'], 0, 63)
                        ) {

                            // Update the authentication session data.
                            if (Token::expired(self::$session['lastSeenAt'], Config::auth('session_update_minutes'), Token::TIME_MINUTES)) {

                                self::updateSession();

                            }

                            // User successfully authenticated.
                            return;
                        }
                    }
                }
            }
            
            self::revokeSession(); // TODO: revoke all the expired sessions
            self::$session = null;
        }
    }

    /**
     * Initializes the user authentication module. Returns null if the configuration file
     * is missing or the database module cannot be initialized.
     */
    public static function init() : ?self
    {
        if (DB::init() === null || Config::auth() === null) {

            return null;
        }

        return self::$instance ?? self::$instance = new self;
    }

    /**
     * Returns the authentication session id of the Authenticated user account, or null if 
     * the user is Unauthenticated.
     */
    public static function getSessionId() : ?string
    {
        return self::$session['sessionId'] ?? null;
    }

    /**
     * Returns the id of the Authenticated user account, or null if the user is 
     * Unauthenticated.
     */
    public static function getAccountId() : ?string
    {
        return self::$session['accountId'] ?? null;
    }

    /**
     * Returns the device info array, or null if the user is Unauthenticated.
     * 
     *  Returning array keys:
     *      [
     *          'type',
     *          'platform',
     *          'browser'
     *      ]
     * 
     */
    public static function getDevice() : ?array
    {
        return  self::$session['device'] ?? null;
    }

    /**
     * Returns the dateTime of the initialization of the session.
     */
    public static function getSignedInAt() : ?string
    {
        return self::$session['signedInAt'] ?? null;
    }

    /**
     * Returns the dateTime of the last session data update.
     */
    public static function getLastSeenAt() : ?string
    {
        return self::$session['lastSeenAt'] ?? null;
    }

    /**
     * Returns the IP address that was when the session was initialized.
     */
    public static function getSignedInIPAddress() : ?string
    {
        return self::$session['signedInIPAddress'] ?? null;
    }

    /**
     * Returns the IP address that was the last time the session data was updated.
     */
    public static function getLastSeenIPAddress() : ?string
    {
        return self::$session['lastSeenIPAddress'] ?? null;
    }

    /**
     * Initializes an authentication session for a given Account Id.
     * Authentication session is stateful as its data is stored in the database.
     * Returns true on success, false on error.
     */
    public static function initSession(string $accountId) : bool
    {
        $device = Device::get();
        $timeNow = DB::dateTime();
        $IPAddress = '';

        // Get IP address of the client.
        $addressHeaders = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

		foreach ($addressHeaders as $header) {
			if (array_key_exists($header, $_SERVER)) {
				$addressChain = explode(',', $_SERVER[$header]);
				$IPAddress = trim($addressChain[0]);
				break;
			}
		}

        // Save the authentication session data to the database.
        $sessionId = DB::prepare('SELECT UUID() AS UUID')->single()['UUID'];
        $result = DB::prepare('INSERT INTO ' . Config::db('table_auth') . ' (Id, AccountId, DeviceType, DevicePlatform, 
                                DeviceBrowser, SignedInAt, LastSeenAt, SignedInIPAddress, LastSeenIPAddress) 
                                VALUES( UUID_TO_BIN(:id), :accountId, :deviceType, :devicePlatform, :deviceBrowser, 
                                :signedInAt, :lastSeenAt, INET6_ATON(:signedInIPAddress), INET6_ATON(:lastSeenIPAddress))')
        ->bindMultiple([
            ':id'                   => $sessionId,
            ':accountId'            => $accountId,
            ':deviceType'           => $device['type'],
            ':devicePlatform'       => $device['platform'],
            ':deviceBrowser'        => $device['browser'],
            ':signedInAt'           => $timeNow,
            ':lastSeenAt'           => $timeNow,
            ':signedInIPAddress'    => $IPAddress,
            ':lastSeenIPAddress'    => $IPAddress
        ])
        ->execute();

        if (!$result) {

            return false;
        }

        // Save the autnhentication session data to the authentication session cookie.
        self::$session = [
            'sessionId'         => $sessionId,
            'accountId'         => $accountId,
            'device'            => $device,
            'signedInAt'        => $timeNow,
            'lastSeenAt'        => $timeNow,
            'signedInIPAddress' => $IPAddress,
            'lastSeenIPAddress' => $IPAddress
        ];

        $sessionToken = Token::create(self::$session);

        if (Cookie::setForDays(Config::auth('session_cookie_name'), $sessionToken, Config::auth('session_ttl_days'))) {

            return true;
        }

        self::$session = null;
        return false;
    }

    /**
     * Updates "last seen" fields of the saved authentication session data. Also 
     * updates the values stored in the database. Returns true on success, false on 
     * error.
     */
    public static function updateSession() : bool
    {
        $timeNow = DB::dateTime();
        $IPAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        // Update the authentication session "last seen" data in the database.
        DB::prepare('UPDATE ' . Config::db('table_auth') . ' SET LastSeenAt = :lastSeenAt, LastSeenIPAddress = INET6_ATON(:lastSeenIPAddress) 
                            WHERE Id = UUID_TO_BIN(:sessionId)')
            ->bind(':lastSeenAt', $timeNow)
            ->bind(':lastSeenIPAddress', $IPAddress)
            ->bind(':sessionId', self::getSessionId())
            ->execute();

        if (!DB::rowCount()) {

            return false;
        }

        // Update the authentication session "last seen" data in the authentication session 
        // cookie.
        $sessionUpdated = self::$session;
        $sessionUpdated['lastSeenAt'] = $timeNow;
        $sessionUpdated['lastSeenIPAddress'] = $IPAddress;

        $sessionToken = Token::create($sessionUpdated);

        if (Cookie::setForDays(Config::auth('session_cookie_name'), $sessionToken, Config::auth('session_ttl_days'))) {

            self::$session = $sessionUpdated;
            return true;
        }

        return false;
    }

    /**
     * Revokes the authentication session by given authentication session id. If no 
     * parameter given, Revokes the current authentication session. Returns true on 
     * success, false on error.
     */
    public static function revokeSession(?string $sessionId = null) : bool
    {
        // Remove invalid authentication session cookie.
        if (!self::$session) {

            return Cookie::unset(Config::auth('session_cookie_name'));
        }

        // Remove the authentication session from the database by given authentication 
        // session id or by the current authentication session id.
        DB::prepare('DELETE FROM ' . Config::db('table_auth') . ' WHERE Id = UUID_TO_BIN(:sessionId) AND AccountId = :accountId')
            ->bind(':sessionId', $sessionId ?? self::$session['sessionId'])
            ->bind(':accountId', self::$session['accountId'])
            ->execute();

        if (!DB::rowCount()) {

            return false;
        }

        // Revoke the current authentication session and remove its authentication session 
        // cookie.
        if (!$sessionId || self::$session['sessionId'] == $sessionId) {

            self::$session = null;
            Cookie::unset(Config::auth('session_cookie_name'));

        }

        return true;
    }

    /**
     * Revokes all the authentication sessions. Returns true on success, false on 
     * error. 
     */
    public static function revokeAllSessions() : bool
    {
        // Remove invalid authentication session cookie.
        if (!self::$session) {

            return Cookie::unset(Config::auth('session_cookie_name'));
        }

        // Remove all the authentication sessions from the database.
        DB::prepare('DELETE FROM ' . Config::db('table_auth') . ' WHERE AccountId = :accountId')
            ->bind(':accountId', self::$session['accountId'])
            ->execute();
        
        if (!DB::rowCount()) {

            return false;
        }

        // Revoke the current authentication session and remove its authentication session
        // cookie.
        self::$session = null;
        Cookie::unset(Config::auth('session_cookie_name'));

        return true;
    }

    /**
     * Revokes all the authentication sessions except the current one. Returns true on 
     * succes, false on error.
     */
    public static function revokeOtherSessions() : bool
    {
        // Remove invalid authentication session cookie.
        if (!self::$session) {

            return Cookie::unset(Config::auth('session_cookie_name'));
        }

        // Remove all the authentication sessions from the database, except the current one.
        DB::prepare('DELETE FROM ' . Config::db('table_auth') . ' WHERE AccountId = :accountId AND Id <> UUID_TO_BIN(:sessionId)')
            ->bind(':sessionId', self::$session['sessionId'])
            ->bind(':accountId', self::$session['accountId'])
            ->execute();

        return (bool) DB::rowCount();
    }

}
