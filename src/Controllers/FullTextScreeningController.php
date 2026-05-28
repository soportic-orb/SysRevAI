<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Models\AiChatMessage;
use SysRevAI\Models\ReferenceFullText;

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
        if ($reference === null) {
            return ['fullText' => null, 'chatHistory' => []];
        }
        $refId = (int) $reference['id'];
        return [
            'fullText'    => ReferenceFullText::find($refId),
            'chatHistory' => AiChatMessage::history($refId, (int) Auth::id(), 20),
        ];
    }
}
