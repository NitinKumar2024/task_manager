<?php
/**
 * Gemini AI Integration Module
 */

require_once __DIR__ . '/config.php';

class GeminiClient {
    private string $apiKey;
    private string $model;
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(string $apiKey, string $model = 'gemini-3.8-flash') {
        $this->apiKey = trim($apiKey);
        $this->model = !empty($model) ? trim($model) : 'gemini-3.8-flash';
    }

    /**
     * Send request to Gemini API
     */
    public function generateContent(string $prompt, string $systemInstruction = '', bool $jsonMode = false): array {
        if (empty($this->apiKey)) {
            throw new Exception("Gemini API Key is not configured. Please set your API key in Settings.");
        }

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $jsonMode ? 0.1 : 0.4,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 4096
            ]
        ];

        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        if ($jsonMode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        // Try primary model, fallback if model not found or deprecated
        $modelsToTry = [];
        // If current model is deprecated gemini-2.5-flash, prefer gemini-3.8-flash
        $primary = ($this->model === 'gemini-2.5-flash') ? 'gemini-3.8-flash' : $this->model;
        $modelsToTry[] = $primary;
        if (!in_array('gemini-3.8-flash', $modelsToTry)) $modelsToTry[] = 'gemini-3.8-flash';
        if (!in_array('gemini-3.8-pro', $modelsToTry)) $modelsToTry[] = 'gemini-3.8-pro';
        if (!in_array('gemini-1.5-flash', $modelsToTry)) $modelsToTry[] = 'gemini-1.5-flash';

        $lastError = '';
        foreach ($modelsToTry as $currentModel) {
            try {
                $response = $this->executeCurl($currentModel, $payload);
                return $response;
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                // If it's not a model error or deprecated error, don't keep cycling
                $isModelError = str_contains($lastError, '404') || 
                                str_contains($lastError, 'not found') || 
                                str_contains($lastError, 'no longer available') ||
                                str_contains($lastError, 'not supported');
                if (!$isModelError) {
                    throw $e;
                }
            }
        }

        throw new Exception($lastError ?: "Failed to generate AI content");
    }

    private function executeCurl(string $model, array $payload): array {
        $url = self::BASE_URL . rawurlencode($model) . ':generateContent?key=' . rawurlencode($this->apiKey);

        $ch = curl_init($url);
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        // Windows CA bundle handling
        $caPaths = [
            'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
            'C:\\xampp\\php\\extras\\ssl\\cacert.pem'
        ];
        foreach ($caPaths as $ca) {
            if (file_exists($ca)) {
                curl_setopt($ch, CURLOPT_CAINFO, $ca);
                break;
            }
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Network connection error to Gemini API: " . $curlError);
        }

        $data = json_decode($rawResponse, true);

        if ($httpCode !== 200) {
            $errorMsg = $data['error']['message'] ?? "Gemini API returned HTTP status $httpCode";
            throw new Exception("Gemini API Error: " . $errorMsg);
        }

        if (empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Gemini returned an empty or blocked response.");
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'];

        return [
            'raw' => $data,
            'text' => $text,
            'model' => $model
        ];
    }

    /**
     * Smart Natural Language Task Parser
     */
    public function quickParseTask(string $input, array $categories = [], array $tags = []): array {
        $catNames = implode(', ', array_column($categories, 'name'));
        $tagNames = implode(', ', array_column($tags, 'name'));
        $now = date('Y-m-d H:i');

        $systemPrompt = "You are an intelligent task parsing assistant. The user will provide a freeform, natural language sentence describing a task.
Extract structured fields and return strictly valid JSON matching this schema:
{
  \"title\": \"clean concise task title without redundant timing/priority words\",
  \"description\": \"any additional context, link, or note mentioned in prompt or empty string\",
  \"category\": \"best matching category name from available categories [{$catNames}] or best guess\",
  \"priority\": \"low | medium | high | critical\",
  \"energy_level\": \"low | medium | high\",
  \"due_date\": \"YYYY-MM-DD HH:MM or YYYY-MM-DD or null if no deadline specified\",
  \"estimated_minutes\": integer (in minutes, e.g. 30, 60, 120; 0 if unknown),
  \"tags\": [\"array of single-word relevant tags from [{$tagNames}] or new relevant ones\"],
  \"recurrence\": \"none | daily | weekly | monthly\"
}
Current local time is {$now}. Relative dates like 'tomorrow', 'next Monday', 'in 2 hours', 'end of day' must be converted relative to {$now}.
Return ONLY valid JSON.";

        $response = $this->generateContent("Parse this task: \"{$input}\"", $systemPrompt, true);
        $cleanJson = $this->extractJson($response['text']);
        $parsed = json_decode($cleanJson, true);

        if (!is_array($parsed) || empty($parsed['title'])) {
            return [
                'title' => trim($input),
                'description' => '',
                'category' => '',
                'priority' => 'medium',
                'energy_level' => 'medium',
                'due_date' => null,
                'estimated_minutes' => 30,
                'tags' => [],
                'recurrence' => 'none'
            ];
        }

        return $parsed;
    }

    /**
     * Break down a complex task into actionable subtasks
     */
    public function breakdownTask(array $task): array {
        $title = $task['title'] ?? '';
        $desc = $task['description'] ?? '';
        $priority = $task['priority'] ?? 'medium';

        $systemPrompt = "You are an expert productivity coach and project strategist. Break down the user's task into 3 to 7 concrete, highly actionable, chronological subtasks.
Return strictly valid JSON with this structure:
{
  \"summary\": \"Brief 1-sentence strategic advice for tackling this task\",
  \"subtasks\": [
    {
      \"title\": \"Clear action verb + direct object\",
      \"estimated_minutes\": integer (e.g. 15, 30, 45)
    }
  ]
}
Return ONLY valid JSON.";

        $userPrompt = "Task Title: {$title}\nDescription: {$desc}\nPriority: {$priority}";
        $response = $this->generateContent($userPrompt, $systemPrompt, true);
        $cleanJson = $this->extractJson($response['text']);
        $result = json_decode($cleanJson, true);

        if (!is_array($result) || empty($result['subtasks'])) {
            throw new Exception("Could not generate subtasks from AI response.");
        }

        return $result;
    }

    /**
     * Generate an intelligent Daily Plan / Briefing
     */
    public function planDay(array $pendingTasks, array $completedToday, string $userName = 'there'): array {
        $now = date('Y-m-d H:i');
        $tasksJson = json_encode($pendingTasks, JSON_UNESCAPED_SLASHES);
        $completedJson = json_encode($completedToday, JSON_UNESCAPED_SLASHES);

        $systemPrompt = "You are a master productivity strategist and personal executive assistant to {$userName}.
Current date and time: {$now}.
Analyze their pending tasks and today's completed tasks.
Formulate a smart, energized, realistic day plan.
Return strictly valid JSON in this format:
{
  \"greeting\": \"Warm, motivating greeting tailored to {$userName}\",
  \"productivity_quote\": \"Short punchy quote or tip\",
  \"top_priorities\": [
    {\"task_id\": integer or null, \"title\": \"string\", \"reason\": \"why this must be done today\"}
  ],
  \"time_blocks\": [
    {
      \"phase\": \"Morning Focus Block (Deep Work)\",
      \"tasks\": [\"task titles\"],
      \"advice\": \"focus strategy\"
    },
    {
      \"phase\": \"Midday Momentum\",
      \"tasks\": [\"task titles\"],
      \"advice\": \"quick execution strategy\"
    },
    {
      \"phase\": \"Afternoon Wrap-Up & Admin\",
      \"tasks\": [\"task titles\"],
      \"advice\": \"review & low-energy items\"
    }
  ],
  \"strategic_advice\": \"Actionable recommendations on delegation, avoiding bottlenecks, or handling overdue items\"
}
Return ONLY valid JSON.";

        $userPrompt = "Pending Tasks:\n{$tasksJson}\n\nCompleted Today:\n{$completedJson}";
        $response = $this->generateContent($userPrompt, $systemPrompt, true);
        $cleanJson = $this->extractJson($response['text']);
        $result = json_decode($cleanJson, true);

        if (!is_array($result)) {
            throw new Exception("Could not generate daily briefing.");
        }

        return $result;
    }

    /**
     * Enhance & Polish a Task
     */
    public function enhanceTask(string $title, string $description = ''): array {
        $systemPrompt = "You are an expert productivity engineer. The user has a draft task.
Polish it into a high-performance, SMART (Specific, Measurable, Achievable, Relevant, Time-bound) task with:
1. An action-oriented concise title
2. Formatted markdown description with:
   - Objective
   - Definition of Done (Checklist)
   - Useful tips or pitfalls to avoid
3. Suggested priority (low, medium, high, critical)
4. Estimated duration in minutes.
Return strictly valid JSON:
{
  \"improved_title\": \"string\",
  \"enhanced_description\": \"markdown string\",
  \"suggested_priority\": \"low | medium | high | critical\",
  \"estimated_minutes\": integer
}
Return ONLY valid JSON.";

        $userPrompt = "Task: {$title}\nExisting Notes: {$description}";
        $response = $this->generateContent($userPrompt, $systemPrompt, true);
        $cleanJson = $this->extractJson($response['text']);
        $result = json_decode($cleanJson, true);

        if (!is_array($result)) {
            throw new Exception("Could not enhance task.");
        }

        return $result;
    }

    /**
     * AI Copilot Conversational Chat with full task list context
     */
    public function copilotChat(string $message, array $history, array $tasksContext, string $userName = 'there'): string {
        $tasksSummary = json_encode($tasksContext, JSON_UNESCAPED_SLASHES);

        $systemPrompt = "You are NexusAI, the user's dedicated intelligent task manager and personal Chief of Staff.
User's Name: {$userName}.
Current Local Time: " . date('Y-m-d H:i') . ".
You have direct real-time visibility into the user's task database:
Tasks Context: {$tasksSummary}

Your capabilities:
- Answering questions about pending, overdue, or completed tasks
- Helping prioritize what to do right now based on deadlines and energy
- Drafting checklists, outlines, emails, or action plans for any specific task
- Coaching on focus, time management (Pomodoro, Time Blocking, Eisenhower matrix)
- Giving encouraging, sharp, and highly concise executive-level guidance.

Format your responses with clean GitHub-flavored markdown (bullet points, bold highlights, code blocks when helpful). Be concise, helpful, and proactive.";

        $formattedPrompt = "Conversation history:\n";
        foreach (array_slice($history, -6) as $turn) {
            $role = ($turn['role'] === 'user') ? 'User' : 'Assistant';
            $formattedPrompt .= "{$role}: {$turn['message']}\n";
        }
        $formattedPrompt .= "\nUser: {$message}\nAssistant:";

        $response = $this->generateContent($formattedPrompt, $systemPrompt, false);
        return $response['text'] ?? "I'm here to help manage your tasks. What would you like to focus on?";
    }

    /**
     * Clean markdown JSON wrappers (e.g. ```json ... ```)
     */
    private function extractJson(string $text): string {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $text, $matches)) {
            return trim($matches[1]);
        }
        return $text;
    }
}
