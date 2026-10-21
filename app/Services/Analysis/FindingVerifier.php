<?php

namespace App\Services\Analysis;

use App\Models\Finding;

/**
 * Independently fact-checks a finding: re-checks its claims and sources and looks for errors.
 */
interface FindingVerifier
{
    /**
     * @throws AnalysisFailed
     */
    public function verify(Finding $finding, References $references): VerificationResult;
}
