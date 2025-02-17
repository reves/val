<?php

namespace Val\App;

Abstract Class Auth
{
    const string COOKIE_NAME = '__Host-passport';

    const int SESSION_LIFETIME_DAYS = 365;
    const int SESSION_MAX_OFFLINE_DAYS = 7;
    const int TOKEN_TRUST_SECONDS = 5;
    const int SESSION_UPDATE_SECONDS = 30;
    const int MAX_ACTIVE_SESSIONS = 30;

    // Authentication session data.
    protected static ?array $session = null;

    /**
     * Initializes authentication and tries to authenticate the user. Does 
     * nothing if the DB module not initialized or Auth config is missing.
     * 
     * @throws \LogicException
     */
    public static function init() : void
    {
        // Already authenticated, or DB mobule not initialized, or Auth 
        // configuration file is missing.
        if (self::$session || !DB::init() || Config::auth() === null)
            return;

        if (Config::app() === null)
            throw new \LogicException('The App configuration file is missing.');

        if (!Cookie::isSet(self::COOKIE_NAME))
            return;

        // Extract data from the session cookie.
        $session = Token::extract(Cookie::get(self::COOKIE_NAME));

        // Unset the invalid auth cookie.
        if ($session === null) {
            Cookie::unset(self::COOKIE_NAME);
            return;
        }
        
        self::$session = $session;

        // Revoke the session if it has expired.
        if (self::isSessionExpired($session)) {
            self::revokeSession();
            return;
        }

        // Authenticate.
        self::verifySession($session) && self::updateSession($session);
    }

    /**
     * Returns true if the session has expired, or false otherwise.
     */
    private static function isSessionExpired(array $session) : bool
    {
        return
            // Session has reached its definitive expiration date.
            Token::expired(
                $session['signedInAt'],
                Config::auth('session_lifetime_days') ?? self::SESSION_LIFETIME_DAYS,
                Token::TIME_DAYS
            )
            ||
            // The user was offline for too long.
            Token::expired(
                $session['lastSeenAt'],
                Config::auth('session_max_offline_days') ?? self::SESSION_MAX_OFFLINE_DAYS,
                Token::TIME_DAYS
            );
    }

    /**
     * Verifies that the session is valid.
     */
    private static function verifySession(array $session) : bool
    {
        if (!Token::expired(
                $session['sessionLastVerifyAt'],
                Config::auth('token_trust_seconds') ?? self::TOKEN_TRUST_SECONDS,
                Token::TIME_SECONDS)
        ) {

            // To avoid hitting the database and parsing the User-Agent on
            // every request, skip verification and trust the token for a 
            // certain amount of time.
            return false; // prevent updateSession() from being called.
        }

        // Check if the User-Agent signature has changed (e.g. cookie theft).
        if ($session['device']) {

            $device = self::getDevice();

            if (!$device ||
                $device['system'] !== $session['device']['system'] ||
                $device['browser'] !== $session['device']['browser']
            ) {
                self::revokeSession();
                return false;
            }
        }

        // Check if the session exists in the database.
        $result = DB::prepare(match (DB::$driver) {
            DBDriver::MySQL =>
                'SELECT 1 FROM `sessions` WHERE `Id` = UUID_TO_BIN(:id)',
            DBDriver::PostgreSQL, DBDriver::SQLite =>
                'SELECT 1 FROM "sessions" WHERE "Id" = :id',
        })->bind(':id', $session['id'])->single();

        if (!$result) {
            // The session has been revoked.
            Cookie::unset(self::COOKIE_NAME);
            self::$session = null;
            return false;
        }

        return true;
    }

    /**
     * Updates the session data if needed.
     */
    private static function updateSession(array $session) : void
    {
        // Update the "last verify" data.
        self::$session['sessionLastVerifyAt'] = DB::dateTime();

        // Check if enough time has passed since the "last seen" data update.
        if (!Token::expired(
                self::$session['lastSeenAt'],
                Config::auth('session_update_seconds') ?? self::SESSION_UPDATE_SECONDS,
                Token::TIME_SECONDS)
        ) {
            // Not enough time passed, just commit the "last verify" data.
            self::setSessionCookie(self::$session);
            return;
        }

        // Update the "last seen" data.
        $IPAddress = self::$session['lastSeenIPAddress'] = self::getIPAddress();
        self::$session['lastSeenAt'] = self::$session['sessionLastVerifyAt'];

        // Update the session data in the database.
        DB::prepare(match (DB::$driver) {
            DBDriver::MySQL =>
                'UPDATE `sessions` SET `LastSeenAt` = :lastSeenAt,
                    `LastSeenIPAddress` = :lastSeenIPAddress
                    WHERE `Id` = UUID_TO_BIN(:id)',
            DBDriver::PostgreSQL, DBDriver::SQLite =>
                'UPDATE "sessions" SET "LastSeenAt" = :lastSeenAt,
                    "LastSeenIPAddress" = :lastSeenIPAddress
                    WHERE "Id" = :id'
        })->bindMultiple([
            ':id' => $session['id'],
            ':lastSeenAt' => self::$session['sessionLastVerifyAt'],
            ':lastSeenIPAddress' => (DB::$driver === DBDriver::PostgreSQL)
                ? $IPAddress
                : ($IPAddress ? inet_pton($IPAddress) : null)
        ])->execute();

        if (!DB::rowCount()) {
            // Unable to update in the database, revoke the session.
            Cookie::unset(self::COOKIE_NAME);
            self::$session = null;
            return;
        }

        // Update the session cookie.
        self::setSessionCookie(self::$session);
    }

    /**
     * Initializes a new session for a given accountId (UUID). Returns true
     * on success, or false on error or if too many active sessions.
     * 
     * @throws \LogicException
     */
    public static function initSession(string $accountId) : bool
    {
        // User Authenticated.
        if (self::$session)
            throw new \LogicException('User Authenticated.');

        // Remove the orphaned expired sessions, if any (for example, when the
        // authentication cookie of one of the previous sessions was manually
        // deleted).
        self::removeExpiredSessions($accountId);

        // Check if the user has reached the maximum number of active sessions.
        $maxActiveSessions = Config::auth('max_active_sessions')
            ?? self::MAX_ACTIVE_SESSIONS;

        if ($maxActiveSessions) {

            $activeSessionsCount = DB::prepare(match (DB::$driver) {
                DBDriver::MySQL =>
                    'SELECT COUNT(*) AS `Count` FROM `sessions`
                        WHERE `AccountId` = UUID_TO_BIN(:accountId)',
                DBDriver::PostgreSQL, DBDriver::SQLite =>
                    'SELECT COUNT(*) AS "Count" FROM "sessions"
                        WHERE "AccountId" = :accountId',
            })->bind(':accountId', $accountId)->single()['Count'];

            if ($activeSessionsCount >= $maxActiveSessions)
                return false;
        }

        // Create a new session.
        $sessionId = UUID::generate();
        $timeNow = DB::dateTime();
        $device = self::getDevice();
        $IPAddress = self::getIPAddress();
        $IPaddressDB = (DB::$driver !== DBDriver::PostgreSQL)
            ? ($IPAddress ? inet_pton($IPAddress) : null)
            : $IPAddress;

        // Save the session data to the database.
        $result = DB::prepare(match (DB::$driver) {
            DBDriver::MySQL =>
                'INSERT INTO `sessions` VALUES (
                    UUID_TO_BIN(:id), UUID_TO_BIN(:accountId), :signedInAt,
                    :lastSeenAt, :signedInIPAddress, :lastSeenIPAddress, 
                    :deviceSystem, :deviceBrowser)',
            DBDriver::PostgreSQL, DBDriver::SQLite =>
                'INSERT INTO "sessions" VALUES (
                    :id, :accountId, :signedInAt, :lastSeenAt,
                    :signedInIPAddress, :lastSeenIPAddress, :deviceSystem,
                    :deviceBrowser)'
        })->bindMultiple([
            ':id'                => $sessionId,
            ':accountId'         => $accountId,
            ':signedInAt'        => $timeNow,
            ':lastSeenAt'        => $timeNow,
            ':signedInIPAddress' => $IPaddressDB,
            ':lastSeenIPAddress' => $IPaddressDB,
            ':deviceSystem'      => $device['system'] ?? null,
            ':deviceBrowser'     => $device['browser'] ?? null
        ])->execute();

        if (!$result)
            return false;

        // Set session data.
        $session = [
            'id'                  => $sessionId,
            'accountId'           => $accountId,
            'signedInAt'          => $timeNow,
            'lastSeenAt'          => $timeNow,
            'signedInIPAddress'   => $IPAddress,
            'lastSeenIPAddress'   => $IPAddress,
            'device'              => $device,
            'sessionLastVerifyAt' => $timeNow
        ];

        // Set the session cookie.
        if (!self::setSessionCookie($session))
            return false;

        self::$session = $session;
        return true;
    }

    /**
     * Revokes the authentication session by a given session UUID. If no
     * parameter given, revokes the current session. Returns true on success, 
     * or false if the session is not found in the database.
     * 
     * @throws \LogicException
     */
    public static function revokeSession(?string $id = null) : bool
    {
        // User unauthenticated.
        if (!self::$session)
            throw new \LogicException('User unauthenticated.');

        $id ??= self::$session['id'];

        // Remove current session data.
        if ($id === self::$session['id']) {
            Cookie::unset(self::COOKIE_NAME);
            self::$session = null;
        }

        // Remove the session from the database.
        DB::prepare(match (DB::$driver) {
            DBDriver::MySQL =>
                'DELETE FROM `sessions` WHERE `Id` = UUID_TO_BIN(:id)',
            DBDriver::PostgreSQL, DBDriver::SQLite =>
                'DELETE FROM "sessions" WHERE "Id" = :id'
        })->bind(':id', $id)->execute();

        return !!DB::rowCount();
    }

    /**
     * Revokes all the sessions of the current user. Returns true on success,
     * or false if no sessions were found in the database.
     * 
     * @throws \LogicException
     */
    public static function revokeAllSessions() : bool
    {
        // User unauthenticated.
        if (!self::$session)
            throw new \LogicException('User unauthenticated.');

        // Remove current session data.
        Cookie::unset(self::COOKIE_NAME);
        self::$session = null;

        // Remove all the user's sessions from the database.
        DB::prepare(match (DB::$driver) {
             DBDriver::MySQL =>
                'DELETE FROM `sessions`
                    WHERE `AccountId` = UUID_TO_BIN(:accountId)',
            DBDriver::PostgreSQL, DBDriver::SQLite =>
                'DELETE FROM "sessions"
                    WHERE "AccountId" = :accountId'
        })->bind(':accountId', self::$session['accountId'])->execute();

        return !!DB::rowCount();
    }

    /**
     * Revokes all the sessions of the current user, except the current
     * session. Returns true on succes, or false if no other sessions were
     * found in the database.
     * 
     * @throws \LogicException
     */
    public static function revokeOtherSessions() : bool
    {
        // User unauthenticated.
        if (!self::$session)
            throw new \LogicException('User unauthenticated.');

        // Remove all the user's sessions from the database, except the
        // current session.
        DB::prepare(match (DB::$driver) {
             DBDriver::MySQL =>
                'DELETE FROM `sessions`
                    WHERE `AccountId` = UUID_TO_BIN(:accountId)
                    AND `Id` <> UUID_TO_BIN(:id)',
            DBDriver::PostgreSQL, DBDriver::SQLite =>
                'DELETE FROM "sessions"
                    WHERE "AccountId" = :accountId
                    AND "Id" <> :id'
        })->bindMultiple([
            ':accountId' => self::$session['accountId'],
            ':id' => self::$session['id'],
        ])
        ->execute();

        return !!DB::rowCount();
    }

    /**
     * Removes all the expired sessions of a given account UUID from the
     * database. Returns true on success, or false if no expired sessions were
     * found in the database.
     */
    public static function removeExpiredSessions(string $accountId) : bool
    {
        $cutoffDateTime = DB::dateTime(
            time() - 86400 * (Config::auth('session_lifetime_days')
                ?? self::SESSION_LIFETIME_DAYS)
        );

        // Remove all the expired sessions from the database.
        DB::prepare(match (DB::$driver) {
             DBDriver::MySQL =>
                'DELETE FROM `sessions`
                    WHERE `AccountId` = UUID_TO_BIN(:accountId)
                    AND `SignedInAt` < :cutoffDateTime',
            DBDriver::PostgreSQL, DBDriver::SQLite =>
                'DELETE FROM "sessions"
                    WHERE "AccountId" = :accountId
                    AND "SignedInAt" < :cutoffDateTime',
        })->bindMultiple([
            ':accountId' => $accountId,
            ':cutoffDateTime' => $cutoffDateTime
        ])
        ->execute();

        return !!DB::rowCount();
    }

    /**
     * Returns the accountId (UUID) associated with the current session,
     * or null if the user is unauthenticated.
     */
    public static function getAccountId() : ?string
    {
        return self::$session['accountId'] ?? null;
    }

    /**
     * Returns the dateTime the session was initialized.
     */
    public static function getSignedInAt() : ?string
    {
        return self::$session['signedInAt'] ?? null;
    }

    /**
     * Returns the dateTime the session data updated.
     */
    public static function getLastSeenAt() : ?string
    {
        return self::$session['lastSeenAt'] ?? null;
    }

    /**
     * Returns the IP address of the session initialization.
     */
    public static function getSignedInIPAddress() : ?string
    {
        return self::$session['signedInIPAddress'] ?? null;
    }

    /**
     * Returns the IP address of the session update.
     */
    public static function getLastSeenIPAddress() : ?string
    {
        return self::$session['lastSeenIPAddress'] ?? null;
    }

    /**
     * Sets or updates the session cookie.
     * 
     * @throws \LogicException
     */
    protected static function setSessionCookie(array $session) : bool
    {
        $token = Token::create($session);

        if (!$token) {
            error_log('Unable to encrypt the session token, check the app key.');
            return false;
        }

        return Cookie::setForDays(
            self::COOKIE_NAME,
            $token,
            Config::auth('session_lifetime_days') ?? self::SESSION_LIFETIME_DAYS
        );
    }

    /**
     * Returns the user's IP address, or null if unable to determine.
     */
    public static function getIPAddress() : ?string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (array_key_exists($header, $_SERVER))
                return trim(strtok($_SERVER[$header], ','));
        }

        return null;
    }

    /**
     * Parses User-Agent signature, such as system and browser infos. Returns
     * an associative array, or null, if the User-Agent header is not set, or
     * the optional $userAgent parameter is empty. This method is used for
     * precautionary purposes, such as to detect theft of the session cookie.
     * 
     * Resulting array example:
     *  [
     *      'system' => 'Linux; Android 13; SM-S901B',
     *      'browser' => 'Chrome Mobile Safari' // may be empty
     *  ]
     */
    protected static function getDevice(?string $userAgent = null) : ?array
    {
        $UA = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';

        // User-Agent header not set, or empty
        if (!$UA)
            return null;

        // Split in 2 parts: system information and extensions
        $parts = explode(')', $UA, 2);

        // Parse system information from part 1. If it is not in parantheses,
        // then the User-Agent string itself represents the system information
        // (part 2 will be empty in this case).
        $system = explode('(', $parts[0], 2)[1] ?? $parts[0];

        // Strip system builds (Build/a.b.c) or minor versions (.b.c-beta).
        $system = preg_replace('/(\s?Build)?((\/[\w\-]+)|[\.\_])[\w\.\-]*/i', '', $system);

        // Parse browser information from part 2.
        $part2 = $parts[1] ?? '';
        $list = explode(' ', ltrim(explode(')', $part2, 2)[1] ?? $part2));

        // Strip browsers versions.
        $browser = '';
        foreach ($list as $browserVer)
            $browser .= ' ' . strtok($browserVer, '/');

        return [
            // substr for a more efficient storage in db (utf8mb4 varchar(63))
            'system' => substr($system, 0, 63),
            // remove the extra space character at the beginning
            'browser' => substr($browser, 1, 63)
        ];
    }

}
