<?php

// Temporary debug: patches vendor/pestphp/pest/src/Plugins/Parallel/Paratest/WrapperRunner.php
// to print PHPUnit warning details to stderr before the exit code is computed.
// Used to diagnose pest issue #1483 (exit 1 with all tests passing under --parallel).

$wrapperFile = __DIR__.'/../../vendor/pestphp/pest/src/Plugins/Parallel/Paratest/WrapperRunner.php';
$parallelFile = __DIR__.'/../../vendor/pestphp/pest/src/Plugins/Parallel.php';

// 1) In WrapperRunner::complete() — dump result state + log the returned exitcode
$contents = file_get_contents($wrapperFile);
$needle = '$exitcode = Result::exitCode($this->options->configuration, $testResultSum);';

if (! str_contains($contents, $needle)) {
    fwrite(STDERR, "debug-pest-warnings: wrapper target not found\n");
    exit(1);
}

$debug = <<<'CODE'
fwrite(STDERR, "\n=== PEST DEBUG (wrapper) ===\n");
        fwrite(STDERR, "wasSuccessful: ".var_export($testResultSum->wasSuccessful(), true)."\n");
        fwrite(STDERR, "hasPhpunitWarnings: ".var_export($testResultSum->hasPhpunitWarnings(), true)."\n");
        fwrite(STDERR, "hasTestErroredEvents: ".var_export($testResultSum->hasTestErroredEvents(), true)."\n");
        fwrite(STDERR, "hasTestFailedEvents: ".var_export($testResultSum->hasTestFailedEvents(), true)."\n");
        fwrite(STDERR, "hasTestTriggeredPhpunitErrorEvents: ".var_export($testResultSum->hasTestTriggeredPhpunitErrorEvents(), true)."\n");
        fwrite(STDERR, "hasDeprecations: ".var_export($testResultSum->hasDeprecations(), true)."\n");
        fwrite(STDERR, "hasNotices: ".var_export($testResultSum->hasNotices(), true)."\n");
        fwrite(STDERR, "hasWarnings: ".var_export($testResultSum->hasWarnings(), true)."\n");
        fwrite(STDERR, "hasPhpunitDeprecations: ".var_export($testResultSum->hasPhpunitDeprecations(), true)."\n");
        fwrite(STDERR, "hasPhpunitNotices: ".var_export($testResultSum->hasPhpunitNotices(), true)."\n");
        fwrite(STDERR, "hasTests: ".var_export($testResultSum->hasTests(), true)."\n");
        fwrite(STDERR, "hasRiskyTests: ".var_export($testResultSum->hasRiskyTests(), true)."\n");
        fwrite(STDERR, "hasIncompleteTests: ".var_export($testResultSum->hasIncompleteTests(), true)."\n");
        fwrite(STDERR, "hasSkippedTests: ".var_export($testResultSum->hasSkippedTests(), true)."\n");
        fwrite(STDERR, "testRunnerWarnings: ".count($testResultSum->testRunnerTriggeredWarningEvents())."\n");
        fwrite(STDERR, "testTriggeredWarnings: ".count($testResultSum->testTriggeredPhpunitWarningEvents())."\n");
        $__pcfg = $this->options->configuration;
        fwrite(STDERR, "cfg.failOnAllIssues: ".var_export($__pcfg->failOnAllIssues(), true)."\n");
        fwrite(STDERR, "cfg.failOnEmptyTestSuite: ".var_export($__pcfg->failOnEmptyTestSuite(), true)."\n");
        fwrite(STDERR, "cfg.failOnPhpunitWarning: ".var_export($__pcfg->failOnPhpunitWarning(), true)."\n");
        fwrite(STDERR, "cfg.failOnWarning: ".var_export($__pcfg->failOnWarning(), true)."\n");
        fwrite(STDERR, "cfg.failOnRisky: ".var_export($__pcfg->failOnRisky(), true)."\n");
        fwrite(STDERR, "cfg.failOnSkipped: ".var_export($__pcfg->failOnSkipped(), true)."\n");
        fwrite(STDERR, "cfg.failOnIncomplete: ".var_export($__pcfg->failOnIncomplete(), true)."\n");
        fwrite(STDERR, "cfg.failOnDeprecation: ".var_export($__pcfg->failOnDeprecation(), true)."\n");
        fwrite(STDERR, "cfg.failOnPhpunitDeprecation: ".var_export($__pcfg->failOnPhpunitDeprecation(), true)."\n");
        fwrite(STDERR, "cfg.failOnPhpunitNotice: ".var_export($__pcfg->failOnPhpunitNotice(), true)."\n");
        fwrite(STDERR, "cfg.failOnNotice: ".var_export($__pcfg->failOnNotice(), true)."\n");
        fwrite(STDERR, "numberOfTestsRun: ".$testResultSum->numberOfTestsRun()."\n");
        fwrite(STDERR, "numberOfAssertions: ".$testResultSum->numberOfAssertions()."\n");
        fwrite(STDERR, "hasErrors: ".var_export($testResultSum->hasErrors(), true)."\n");
        $__listedFiles = [];
        foreach ($this->testResultFiles as $__f) {
            $__listedFiles[] = $__f->getPathname().'('.(file_exists($__f->getPathname()) ? filesize($__f->getPathname()) : 'missing').')';
        }
        fwrite(STDERR, "testResultFiles: ".implode(', ', $__listedFiles)."\n");
        fwrite(STDERR, "-- per-worker test results --\n");
        foreach ($this->testResultFiles as $__f) {
            if (! $__f->isFile()) {
                fwrite(STDERR, "  ".$__f->getPathname().": MISSING\n");
                continue;
            }
            $__raw = file_get_contents($__f->getPathname());
            $__prev = set_error_handler(function () { return true; });
            try {
                $__tr = unserialize($__raw);
            } catch (\Throwable $__e) {
                $__tr = null;
            }
            restore_error_handler();
            if (! ($__tr instanceof \PHPUnit\TestRunner\TestResult\TestResult)) {
                fwrite(STDERR, "  ".$__f->getPathname().": UNSERIALIZE FAILED (type=".gettype($__tr).")\n");
                continue;
            }
            fwrite(STDERR, "  ".basename($__f->getPathname()).": hasTests=".var_export($__tr->hasTests(), true).", numberOfTestsRun=".$__tr->numberOfTestsRun().", assertions=".$__tr->numberOfAssertions()."\n");
        }

CODE;

$afterCalc = <<<'CODE'

        fwrite(STDERR, "WrapperRunner::complete() returning exitcode=".$exitcode."\n");
        fwrite(STDERR, "=== END PEST DEBUG (wrapper) ===\n");
CODE;

$contents = str_replace(
    $needle,
    $debug.$needle.$afterCalc,
    $contents,
);
file_put_contents($wrapperFile, $contents);

// 2) In Parallel::runTestSuiteInParallel() — log exit code returned by paratest application
$pContents = file_get_contents($parallelFile);
$pNeedle = '$exitCode = $this->paratestCommand()->run(new ArgvInput(array_values($filteredArguments)), new CleanConsoleOutput);';

if (! str_contains($pContents, $pNeedle)) {
    fwrite(STDERR, "debug-pest-warnings: parallel target not found\n");
    exit(1);
}

$pAfter = <<<'CODE'

        fwrite(STDERR, "=== PEST DEBUG (parallel) paratest app returned exitCode=".$exitCode." ===\n");
        $afterAddsOutput = \Pest\Plugins\Actions\CallsAddsOutput::execute($exitCode);
        fwrite(STDERR, "=== PEST DEBUG (parallel) after CallsAddsOutput: ".$afterAddsOutput." ===\n");
        return $afterAddsOutput;
CODE;

$pContents = str_replace(
    $pNeedle."\n\n        return CallsAddsOutput::execute(\$exitCode);",
    $pNeedle.$pAfter,
    $pContents,
);
file_put_contents($parallelFile, $pContents);

echo "Patched {$wrapperFile} and {$parallelFile}\n";
