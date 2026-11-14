<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/** Jeder Protokolleintrag nennt Modul und Version (#192); request_id kommt aus eduksx/core (AssignRequestId). */
final class ModuleProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: $record->extra + [
            'module' => 'glossary',
            'version' => (string) config('glossary.version'),
        ]);
    }
}
