<?php

// Temporary debug: patches vendor/pestphp/pest/src/Plugins/Parallel/Paratest/WrapperRunner.php
// to print PHPUnit warning details to stderr before the exit code is computed.
// Used to diagnose pest issue #1483 (exit 1 with all tests passing under --parallel).

$file = __DIR__.'/../../vendor/pestphp/pest/src/Plugins/Parallel/Paratest/WrapperRunner.php';
$contents = file_get_contents($file);

$needle = '$exitcode = Result::exitCode($this->options->configuration, $testResultSum);';

if (! str_contains($contents, $needle)) {
    fwrite(STDERR, "debug-pest-warnings: target line not found in {$file}\n");
    exit(1);
}

$debug = <<<'CODE'
fwrite(STDERR, "\n=== PEST DEBUG ===\n");
        fwrite(STDERR, "wasSuccessful: ".var_export($testResultSum->wasSuccessful(), true)."\n");
        fwrite(STDERR, "hasPhpunitWarnings: ".var_export($testResultSum->hasPhpunitWarnings(), true)."\n");
        fwrite(STDERR, "hasTestErroredEvents: ".var_export($testResultSum->hasTestErroredEvents(), true)."\n");
        fwrite(STDERR, "hasTestFailedEvents: ".var_export($testResultSum->hasTestFailedEvents(), true)."\n");
        fwrite(STDERR, "hasTestTriggeredPhpunitErrorEvents: ".var_export($testResultSum->hasTestTriggeredPhpunitErrorEvents(), true)."\n");
        fwrite(STDERR, "testRunnerWarnings: ".count($testResultSum->testRunnerTriggeredWarningEvents())."\n");
        fwrite(STDERR, "testTriggeredWarnings: ".count($testResultSum->testTriggeredPhpunitWarningEvents())."\n");
        foreach ($testResultSum->testRunnerTriggeredWarningEvents() as $e) {
            fwrite(STDERR, "RUNNER WARN: ".(method_exists($e, 'message') ? $e->message() : print_r($e, true))."\n");
        }
        foreach ($testResultSum->testTriggeredPhpunitWarningEvents() as $ev) {
            if (is_array($ev)) {
                foreach ($ev as $e) {
                    fwrite(STDERR, "TEST WARN: ".(is_object($e) && method_exists($e, 'message') ? $e->message() : print_r($e, true))."\n");
                }
            } else {
                fwrite(STDERR, "TEST WARN: ".(is_object($ev) && method_exists($ev, 'message') ? $ev->message() : print_r($ev, true))."\n");
            }
        }
        fwrite(STDERR, "=== END PEST DEBUG ===\n");

CODE;

$contents = str_replace($needle, $debug.$needle, $contents);
file_put_contents($file, $contents);

echo "Patched {$file}\n";
