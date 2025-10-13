<?php

namespace App\Services\AI;

use App\Services\AnthropicService;

class CongressAnalysisOrchestrator
{
    private AnthropicService $anthropicService;
    private array $agents;

    public function __construct(AnthropicService $anthropicService)
    {
        $this->anthropicService = $anthropicService;
        $this->initializeAgents();
    }

    private function initializeAgents(): void
    {
        $this->agents = [
            'query_planner' => new QueryPlannerAgent($this->anthropicService),
            'data_analyst' => new DataAnalystAgent($this->anthropicService),
            'policy_expert' => new PolicyExpertAgent($this->anthropicService),
            'trend_analyzer' => new TrendAnalyzerAgent($this->anthropicService),
            'response_synthesizer' => new ResponseSynthesizerAgent($this->anthropicService),
        ];
    }

    public function analyzeQuestion(string $question): array
    {
        // 1. Query Planner determines what data is needed
        $queryPlan = $this->agents['query_planner']->planQuery($question);
        
        // 2. Data Analyst executes queries and processes results
        $dataResults = $this->agents['data_analyst']->executeAnalysis($queryPlan);
        
        // 3. Policy Expert provides context and implications
        $policyContext = $this->agents['policy_expert']->analyzePolicy($question, $dataResults);
        
        // 4. Trend Analyzer identifies patterns and trends
        $trendAnalysis = $this->agents['trend_analyzer']->analyzeTrends($dataResults);
        
        // 5. Response Synthesizer combines all insights
        $finalResponse = $this->agents['response_synthesizer']->synthesizeResponse([
            'question' => $question,
            'data' => $dataResults,
            'policy_context' => $policyContext,
            'trends' => $trendAnalysis
        ]);

        return $finalResponse;
    }
}