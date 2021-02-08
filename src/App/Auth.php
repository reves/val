<?php

namespace Val\App;

Class Auth
{
    // Database module
    protected Database $db;

    // Authentication session data
    protected ?array $session;

    /**
     * Identifies the authentication session cookie and tries to authenticate the user.
     */
    public function __construct(Database $db)
    {
        $this->db = $db;

        // Get the encrypted authentication session data from the cookie.
        if (Cookie::isSet(Config::account('auth_session_cookie_name'))) {

            $this->session = Token::extract(Cookie::get(Config::account('auth_session_cookie_name')));

            // The authentication session data decrypted and decoded successfully.
            if ($this->session !== null) {

                // The authentication session did not expire yet.
                if (!Token::expired($this->session['signedInAt'], Config::account('auth_session_ttl_days'), Token::EXPIRED_DAYS)) {
                
                    $this->db->prepare('SELECT EXISTS(SELECT 1 FROM val_auth_sessions WHERE Id = :sessionId) AS AuthSessionFound')
                        ->bind(':sessionId', $this->session['sessionId']);

                    // The authentication session record was found in the database.
                    if ($this->db->single()['AuthSessionFound']) {

                        $device = Device::get();

                        // The data of the current device matches the data of the device used for the authentication session initialization.
                        if (
                            $this->session['device']['type'] === mb_substr($device['type'], 0, 63) &&
                            $this->session['device']['platform'] ===  mb_substr($device['platform'], 0, 63) &&
                            $this->session['device']['browser'] === mb_substr($device['browser'], 0, 63)
                        ) {

                            // Update the authentication session.
                            if (Token::expired($this->session['lastSeenAt'], Config::account('auth_session_update_minutes'), Token::EXPIRED_MINUTES)) {

                                $this->updateSession();

                            }
                            
                            // User successfully authenticated.
                            return;
                        }
                    }
                }
            }
            
            $this->session = null;
            $this->revokeSession();
        }
    }

    public function __destruct()
    {
        unset($this->db);
    }

    /**
     * Gets the id of the Authenticated user account, or null if the user is 
     * Unauthenticated.
     */
    public function getAccountId() : ?string
    {
        return $this->session['accountId'] ?? null;
    }

    /**
     * Gets the authentication session id of the Authenticated user account, or null if 
     * the user is Unauthenticated.
     */
    public function getSessionId() : ?string
    {
        return $this->session['sessionId'] ?? null;
    }

    /**
     * Initializes an authentication session for a given Account Id.
     * Authentication session is stateful as its data is stored in the database.
     * Returns true on success, false on error.
     */
    public function initSession(string $accountId) : bool
    {
        $device = Device::get();
        $timeNow = Database::dateTime();
        $IPAddress = $_SERVER['REMOTE_ADDR'];

        // Save the authentication session data to the database.
        $this->db->prepare('INSERT INTO val_auth_sessions (AccountId, DeviceType, DevicePlatform, DeviceBrowser, 
                            SignedInAt, LastSeenAt, SignedInIPAddress, LastSeenIPAddress) 
                            VALUES(:accountId, :deviceType, :devicePlatform, :deviceBrowser, :signedInAt, :lastSeenAt, 
                            INET6_ATON(:signedInIPAddress), INET6_ATON(:lastSeenIPAddress))')
            ->bindMultiple([
                ':accountId' =>         $accountId,
                ':deviceType' =>        $device['type'],
                ':devicePlatform' =>    $device['platform'],
                ':deviceBrowser' =>     $device['browser'],
                ':signedInAt' =>        $timeNow,
                ':lastSeenAt' =>        $timeNow,
                ':signedInIPAddress' => $IPAddress,
                ':lastSeenIPAddress' => $IPAddress
            ])->execute();

        $sessionId = $this->db->lastInsertId();

        if (!$sessionId) {

            return false;
        }

        // Save the autnhentication session data to the authentication session cookie.
        $this->session = [
            'sessionId' =>  $sessionId,
            'accountId' =>  $accountId,
            'device' =>     $device,
            'signedInAt' => $timeNow,
            'lastSeenAt' => $timeNow,
            'signedInIPAddress' => $IPAddress,
            'lastSeenIPAddress' => $IPAddress
        ];

        $sessionToken = Token::create($this->session);

        if (Cookie::setForDays(Config::account('auth_session_cookie_name'), $sessionToken, Config::account('auth_session_ttl_days'))) {

            return true;
        }

        $this->session = null;
        return false;
    }

    /**
     * Updates "last seen" fields of the saved authentication session data. Also 
     * updates the values stored in the database. Returns true on success, false on 
     * error.
     */
    public function updateSession() : bool
    {
        $timeNow = Database::dateTime();
        $IPAddress = $_SERVER['REMOTE_ADDR'];

        // Update the authentication session "last seen" data in the database.
        $this->db->prepare('UPDATE val_auth_sessions SET LastSeenAt = :lastSeenAt, LastSeenIPAddress = INET6_ATON(:lastSeenIPAddress) 
                            WHERE Id = :sessionId')
            ->bind(':lastSeenAt', $timeNow)
            ->bind(':lastSeenIPAddress', $IPAddress)
            ->bind(':sessionId', $this->getSessionId())
            ->execute();

        if (!$this->db->rowCount()) {

            return false;
        }

        // Update the authentication session "last seen" data in the authentication session 
        // cookie.
        $sessionUpdated = $this->session;
        $sessionUpdated['lastSeenAt'] = $timeNow;
        $sessionUpdated['lastSeenIPAddress'] = $IPAddress;

        $sessionToken = Token::create($sessionUpdated);

        if (Cookie::setForDays(Config::account('auth_session_cookie_name'), $sessionToken, Config::account('auth_session_ttl_days'))) {

            $this->session = $sessionUpdated;
            return true;
        }

        return false;
    }

    /**
     * Revokes the authentication session by given authentication session id. If no 
     * parameter given, Revokes the current authentication session. Returns true on 
     * success, false on error.
     */
    public function revokeSession(?string $sessionId = null) : bool
    {
        // Remove invalid authentication session cookie.
        if (!$this->session) {

            return Cookie::unset(Config::account('auth_session_cookie_name'));
        }

        // Remove the authentication session from the database by given authentication 
        // session id or by the current authentication session id.
        $this->db->prepare('DELETE FROM val_auth_sessions WHERE Id = :sessionId AND AccountId = :accountId')
            ->bind(':sessionId', $sessionId ?? $this->session['sessionId'])
            ->bind(':accountId', $this->session['accountId'])
            ->execute();

        if (!$this->db->rowCount()) {
            
            return false;
        }

        // Revoke the current authentication session and remove its authentication session 
        // cookie.
        if (!$sessionId || $this->session['sessionId'] == $sessionId) {

            $this->session = null;
            Cookie::unset(Config::account('auth_session_cookie_name'));

        }

        return true;
    }

    /**
     * Revokes all the authentication sessions. Returns true on success, false on 
     * error. 
     */
    public function revokeAllSessions() : bool
    {
        // Remove invalid authentication session cookie.
        if (!$this->session) {

            return Cookie::unset(Config::account('auth_session_cookie_name'));
        }

        // Remove all the authentication sessions from the database.
        $this->db->prepare('DELETE FROM val_auth_sessions WHERE AccountId = :accountId')
            ->bind(':accountId', $this->session['accountId'])
            ->execute();
        
        if (!$this->db->rowCount()) {

            return false;
        }

        // Revoke the current authentication session and remove its authentication session
        // cookie.
        $this->session = null;
        Cookie::unset(Config::account('auth_session_cookie_name'));

        return true;
    }

    /**
     * Revokes all the authentication sessions except the current one. Returns true on 
     * succes, false on error.
     */
    public function revokeOtherSessions() : bool
    {
        // Remove invalid authentication session cookie.
        if (!$this->session) {

            return Cookie::unset(Config::account('auth_session_cookie_name'));
        }

        // Remove all the authentication sessions from the database, except the current one.
        $this->db->prepare('DELETE FROM val_auth_sessions WHERE AccountId = :accountId AND Id <> :sessionId')
            ->bind(':sessionId', $this->session['sessionId'])
            ->bind(':accountId', $this->session['accountId'])
            ->execute();

        return (bool) $this->db->rowCount();
    }

}
