<?php

use Illuminate\Support\Facades\Schedule;

// Geplante Aufgaben; im Betrieb ausgeführt vom Dienst «scheduler» (docker/compose.prod.yaml)

// Löschfristen (#41, #42): täglich nachts
Schedule::command('glossary:loeschfristen')->dailyAt('03:15')->withoutOverlapping();

// Fehlgeschlagene Mail-Aufträge nach 30 Tagen entfernen (Löschkonzept)
Schedule::command('queue:prune-failed', ['--hours' => 720])->dailyAt('03:30');

// Automatische Updates nach Richtlinie (#178): stündlich prüfen, ausgeführt nur im eingestellten Zeitfenster
Schedule::command('glossary:updates-pruefen')->hourlyAt(5)->withoutOverlapping();
