<?php

use Illuminate\Support\Facades\Schedule;

// Geplante Aufgaben; im Betrieb ausgeführt vom Dienst «scheduler» (docker/compose.prod.yaml)

// Löschfristen (#41, #42): täglich nachts
Schedule::command('glossary:loeschfristen')->dailyAt('03:15')->withoutOverlapping();
