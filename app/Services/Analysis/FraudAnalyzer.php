<?php

namespace App\Services\Analysis;

use App\Models\Target;
use App\Models\Vector;

/**
 * Cross-checks a fraud vector against a target and returns scored leads.
 */
interface FraudAnalyzer
{
    /**
     * @throws AnalysisFailed
     */
    public function analyze(Vector $vector, Target $target, References $references): AnalysisResult;
}
