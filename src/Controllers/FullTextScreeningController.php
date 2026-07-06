<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Models\AiChatMessage;
use SysRevAI\Models\ReferenceFullText;
use SysRevAI\Services\ScreeningService;

/**
 * Full-text screening reuses every mechanic of the title/abstract flow
 * (blinding, conflict queue, coordinator view) by overriding the stage.
 * The view changes to embed the PDF and the per-article chat panel.
 */
final class FullTextScreeningController extends ScreeningController
{
    protected string $stage = 'ft';

    protected function screenView(): string
    {
        return 'full_text/screen';
    }

    protected function extraScreenData(array $review, ?array $reference): array
    {
        // Only meaningful for the empty-state screen (no reference yet):
        // distinguishes "T/A still in progress, can't start" from "all done".
        $taComplete = ScreeningService::taScreeningComplete((int) $review['id']);
        if ($reference === null) {
            return ['fullText' => null, 'chatHistory' => [], 'taComplete' => $taComplete];
        }
        $refId = (int) $reference['id'];
        return [
            'fullText'    => ReferenceFullText::find($refId),
            'chatHistory' => AiChatMessage::history($refId, (int) Auth::id(), 20),
            'taComplete'  => $taComplete,
        ];
    }
}
