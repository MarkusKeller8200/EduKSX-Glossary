<?php

namespace App\Access;

use RuntimeException;

/** Verwaltungsaktion ausserhalb der eigenen Befugnis oder gegen eine Schutzregel (z. B. letzter Developer). */
final class AccessDenied extends RuntimeException {}
