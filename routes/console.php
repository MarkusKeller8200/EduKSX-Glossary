<?php

use Illuminate\Support\Facades\Schedule;

// Geplante Aufgaben; im Betrieb ausgeführt vom Dienst «scheduler» (docker/compose.prod.yaml)

// Löschfristen (#41, #42): täglich nachts
Schedule::command('glossary:loeschfristen')->dailyAt('03:15')->withoutOverlapping();

// Fehlgeschlagene Mail-Aufträge nach 30 Tagen entfernen (Löschkonzept)
Schedule::command('queue:prune-failed', ['--hours' => 720])->dailyAt('03:30');

// Kopie der Datenbank für die Sicherung ausser Haus (#197): täglich vor dem Dienst «backup» (02:30)
Schedule::command('glossary:sicherung')->dailyAt('02:15')->withoutOverlapping();
