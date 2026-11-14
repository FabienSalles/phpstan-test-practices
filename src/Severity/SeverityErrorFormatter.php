<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Severity;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;
use PHPStan\DependencyInjection\Container;

final readonly class SeverityErrorFormatter implements ErrorFormatter
{
    public function __construct(
        private Container $container,
        private string $innerFormat,
    ) {
    }

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        $inner = $this->container->getService('errorFormatter.' . $this->innerFormat);
        if (!$inner instanceof ErrorFormatter) {
            $output->writeLineFormatted(sprintf('Error formatter "%s" set in testPractices.errorFormat does not exist.', $this->innerFormat));

            return 1;
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
}
