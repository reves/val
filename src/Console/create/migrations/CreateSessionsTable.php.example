<?php

use Val\Console\Interfaces\MigrationInterface;
use Val\App\{DB, DBDriver};

Class CreateSessionsTable Implements MigrationInterface
{
    public function up()
    {
        DB::raw(match (DB::$driver) {

            DBDriver::MySQL =>
                'CREATE TABLE IF NOT EXISTS `sessions` (
                    `Id` binary(16) NOT NULL,
                    `AccountId` binary(16) NOT NULL,
                    `SignedInAt` datetime NOT NULL,
                    `LastSeenAt` datetime NOT NULL,
                    `SignedInIPAddress` varbinary(16) DEFAULT NULL,
                    `LastSeenIPAddress` varbinary(16) DEFAULT NULL,
                    `DeviceSystem` varchar(63) DEFAULT NULL,
                    `DeviceBrowser` varchar(63) DEFAULT NULL,
                    PRIMARY KEY (`Id`),
                    KEY `AccountId` (`AccountId`)
                );',

            DBDriver::PostgreSQL =>
                'CREATE TABLE IF NOT EXISTS "sessions" (
                    "Id" uuid NOT NULL PRIMARY KEY,
                    "AccountId" uuid NOT NULL,
                    "SignedInAt" timestamp NOT NULL,
                    "LastSeenAt" timestamp NOT NULL,
                    "SignedInIPAddress" inet DEFAULT NULL,
                    "LastSeenIPAddress" inet DEFAULT NULL,
                    "DeviceSystem" varchar(63) DEFAULT NULL,
                    "DeviceBrowser" varchar(63) DEFAULT NULL
                );
                CREATE INDEX "AccountId_idx" ON "sessions" ("AccountId");',

            DBDriver::SQLite =>
                'CREATE TABLE IF NOT EXISTS "sessions" (
                    "Id" text NOT NULL PRIMARY KEY,
                    "AccountId" text NOT NULL,
                    "SignedInAt" text NOT NULL,
                    "LastSeenAt" text NOT NULL,
                    "SignedInIPAddress" BLOB DEFAULT NULL,
                    "LastSeenIPAddress" BLOB DEFAULT NULL,
                    "DeviceSystem" text DEFAULT NULL,
                    "DeviceBrowser" text DEFAULT NULL
                );
                CREATE INDEX "AccountId_idx" ON "sessions" ("AccountId");'

        });

    }

    public function down()
    {
        DB::raw('DROP TABLE sessions;');
    }

}
