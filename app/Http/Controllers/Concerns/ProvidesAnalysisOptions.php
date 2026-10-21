<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ProposalStatus;
use App\Models\Target;
use App\Models\Vector;

/**
 * Active vectors and targets for the "run analysis" pickers, most upvoted first.
 */
trait ProvidesAnalysisOptions
{
    /**
     * @return list<array{id: int, label: string, votes_count: int}>
     */
    protected function vectorOptions(): array
    {
        return array_values(Vector::query()
            ->where('status', ProposalStatus::Active)
            ->orderByDesc('votes_count')
            ->orderBy('title')
            ->get(['id', 'title', 'votes_count'])
            ->map(fn (Vector $vector): array => ['id' => $vector->id, 'label' => $vector->title, 'votes_count' => $vector->votes_count])
            ->all());
    }

    /**
     * @return list<array{id: int, label: string, votes_count: int}>
     */
    protected function targetOptions(): array
    {
        return array_values(Target::query()
            ->where('status', ProposalStatus::Active)
            ->orderByDesc('votes_count')
            ->orderBy('name')
            ->get(['id', 'name', 'votes_count'])
            ->map(fn (Target $target): array => ['id' => $target->id, 'label' => $target->name, 'votes_count' => $target->votes_count])
            ->all());
    }
}
