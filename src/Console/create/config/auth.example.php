<?php

/**
 * Authentication and session settings.
 * 
 * Config required for modules: Auth.
 */
return [

    /**
     * The session will permanently expire after this duration (in days).
     * 
     * Default: Val\App\Auth::SESSION_LIFETIME_DAYS
     */
    'session_lifetime_days' => 365,

    /**
     * The session will expire if the device remains inactive for this
     * duration (in days).
     * 
     * Default: Val\App\Auth::SESSION_MAX_OFFLINE_DAYS
     */
    'session_max_offline_days' => 7,

    /**
     * Duration (in seconds) to trust the session token before re-checking in 
     * the database if the session still remains valid.
     * 
     * Reduces database load by avoiding verification on every request.
     * 
     * Default: Val\App\Auth::TOKEN_TRUST_SECONDS
     */
    'token_trust_seconds' => 5,

    /**
     * The "last seen" data is updated in the database no more frequently than 
     * this duration (in seconds).
     * 
     * Default: Val\App\Auth::SESSION_UPDATE_SECONDS
     */
    'session_update_seconds' => 60,

    /**
     * The maximum number of active sessions per account.
     * 
     * The value of "0" or "false" means no limit.
     * Default: Val\App\Auth::MAX_ACTIVE_SESSIONS
     */
    'max_active_sessions' => 30,

];
