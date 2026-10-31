<?php
// Filesystem roots for the monorepo packages (resolved from this file, so they
// are correct under Docker, XAMPP and shared hosting alike).
define("PACKAGES_BASE", dirname(__DIR__) . "/");
define("BACKEND_PATH", __DIR__);
define("FRONTEND_PATH", dirname(__DIR__) . "/frontend");
