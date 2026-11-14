<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Severity;

use FabienSalles\PHPStanTestPractices\Diff\ChangedLines;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;
use PHPStan\DependencyInjection\Container;
use RuntimeException;

final readonly class SeverityErrorFormatter implements ErrorFormatter
{
    public function __construct(
        private Container $container,
        private string $innerFormat,
        private ?string $diffBase,
        private string $workingDirectory,
    ) {
    }

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        $inner = $this->container->getService('errorFormatter.' . $this->innerFormat);
        if (!$inner instanceof ErrorFormatter) {
            $output->writeLineFormatted(sprintf('Error formatter "%s" set in testPractices.errorFormat does not exist.', $this->innerFormat));

            return 1;
        }

        if ($this->diffBase !== null) {
            $filtered = $this->onChangedLines($this->diffBase, $analysisResult, $output);
            if ($filtered === null) {
                return 1;
            }

            $analysisResult = $filtered;
        }

        $exitCode = $inner->formatErrors($analysisResult, $output);
        if ($exitCode === 0 || $analysisResult->hasInternalErrors() || $analysisResult->getNotFileSpecificErrors() !== []) {
            return $exitCode;
        }

        $warnings = count(array_filter($analysisResult->getFileSpecificErrors(), Severity::isWarning(...)));
        $errors = $analysisResult->getTotalErrorsCount() - $warnings;
        if ($this->innerFormat === 'table') {
            $output->writeLineFormatted(sprintf('Test practices: %d blocking error(s), %d non-blocking warning(s).', $errors, $warnings));
            $output->writeLineFormatted('');
        }

        return $errors > 0 ? $exitCode : 0;
    }

    private function onChangedLines(string $diffBase, AnalysisResult $analysisResult, Output $output): ?AnalysisResult
    {
        if (!method_exists($analysisResult, 'withFileSpecificErrors')) {
            $output->writeLineFormatted('testPractices.diffBase requires PHPStan 2.1.41 or later.');

            return null;
        }

        try {
            $changedLines = ChangedLines::since($diffBase, $this->workingDirectory);
        } catch (RuntimeException $exception) {
            $output->writeLineFormatted(sprintf('testPractices.diffBase: %s', $exception->getMessage()));

            return null;
        }

        return $analysisResult->withFileSpecificErrors(array_values(array_filter($analysisResult->getFileSpecificErrors(), $changedLines->contains(...))));
    }
}
