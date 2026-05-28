<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Reference;
use SysRevAI\Models\ReferenceFullText;
use SysRevAI\Models\Review;
use SysRevAI\Models\RiskOfBias;
use SysRevAI\Services\ClaudeService;
use SysRevAI\Services\RiskOfBiasService;

final class RiskOfBiasController
{
    public function index(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        $tool = $this->resolveTool((string) ($_GET['tool'] ?? ''));
        $def = RiskOfBiasService::tool($tool);

        $rows = RiskOfBias::forReviewTool($rid, $tool);
        $traffic = $this->buildTrafficLight($rows, $def);
        $summary = $this->buildSummary($traffic, $def);

        echo View::render('rob/index', [
            'review'     => $review,
            'tool'       => $tool,
            'toolDef'    => $def,
            'enabledTools' => $this->enabledTools(),
            'traffic'    => $traffic,
            'summary'    => $summary,
        ]);
    }

    public function edit(string $id, string $refId): void
    {
        [$review, $reference] = $this->loadRefOrDeny((int) $id, (int) $refId);
        $tool = $this->resolveTool((string) ($_GET['tool'] ?? ''));
        $def = RiskOfBiasService::tool($tool);
        $judgements = RiskOfBias::forAssessment((int) $reference['id'], (int) Auth::id(), $tool);

        echo View::render('rob/edit', [
            'review'       => $review,
            'reference'    => $reference,
            'tool'         => $tool,
            'toolDef'      => $def,
            'enabledTools' => $this->enabledTools(),
            'judgements'   => $judgements,
        ]);
    }

    public function save(string $id, string $refId): void
    {
        [$review, $reference] = $this->loadRefOrDeny((int) $id, (int) $refId);
        $tool = $this->resolveTool((string) ($_POST['tool'] ?? ''));
        $def = RiskOfBiasService::tool($tool);

        $judgements = $_POST['judgement'] ?? [];
        $justifications = $_POST['justification'] ?? [];
        if (!is_array($judgements)) {
            $judgements = [];
        }
        if (!is_array($justifications)) {
            $justifications = [];
        }

        foreach ($def['domains'] as $domain) {
            $j = (string) ($judgements[$domain] ?? '');
            $just = trim((string) ($justifications[$domain] ?? ''));
            $finalJ = RiskOfBiasService::isValidJudgement($tool, $j) ? $j : null;
            RiskOfBias::upsert((int) $reference['id'], (int) Auth::id(), $tool, $domain, $finalJ, $just !== '' ? $just : null);
        }

        ActivityLog::record('rob.saved', ['reference_id' => (int) $reference['id'], 'tool' => $tool], (int) $id);
        Session::flash('success', __('rob.saved'));
        redirect('/reviews/' . (int) $id . '/risk-of-bias/' . (int) $reference['id'] . '?tool=' . $tool);
    }

    /** AI suggestion for a single domain. Returns JSON. */
    public function ai(string $id, string $refId): void
    {
        [$review, $reference] = $this->loadRefOrDeny((int) $id, (int) $refId);
        $tool = (string) ($_POST['tool'] ?? '');
        $domain = (string) ($_POST['domain'] ?? '');
        header('Content-Type: application/json; charset=utf-8');

        if (!RiskOfBiasService::knownTool($tool) || !RiskOfBiasService::isValidDomain($tool, $domain)) {
            echo json_encode(['ok' => false, 'error' => 'invalid_request']);
            return;
        }

        $ft = ReferenceFullText::find((int) $reference['id']);
        $text = (string) ($ft['extracted_text'] ?? ($reference['abstract'] ?? ''));
        if (trim($text) === '') {
            echo json_encode(['ok' => false, 'error' => 'no_text']);
            return;
        }

        $result = ClaudeService::fromSettings()->assessBiasDomain($text, $tool, $domain, (int) $id);
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            echo json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'ai_error')]);
            return;
        }

        $j = (string) ($result['data']['judgement'] ?? '');
        $justification = (string) ($result['data']['justification'] ?? '');
        if (!RiskOfBiasService::isValidJudgement($tool, $j)) {
            // Normalize unexpected vocabulary to a valid value when possible.
            $j = match (true) {
                str_contains($j, 'critical') => 'critical',
                str_contains($j, 'serious') => 'serious',
                str_contains($j, 'moderate') => 'moderate',
                str_contains($j, 'high') => 'high',
                str_contains($j, 'some') => 'some_concerns',
                str_contains($j, 'low') => 'low',
                str_contains($j, 'yes') => 'yes',
                str_contains($j, 'no') => 'no',
                default => 'no_information',
            };
            if (!RiskOfBiasService::isValidJudgement($tool, $j)) {
                $j = 'no_information';
            }
        }

        echo json_encode(['ok' => true, 'judgement' => $j, 'justification' => $justification], JSON_UNESCAPED_UNICODE);
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    private function enabledTools(): array
    {
        $enabled = (array) (setting('reviews.rob_tools') ?? RiskOfBiasService::TOOLS);
        $enabled = array_values(array_intersect($enabled, RiskOfBiasService::TOOLS));
        return $enabled !== [] ? $enabled : RiskOfBiasService::TOOLS;
    }

    private function resolveTool(string $candidate): string
    {
        $enabled = $this->enabledTools();
        if (RiskOfBiasService::knownTool($candidate) && in_array($candidate, $enabled, true)) {
            return $candidate;
        }
        return $enabled[0];
    }

    /**
     * Build the traffic-light matrix: per reference, the most-severe judgement
     * across reviewers for each domain.
     * @return array<int,array{reference_id:int,title:string,cells:array<string,string>}>
     */
    private function buildTrafficLight(array $rows, array $def): array
    {
        $byRef = [];
        foreach ($rows as $row) {
            $rid = (int) $row['reference_id'];
            $byRef[$rid]['reference_id'] = $rid;
            $byRef[$rid]['title'] = (string) ($row['title'] ?? '');
            $byRef[$rid]['cells'] ??= [];
            $domain = $row['domain'];
            $j = $row['judgement'];
            if ($domain === null || $j === null) {
                continue;
            }
            $current = $byRef[$rid]['cells'][$domain] ?? null;
            if ($current === null || RiskOfBiasService::level($j) > RiskOfBiasService::level($current)) {
                $byRef[$rid]['cells'][$domain] = $j;
            }
        }
        return array_values($byRef);
    }

    /**
     * Counts of each judgement per domain for the summary stacked-bar chart.
     * @return array{labels:string[],datasets:array<int,array{label:string,backgroundColor:string,data:int[]}>}
     */
    private function buildSummary(array $traffic, array $def): array
    {
        $labels = $def['domains'];
        $datasets = [];
        foreach ($def['judgements'] as $j) {
            $counts = [];
            foreach ($labels as $domain) {
                $counts[] = $this->countWithJudgement($traffic, $domain, $j);
            }
            $datasets[] = [
                'label'           => $j,
                'backgroundColor' => RiskOfBiasService::color($j),
                'data'            => $counts,
            ];
        }
        return ['labels' => $labels, 'datasets' => $datasets];
    }

    private function countWithJudgement(array $traffic, string $domain, string $judgement): int
    {
        $c = 0;
        foreach ($traffic as $row) {
            if (($row['cells'][$domain] ?? null) === $judgement) {
                $c++;
            }
        }
        return $c;
    }

    /** @return array{0:array,1:array} [review, reference] */
    private function loadRefOrDeny(int $reviewId, int $refId): array
    {
        $review = Review::find($reviewId);
        $reference = Reference::find($refId);
        if ($review === null || $reference === null
            || (int) $reference['review_id'] !== $reviewId
            || !Review::userCanAccess($reviewId, (int) Auth::id())) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return [$review, $reference];
    }

    private function memberOrDeny(int $reviewId): array
    {
        $review = Review::find($reviewId);
        if ($review === null || !Review::userCanAccess($reviewId, (int) Auth::id())) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $review;
    }
}
