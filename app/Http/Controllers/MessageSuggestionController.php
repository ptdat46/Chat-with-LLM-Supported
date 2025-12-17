<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class MessageSuggestionController extends Controller
{
    private $ollamaHost = 'http://localhost:11434';
    private $model = 'llama-3-8b-tuned';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function testOllama(Request $request)
    {
        $testMessage = $request->input('message', 'Xin chào, bạn khỏe không?');
        
        try {
            $startTime = microtime(true);
            
            $response = Http::timeout(90)->post("{$this->ollamaHost}/api/generate", [
                'model' => $this->model,
                'prompt' => $testMessage,
                'stream' => false,
                'options' => [
                    'temperature' => 0.7,
                    'num_predict' => 50,
                ]
            ]);

            $duration = round((microtime(true) - $startTime), 2);

            $result = [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'duration' => $duration . 's',
                'model' => $this->model,
                'host' => $this->ollamaHost,
                'prompt' => $testMessage,
            ];

            if ($response->successful()) {
                $data = $response->json();
                $result['response'] = $data['response'] ?? 'No response field';
                $result['full_data'] = $data;
            } else {
                $result['error'] = $response->body();
                $result['error_status'] = $response->status();
            }

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'model' => $this->model,
                'host' => $this->ollamaHost,
            ], 500);
        }
    }

    public function getSuggestions(Request $request)
    {
        $recentMessages = $this->getRecentConversation();
        
        if ($recentMessages->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No recent conversation found'
            ]);
        }
        $context = $this->buildContext($recentMessages);

        $cheerfulSuggestion = $this->generateSuggestion($context, 'cheerful');
        $professionalSuggestion = $this->generateSuggestion($context, 'professional');

        return response()->json([
            'success' => true,
            'suggestions' => [
                'cheerful' => $cheerfulSuggestion,
                'professional' => $professionalSuggestion,
            ],
            'context_messages_count' => $recentMessages->count(),
            'context' => $context,
        ]);
    }

    private function getRecentConversation()
    {
        // Lấy 50 messages gần nhất
        $messages = Message::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        if ($messages->isEmpty()) {
            return collect([]);
        }

        $messages = $messages->reverse()->values();

        if ($messages->count() < 2) {
            return $messages;
        }

        $conversationMessages = collect([$messages->last()]);
        $timeGaps = [];
        $sumGaps = 0;
        
        for ($i = $messages->count() - 2; $i >= 0; $i--) {
            $currentMsg = $messages[$i];
            $nextMsg = $messages[$i + 1];

            $gap = $nextMsg->created_at->diffInSeconds($currentMsg->created_at);
            
            if (count($timeGaps) >= 3) {
                $mean = $sumGaps / count($timeGaps);

                $variance = 0;
                foreach ($timeGaps as $g) {
                    $variance += pow($g - $mean, 2);
                }
                $stdDev = sqrt($variance / count($timeGaps));

                $threshold = max($mean + (2 * $stdDev), 300);
                
                if ($gap > $threshold) {
                    \Log::info('Conversation break detected', [
                        'gap' => $gap,
                        'mean' => $mean,
                        'stdDev' => $stdDev,
                        'threshold' => $threshold,
                    ]);
                    break;
                }
            }
            
            $conversationMessages->prepend($currentMsg);
            
            $timeGaps[] = $gap;
            $sumGaps += $gap;
        }

        return $conversationMessages->take(15);
    }

    private function buildContext($messages)
    {
        $context = "";
        
        foreach ($messages as $message) {
            $userName = $message->user ? $message->user->name : 'Unknown';
            $context .= "{$userName}: {$message->text}\n";
        }

        return trim($context);
    }

    private function generateSuggestion($context, $style)
    {
        if ($style === 'cheerful') {
            $prompt = "Dựa vào đoạn chat:\n{$context}\n\nViết 1 tin nhắn tiếp theo vui vẻ, thân thiện (1-2 câu):";
        } else {
            $prompt = "Dựa vào đoạn chat:\n{$context}\n\nViết 1 tin nhắn tiếp theo nghiêm túc, chuyên nghiệp (1-2 câu):";
        }

        try {
            $response = Http::timeout(90)->post("{$this->ollamaHost}/api/generate", [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => 0.7,
                    'num_predict' => 50,
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['response'])) {
                    $text = trim($data['response']);
                    \Log::info('Generated suggestion', ['style' => $style, 'text' => substr($text, 0, 100)]);
                    return $this->cleanSuggestion($text);
                }
                
                \Log::warning('No response field in Ollama result', ['data' => $data]);
                return 'Model không trả về kết quả.';
            }

            \Log::error('Ollama API error', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return 'API lỗi: ' . $response->status();
            
        } catch (\Exception $e) {
            \Log::error('Exception in generateSuggestion: ' . $e->getMessage());
            return 'Lỗi: ' . $e->getMessage();
        }
    }

    private function cleanSuggestion($text)
    {
        $text = trim($text, '"\'');
        
        $text = preg_replace('/^(Tin nhắn|Message|Response|Trả lời|Reply):\s*/i', '', $text);
        
        $text = preg_replace('/^[A-Za-z\s]+:\s*/', '', $text);
        
        return trim($text);
    }
}
